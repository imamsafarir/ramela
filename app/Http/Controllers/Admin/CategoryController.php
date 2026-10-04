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
    public function index(Request $request): Response
    {
        return app(ProductController::class)->index($request->merge(['tab' => 'categories']));
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
