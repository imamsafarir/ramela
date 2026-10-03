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

        return Inertia::render('User/Checkout', [
            'promo' => $promo,
            'promoError' => $promoError,
            'store' => $store->only('slug', 'name'),
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
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'shipping_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'shipping_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:500'],
            'promo_code' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $transaction = $checkout->execute($request->user(), $store, $data);
        } catch (CheckoutException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $transaction->invoice_number)
            ->with('success', 'Pesanan berhasil dibuat dan dibayar.');
    }
}
