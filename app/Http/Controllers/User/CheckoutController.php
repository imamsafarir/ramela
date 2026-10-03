<?php

namespace App\Http\Controllers\User;

use App\Actions\CheckoutAction;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(Request $request, Store $store, \App\Services\PromoService $promos): Response|RedirectResponse
    {
        $user = $request->user();

        $items = $user->cartItems()
            ->whereHas('product', fn($q) => $q->where('store_id', $store->id))
            ->with('product:id,name,price,unit,store_id')
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $subtotal = $items->reduce(
            fn($c, $i) => bcadd($c, bcmul((string) $i->product->price, (string) $i->quantity, 2), 2),
            '0.00'
        );
        $promo = null;
        $promoError = null;
        if ($code = $request->query('promo')) {
            try {
                $r = $promos->evaluate($code, $user, $store, $subtotal);
                $promo = ['code' => $r['promo']->code ?? $promos->normalize($code), 'discount' => $r['discount']];
            } catch (CheckoutException $e) {
                $promoError = $e->getMessage();
            }
        }

        $shippingRates = \App\Models\ShippingRate::where('is_active', true)
            ->orderBy('city_name')
            ->get(['id', 'city_name', 'shipping_cost', 'estimated_delivery']);

        return Inertia::render('User/Checkout', [
            'promo' => $promo,
            'promoError' => $promoError,
            'store' => $store->only('slug', 'name'),
            'shippingRates' => $shippingRates,
            'items' => $items->map(fn($i) => [
                'name' => $i->product->name,
                'quantity' => $i->quantity,
                'price' => $i->product->price,
                'subtotal' => bcmul((string) $i->product->price, (string) $i->quantity, 2),
            ]),
            'total' => $items->reduce(
                fn($c, $i) => bcadd($c, bcmul((string) $i->product->price, (string) $i->quantity, 2), 2),
                '0.00'
            ),
            'defaults' => [
                'recipient_name' => $user->name ?? '',
                'recipient_phone' => $user->phone ?? '',
            ],
        ]);
    }

    public function store(Request $request, Store $store, CheckoutAction $checkout): RedirectResponse
    {
        $rules = [
            'delivery_type' => ['nullable', 'string', 'in:pickup,courier'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'note' => ['nullable', 'string', 'max:500'],
            'promo_code' => ['nullable', 'string', 'max:50'],
        ];

        if ($request->input('delivery_type') === 'courier' || (! $request->has('delivery_type') && $request->filled('shipping_rate_id'))) {
            $rules['shipping_rate_id'] = ['required', 'exists:shipping_rates,id'];
            $rules['shipping_district'] = ['required', 'string', 'max:255'];
            $rules['shipping_postal_code'] = ['required', 'string', 'max:20'];
            $rules['shipping_address'] = ['required', 'string', 'max:1000'];
            $rules['shipping_latitude'] = ['nullable', 'numeric', 'between:-90,90'];
            $rules['shipping_longitude'] = ['nullable', 'numeric', 'between:-180,180'];
        } else {
            $rules['shipping_address'] = ['nullable', 'string', 'max:1000'];
        }

        $messages = [
            'delivery_type.in' => 'Pilihan metode pengiriman tidak valid.',
            'recipient_name.required' => 'Nama penerima / pengambil wajib diisi.',
            'recipient_phone.required' => 'Nomor WhatsApp / telepon wajib diisi.',
            'recipient_phone.regex' => 'Format nomor WhatsApp / telepon tidak valid.',
            'shipping_rate_id.required' => 'Silakan pilih Kabupaten / Kota pengiriman.',
            'shipping_rate_id.exists' => 'Kabupaten / Kota yang dipilih tidak valid.',
            'shipping_district.required' => 'Kecamatan / Kelurahan wajib diisi.',
            'shipping_postal_code.required' => 'Kode Pos wajib diisi.',
            'shipping_address.required' => 'Detail alamat lengkap pengiriman wajib diisi.',
        ];

        $data = $request->validate($rules, $messages);

        try {
            $transaction = $checkout->execute($request->user(), $store, $data);
        } catch (CheckoutException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $transaction->invoice_number)
            ->with('success', 'Pesanan berhasil dibuat dan dibayar.');
    }
}
