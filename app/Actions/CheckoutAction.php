<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\PromoLog;
use App\Services\PromoService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutAction
{
    public function __construct(private WalletService $wallet, private PromoService $promos) {}

    /**
     * Checkout keranjang milik $user untuk satu $store, dibayar dari saldo.
     * Seluruhnya satu transaksi DB: gagal di mana pun = tidak ada yang berubah.
     *
     * @param  array{recipient_name:string, recipient_phone:string, shipping_address:string, shipping_latitude?:?float, shipping_longitude?:?float, note?:?string}  $shipping
     */
    public function execute(User $user, Store $store, array $shipping): Transaction
    {
        if (! $store->is_active) {
            throw new CheckoutException('Toko sedang tidak aktif.');
        }

        return DB::transaction(function () use ($user, $store, $shipping) {
            $cart = CartItem::where('user_id', $user->id)
                ->whereHas('product', fn($q) => $q->where('store_id', $store->id))
                ->get();

            if ($cart->isEmpty()) {
                throw new CheckoutException('Keranjang untuk toko ini kosong.');
            }

            // Kunci baris produk (urut id agar tak deadlock) lalu validasi stok memakai data terkunci.
            $products = Product::whereIn('id', $cart->pluck('product_id')->sort()->values())
                ->lockForUpdate()->get()->keyBy('id');

            $total = '0.00';
            $lines = [];

            foreach ($cart as $item) {
                $product = $products[$item->product_id] ?? null;

                if (! $product || ! $product->is_active) {
                    throw new CheckoutException('Ada produk yang sudah tidak tersedia. Perbarui keranjang Anda.');
                }
                if ($product->stock < $item->quantity) {
                    throw new CheckoutException("Stok \"{$product->name}\" tidak cukup (tersisa {$product->stock}).");
                }

                $subtotal = bcmul((string) $product->price, (string) $item->quantity, 2);
                $total = bcadd($total, $subtotal, 2);
                $lines[] = [$product, $item->quantity, $subtotal];
            }

            // Promo dievaluasi ulang di sini dengan row lock: pratinjau di halaman checkout bukan jaminan.
            $promo = null;
            $discount = '0.00';

            if (filled($shipping['promo_code'] ?? null)) {
                ['promo' => $promo, 'discount' => $discount] = $this->promos->evaluate(
                    $shipping['promo_code'], $user, $store, $total, lock: true,
                );
            }

            $deliveryType = $shipping['delivery_type'] ?? (isset($shipping['shipping_rate_id']) ? 'courier' : 'pickup');

            if ($deliveryType === 'courier') {
                $rate = \App\Models\ShippingRate::where('is_active', true)->find($shipping['shipping_rate_id'] ?? null);
                if (! $rate) {
                    throw new CheckoutException('Wilayah pengiriman tidak valid atau tarif kurir tidak aktif.');
                }
                $shippingCost = (string) $rate->shipping_cost;
                $shippingCity = $rate->city_name;
                $shippingDistrict = $shipping['shipping_district'] ?? null;
                $shippingPostalCode = $shipping['shipping_postal_code'] ?? null;
                $shippingAddress = $shipping['shipping_address'] ?? '';
                $shippingLat = $shipping['shipping_latitude'] ?? null;
                $shippingLng = $shipping['shipping_longitude'] ?? null;
            } else {
                $deliveryType = 'pickup';
                $shippingCost = '0.00';
                $shippingCity = null;
                $shippingDistrict = null;
                $shippingPostalCode = null;
                $shippingAddress = ! empty($shipping['shipping_address'])
                    ? $shipping['shipping_address']
                    : ('Ambil Sendiri di Toko (' . $store->name . ')');
                $shippingLat = null;
                $shippingLng = null;
            }

            $productNet = bcsub($total, $discount, 2);
            if (bccomp($productNet, '0.00', 2) < 0) {
                $productNet = '0.00';
            }
            $final = bcadd($productNet, $shippingCost, 2);

            $transaction = Transaction::create([
                'invoice_number' => $this->invoiceNumber(),
                'user_id' => $user->id,
                'store_id' => $store->id,
                'total_amount' => $total,
                'promo_id' => $promo?->id,
                'discount_amount' => $discount,
                'shipping_cost' => $shippingCost,
                'final_amount' => $final,
                'status' => OrderStatus::Pending,
                'delivery_type' => $deliveryType,
                'recipient_name' => $shipping['recipient_name'],
                'recipient_phone' => $shipping['recipient_phone'],
                'shipping_city' => $shippingCity,
                'shipping_district' => $shippingDistrict,
                'shipping_postal_code' => $shippingPostalCode,
                'shipping_address' => $shippingAddress,
                'shipping_latitude' => $shippingLat,
                'shipping_longitude' => $shippingLng,
                'note' => $shipping['note'] ?? null,
            ]);

            foreach ($lines as [$product, $qty, $subtotal]) {
                $transaction->details()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'price_at_transaction' => $product->price,
                    'subtotal' => $subtotal,
                ]);
                $product->decrement('stock', $qty);
            }

            // Potong saldo (melempar InsufficientBalanceException -> seluruh transaksi di-rollback)
            $this->wallet->debit($user, $final, 'purchase', $transaction, "Pembayaran {$transaction->invoice_number}");

            if ($promo) {
                $promo->increment('used_count');
                PromoLog::create([
                    'promo_id' => $promo->id,
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'discount_amount' => $discount,
                ]);
            }

            $transaction->update(['status' => OrderStatus::Paid]);
            $transaction->statusLogs()->createMany([
                ['from_status' => null, 'to_status' => OrderStatus::Pending->value, 'changed_by' => $user->id, 'note' => 'Pesanan dibuat'],
                ['from_status' => OrderStatus::Pending->value, 'to_status' => OrderStatus::Paid->value, 'changed_by' => $user->id, 'note' => 'Dibayar dengan saldo'],
            ]);

            CartItem::whereIn('id', $cart->pluck('id'))->delete();

            return $transaction;
        });
    }

    private function invoiceNumber(): string
    {
        do {
            $number = 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Transaction::where('invoice_number', $number)->exists());

        return $number;
    }
}
