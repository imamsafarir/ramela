<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function show(Request $request, Store $store): Response
    {
        abort_unless($store->is_active, 404);

        $products = $store->products()
            ->where('is_active', true)
            ->when($request->query('category'), fn($q, $c) => $q->where('category_id', $c))
            ->when($request->query('q'), fn($q, $term) => $q->where('name', 'like', '%' . addcslashes($term, '%_\\') . '%'))
            ->with('images:id,product_id,path')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $p->price,
                'stock' => $p->stock,
                'unit' => $p->unit,
                'image' => $p->images->first() ? asset('storage/' . $p->images->first()->path) : null,
            ]);

        return Inertia::render('User/Store', [
            'store' => $store->only('slug', 'name', 'tagline'),
            'categories' => $store->categories()->orderBy('sort_order')->get(['id', 'name']),
            'products' => $products,
            'filters' => $request->only('q', 'category'),
        ]);
    }
}
