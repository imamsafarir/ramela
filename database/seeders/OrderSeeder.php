<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\DeliveryPhoto;
use App\Models\Product;
use App\Models\Promo;
use App\Models\PromoLog;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionStatusLog;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all()->keyBy('slug');
        $customers = User::role(Role::User->value)->get();
        $couriers = User::role(Role::Courier->value)->get();
        $admins = User::role(Role::Admin->value)->get();
        $rates = ShippingRate::all();
        $promos = Promo::all();

        if ($customers->isEmpty() || $couriers->isEmpty() || $rates->isEmpty()) {
            return;
        }

        $allProducts = Product::all()->groupBy('store_id');

        // 1. Seed Shopping Cart Items for Several Users
        $cartUsers = $customers->take(6);
        foreach ($cartUsers as $u) {
            $favStore = $stores->random();
            $favProducts = $allProducts->get($favStore->id);
            if ($favProducts && $favProducts->isNotEmpty()) {
                $chosen = $favProducts->random(min(rand(2, 4), $favProducts->count()));
                foreach ($chosen as $cp) {
                    CartItem::updateOrCreate(
                        [
                            'user_id' => $u->id,
                            'product_id' => $cp->id,
                        ],
                        [
                            'quantity' => rand(1, 3),
                        ]
                    );
                }
            }
        }

        // 2. Prevent duplicate invoice seeding
        if (Transaction::count() >= 50) {
            return;
        }

        // 3. Seed Transactions across all statuses
        $statusList = [
            OrderStatus::Pending->value => 5,
            OrderStatus::Paid->value => 7,
            OrderStatus::Processed->value => 8,
            OrderStatus::ReadyForPickup->value => 6,
            OrderStatus::ReadyToShip->value => 8,
            OrderStatus::Shipping->value => 10,
            OrderStatus::Completed->value => 18,
            OrderStatus::Cancelled->value => 5,
        ];

        $invoiceCounter = 1001;

        foreach ($statusList as $targetStatus => $count) {
            for ($i = 0; $i < $count; $i++) {
                $user = $customers->random();
                $store = $stores->random();
                $storeProducts = $allProducts->get($store->id);
                if (! $storeProducts || $storeProducts->isEmpty()) {
                    continue;
                }

                $address = $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->first() ?? Address::create([
                    'user_id' => $user->id,
                    'label' => 'Alamat Utama',
                    'recipient_name' => $user->name,
                    'phone' => $user->phone,
                    'full_address' => 'Jl. Pemuda No. 45, Semarang Tengah, Kota Semarang',
                    'latitude' => -6.982000,
                    'longitude' => 110.409000,
                    'is_default' => true,
                ]);

                // Waktu transaksi (dalam rentang 1-14 hari ke belakang)
                $daysAgo = rand(0, 14);
                $createdAt = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 12))->subMinutes(rand(1, 50));

                if ($targetStatus === OrderStatus::ReadyForPickup->value) {
                    $isPickup = true;
                } elseif (in_array($targetStatus, [OrderStatus::ReadyToShip->value, OrderStatus::Shipping->value], true)) {
                    $isPickup = false;
                } else {
                    $isPickup = (rand(1, 10) <= 3); // 30% pickup
                }
                $rate = $rates->random();

                // Pilih 1 - 3 produk
                $itemCount = rand(1, 3);
                $selectedProducts = $storeProducts->random(min($itemCount, $storeProducts->count()));

                $detailsData = [];
                $totalAmount = '0.00';
                $totalWeight = 0;

                foreach ($selectedProducts as $prod) {
                    $qty = rand(1, 3);
                    $price = (string) $prod->price;
                    $subtotal = bcmul($price, (string) $qty, 2);
                    $totalAmount = bcadd($totalAmount, $subtotal, 2);
                    $totalWeight += ((int) ($prod->weight ?? 1000)) * $qty;

                    $detailsData[] = [
                        'product_id' => $prod->id,
                        'product_name' => $prod->name,
                        'quantity' => $qty,
                        'price_at_transaction' => $price,
                        'subtotal' => $subtotal,
                    ];
                }

                // Ongkir
                if ($isPickup) {
                    $shippingCost = '0.00';
                    $pricingType = null;
                    $deliveryType = 'pickup';
                } else {
                    $deliveryType = 'courier';
                    $pricingType = $rate->pricing_type ?? 'per_kg';
                    if ($pricingType === 'flat') {
                        $shippingCost = (string) $rate->shipping_cost;
                    } else {
                        $kg = (int) max(1, ceil($totalWeight / 1000));
                        $shippingCost = bcmul((string) $rate->shipping_cost, (string) $kg, 2);
                    }
                }

                // Promo (30% possibility)
                $appliedPromo = null;
                $discountAmount = '0.00';
                if (rand(1, 10) <= 3) {
                    $candidatePromo = $promos->random();
                    if ($candidatePromo->is_active && bccomp($totalAmount, (string) $candidatePromo->min_purchase, 2) >= 0) {
                        $appliedPromo = $candidatePromo;
                        if ($appliedPromo->discount_type === 'percent') {
                            $calc = bcmul($totalAmount, bcdiv((string) $appliedPromo->discount_value, '100', 4), 2);
                            if ($appliedPromo->max_discount_amount) {
                                $calc = min((float) $calc, (float) $appliedPromo->max_discount_amount);
                            }
                            $discountAmount = (string) $calc;
                        } else {
                            $discountAmount = (string) min((float) $totalAmount, (float) $appliedPromo->discount_value);
                        }
                    }
                }

                // Final Amount = Total + Ongkir - Diskon
                $finalAmount = bcsub(bcadd($totalAmount, $shippingCost, 2), $discountAmount, 2);
                if (bccomp($finalAmount, '0.00', 2) < 0) {
                    $finalAmount = '0.00';
                }

                $invoice = sprintf('INV-%s-%04d', $createdAt->format('Ymd'), $invoiceCounter++);

                $parts = explode(',', $address->full_address);
                $shippingCity = trim(end($parts)) ?: $rate->city_name;

                $transaction = Transaction::create([
                    'invoice_number' => $invoice,
                    'user_id' => $user->id,
                    'store_id' => $store->id,
                    'total_amount' => $totalAmount,
                    'promo_id' => $appliedPromo?->id,
                    'discount_amount' => $discountAmount,
                    'shipping_cost' => $shippingCost,
                    'total_weight' => $totalWeight,
                    'shipping_pricing_type' => $pricingType,
                    'final_amount' => $finalAmount,
                    'status' => $targetStatus,
                    'delivery_type' => $deliveryType,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->phone,
                    'shipping_city' => $shippingCity,
                    'shipping_district' => 'Semarang Tengah',
                    'shipping_postal_code' => '50134',
                    'shipping_address' => $address->full_address,
                    'shipping_latitude' => $address->latitude ?? -6.982000,
                    'shipping_longitude' => $address->longitude ?? 110.409000,
                    'note' => rand(0, 1) ? 'Mohon dipacking rapi dan aman' : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Details
                foreach ($detailsData as $d) {
                    $transaction->details()->create($d);
                }

                // Promo Log
                if ($appliedPromo && bccomp($discountAmount, '0.00', 2) > 0) {
                    PromoLog::create([
                        'promo_id' => $appliedPromo->id,
                        'user_id' => $user->id,
                        'transaction_id' => $transaction->id,
                        'discount_amount' => $discountAmount,
                        'used_at' => $createdAt,
                        'cancelled_at' => ($targetStatus === OrderStatus::Cancelled->value) ? $createdAt->copy()->addMinutes(45) : null,
                    ]);
                    $appliedPromo->increment('used_count');
                }

                // Status Logs Progression
                $this->createStatusLogs($transaction, $targetStatus, $createdAt, $user, $admins->first());

                // Delivery Assignment & Realtime Data
                if ($deliveryType === 'courier' && in_array($targetStatus, [
                    OrderStatus::ReadyToShip->value,
                    OrderStatus::Shipping->value,
                    OrderStatus::Completed->value
                ], true)) {
                    $courier = $couriers->random();
                    $this->createDeliveryData($transaction, $targetStatus, $courier, $createdAt);
                }

                // Wallet Purchase & Refund Ledger Transactions
                if ($targetStatus !== OrderStatus::Pending->value) {
                    // Purchase debit
                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'purchase',
                        'amount' => -$finalAmount,
                        'balance_before' => $user->saldo + $finalAmount,
                        'balance_after' => $user->saldo,
                        'reference_type' => Transaction::class,
                        'reference_id' => $transaction->id,
                        'note' => 'Pembayaran pesanan ' . $transaction->invoice_number,
                        'created_at' => $createdAt->copy()->addMinutes(5),
                    ]);

                    // If cancelled, log refund credit
                    if ($targetStatus === OrderStatus::Cancelled->value) {
                        WalletTransaction::create([
                            'user_id' => $user->id,
                            'type' => 'refund',
                            'amount' => $finalAmount,
                            'balance_before' => $user->saldo,
                            'balance_after' => $user->saldo + $finalAmount,
                            'reference_type' => Transaction::class,
                            'reference_id' => $transaction->id,
                            'note' => 'Pengembalian dana pembatalan pesanan ' . $transaction->invoice_number,
                            'created_at' => $createdAt->copy()->addMinutes(30),
                        ]);
                    }
                }
            }
        }
    }

    private function createStatusLogs(Transaction $t, string $targetStatus, Carbon $start, User $customer, ?User $admin): void
    {
        $time = $start->copy();

        // Pending
        TransactionStatusLog::create([
            'transaction_id' => $t->id,
            'from_status' => null,
            'to_status' => OrderStatus::Pending->value,
            'changed_by' => $customer->id,
            'note' => 'Pesanan berhasil dibuat oleh pelanggan.',
            'created_at' => $time,
        ]);

        if ($targetStatus === OrderStatus::Pending->value) {
            return;
        }

        // Paid
        $time = $time->copy()->addMinutes(rand(5, 15));
        TransactionStatusLog::create([
            'transaction_id' => $t->id,
            'from_status' => OrderStatus::Pending->value,
            'to_status' => OrderStatus::Paid->value,
            'changed_by' => $customer->id,
            'note' => 'Pembayaran via Saldo Ramela sukses.',
            'created_at' => $time,
        ]);

        if ($targetStatus === OrderStatus::Paid->value) {
            return;
        }

        if ($targetStatus === OrderStatus::Cancelled->value) {
            $time = $time->copy()->addMinutes(rand(10, 30));
            TransactionStatusLog::create([
                'transaction_id' => $t->id,
                'from_status' => OrderStatus::Paid->value,
                'to_status' => OrderStatus::Cancelled->value,
                'changed_by' => $admin?->id ?? $customer->id,
                'note' => 'Pesanan dibatalkan atas permintaan pelanggan. Saldo dikembalikan.',
                'created_at' => $time,
            ]);
            return;
        }

        // Processed
        $time = $time->copy()->addMinutes(rand(10, 25));
        TransactionStatusLog::create([
            'transaction_id' => $t->id,
            'from_status' => OrderStatus::Paid->value,
            'to_status' => OrderStatus::Processed->value,
            'changed_by' => $admin?->id,
            'note' => 'Pesanan diterima toko dan sedang dipersiapkan.',
            'created_at' => $time,
        ]);

        if ($targetStatus === OrderStatus::Processed->value) {
            return;
        }

        // Khusus alur Pickup: Processed -> ReadyForPickup -> Completed
        if ($t->delivery_type === 'pickup') {
            $time = $time->copy()->addMinutes(rand(20, 45));
            TransactionStatusLog::create([
                'transaction_id' => $t->id,
                'from_status' => OrderStatus::Processed->value,
                'to_status' => OrderStatus::ReadyForPickup->value,
                'changed_by' => $admin?->id,
                'note' => 'Pesanan telah selesai dipersiapkan toko dan siap dijemput oleh pelanggan.',
                'created_at' => $time,
            ]);

            if ($targetStatus === OrderStatus::ReadyForPickup->value) {
                return;
            }

            $time = $time->copy()->addMinutes(rand(30, 90));
            TransactionStatusLog::create([
                'transaction_id' => $t->id,
                'from_status' => OrderStatus::ReadyForPickup->value,
                'to_status' => OrderStatus::Completed->value,
                'changed_by' => $admin?->id,
                'note' => 'Pesanan telah diambil langsung oleh pelanggan di toko. Transaksi selesai.',
                'created_at' => $time,
            ]);

            return;
        }

        // Alur Kurir: Processed -> ReadyToShip -> Shipping -> Completed
        $time = $time->copy()->addMinutes(rand(20, 45));
        TransactionStatusLog::create([
            'transaction_id' => $t->id,
            'from_status' => OrderStatus::Processed->value,
            'to_status' => OrderStatus::ReadyToShip->value,
            'changed_by' => $admin?->id,
            'note' => 'Barang selesai dikemas, menunggu kurir menjemput pesanan.',
            'created_at' => $time,
        ]);

        if ($targetStatus === OrderStatus::ReadyToShip->value) {
            return;
        }

        // Shipping
        $time = $time->copy()->addMinutes(rand(15, 30));
        TransactionStatusLog::create([
            'transaction_id' => $t->id,
            'from_status' => OrderStatus::ReadyToShip->value,
            'to_status' => OrderStatus::Shipping->value,
            'changed_by' => $admin?->id,
            'note' => 'Paket telah diambil kurir dan sedang dalam perjalanan menuju tujuan.',
            'created_at' => $time,
        ]);

        if ($targetStatus === OrderStatus::Shipping->value) {
            return;
        }

        // Completed
        $time = $time->copy()->addMinutes(rand(30, 90));
        TransactionStatusLog::create([
            'transaction_id' => $t->id,
            'from_status' => OrderStatus::Shipping->value,
            'to_status' => OrderStatus::Completed->value,
            'changed_by' => $admin?->id,
            'note' => 'Paket berhasil diterima oleh pemesan. Transaksi selesai.',
            'created_at' => $time,
        ]);
    }

    private function createDeliveryData(Transaction $t, string $status, User $courier, Carbon $start): void
    {
        $deliveryStatus = match ($status) {
            OrderStatus::ReadyToShip->value => 'waiting_pickup',
            OrderStatus::Shipping->value => 'en_route',
            OrderStatus::Completed->value => 'delivered',
            default => 'waiting_pickup',
        };

        // Koordinat tujuan & asal toko di area Semarang
        $destLat = (float) ($t->shipping_latitude ?: -6.992440);
        $destLng = (float) ($t->shipping_longitude ?: 110.428450);

        $curLat = $destLat + (rand(-100, 100) / 10000.0);
        $curLng = $destLng + (rand(-100, 100) / 10000.0);

        $delivery = Delivery::create([
            'transaction_id' => $t->id,
            'courier_id' => $courier->id,
            'status' => $deliveryStatus,
            'current_lat' => $curLat,
            'current_lng' => $curLng,
            'location_updated_at' => Carbon::now()->subMinutes(rand(5, 30)),
            'started_at' => $start->copy()->addMinutes(rand(30, 60)),
            'completed_at' => ($status === OrderStatus::Completed->value) ? $start->copy()->addMinutes(rand(90, 180)) : null,
        ]);

        // Lokasi breadcrumbs
        if (in_array($status, [OrderStatus::Shipping->value, OrderStatus::Completed->value], true)) {
            for ($step = 1; $step <= 3; $step++) {
                DeliveryLocation::create([
                    'delivery_id' => $delivery->id,
                    'latitude' => $curLat - ($step * 0.002),
                    'longitude' => $curLng - ($step * 0.002),
                    'recorded_at' => $start->copy()->addMinutes($step * 15),
                ]);
            }
        }

        // Foto Validasi
        if (in_array($status, [OrderStatus::Shipping->value, OrderStatus::Completed->value], true)) {
            DeliveryPhoto::create([
                'delivery_id' => $delivery->id,
                'type' => 'pickup',
                'path' => 'delivery/sample_pickup.jpg',
                'latitude' => $curLat,
                'longitude' => $curLng,
                'taken_at' => $start->copy()->addMinutes(45),
            ]);
        }

        if ($status === OrderStatus::Completed->value) {
            DeliveryPhoto::create([
                'delivery_id' => $delivery->id,
                'type' => 'dropoff',
                'path' => 'delivery/sample_dropoff.jpg',
                'latitude' => $destLat,
                'longitude' => $destLng,
                'taken_at' => $start->copy()->addMinutes(120),
            ]);
        }
    }
}
