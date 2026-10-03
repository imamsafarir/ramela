<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        $items = $request->user()->cartItems()
            ->with(['product' => fn($q) => $q->with('store:id,slug,name')])
            ->get()
            ->filter(fn($i) => $i->product) // produk terhapus tak ditampilkan
            ->groupBy(fn($i) => $i->product->store_id)
            ->map(fn($group) => [
                'store' => $group->first()->product->store->only('slug', 'name'),
                'subtotal' => $group->reduce(
                    fn($c, $i) => bcadd($c, bcmul((string) $i->product->price, (string) $i->quantity, 2), 2),
                    '0.00'
                ),
                'items' => $group->map(fn($i) => [
                    'id' => $i->id,
                    'quantity' => $i->quantity,
                    'name' => $i->product->name,
                    'price' => $i->product->price,
                    'stock' => $i->product->stock,
                    'unit' => $i->product->unit,
                    'available' => $i->product->is_active && $i->product->stock > 0,
                ])->values(),
            ])->values();

        return Inertia::render('User/Cart', ['groups' => $items]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::where('is_active', true)->whereHas('store', fn($q) => $q->where('is_active', true))
            ->findOrFail($data['product_id']);

        $item = CartItem::firstOrNew(['user_id' => $request->user()->id, 'product_id' => $product->id]);
        $item->quantity = min(($item->quantity ?? 0) + $data['quantity'], max($product->stock, 1));
        $item->save();

        return back()->with('success', "\"{$product->name}\" masuk keranjang.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:999']]);

        $item = $request->user()->cartItems()->with('product')->findOrFail($id);
        $item->update(['quantity' => min($data['quantity'], max($item->product->stock, 1))]);

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->cartItems()->findOrFail($id)->delete();

        return back();
    }
}
