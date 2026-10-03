<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Categories', [
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'categories' => Category::withCount('products')->with('store:id,name')->orderBy('store_id')->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'store' => $c->store->name, 'products_count' => $c->products_count]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $slug = Str::slug($data['name']);

        $request->validate([
            'name' => [Rule::unique('categories', 'name')->where('store_id', $data['store_id'])],
        ], ['name.unique' => 'Kategori ini sudah ada di toko tersebut.']);

        Category::create($data + ['slug' => $slug ?: Str::random(6)]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete(); // products.category_id -> NULL (nullOnDelete)

        return back()->with('success', 'Kategori dihapus.');
    }
}
