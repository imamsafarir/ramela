<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::with('store:id,name', 'category:id,name')
            ->when($request->query('store'), fn ($q, $s) => $q->where('store_id', $s))
            ->when($request->query('q'), fn ($q, $t) => $q->where('name', 'like', '%'.addcslashes($t, '%_\\').'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'store' => $p->store->name,
                'category' => $p->category?->name, 'price' => $p->price, 'stock' => $p->stock,
                'unit' => $p->unit, 'weight' => $p->weight, 'is_active' => $p->is_active,
            ]);

        return Inertia::render('Admin/Products', [
            'products' => $products,
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'filters' => $request->only('store', 'q'),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(Product $product): Response
    {
        return $this->form($product->load('images'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $product = Product::create($data + ['slug' => $this->uniqueSlug($data['name'])]);
        $this->storeImages($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Produk ditambahkan.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);
        $product->update($data);

        $remove = $product->images()->whereIn('id', (array) $request->input('remove_images', []))->get();
        foreach ($remove as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
        $this->storeImages($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Produk diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete(); // soft delete: riwayat transaksi tetap utuh

        return back()->with('success', 'Produk dihapus.');
    }

    private function form(?Product $product): Response
    {
        return Inertia::render('Admin/ProductForm', [
            'product' => $product ? [
                ...$product->only('id', 'store_id', 'category_id', 'name', 'description', 'price', 'stock', 'unit', 'weight', 'is_active'),
                'images' => $product->images->map(fn ($i) => ['id' => $i->id, 'url' => asset('storage/'.$i->path)]),
            ] : null,
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'store_id', 'name']),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('store_id', $request->input('store_id'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit' => ['nullable', 'string', 'max:20'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'weight_unit' => ['nullable', 'string', 'in:g,kg'],
            'is_active' => ['required', 'boolean'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // SVG sengaja tidak diizinkan (XSS)
        ]);

        $rawWeight = $data['weight'] ?? null;
        $weightUnit = $data['weight_unit'] ?? 'g';
        if ($rawWeight !== null && (float) $rawWeight > 0) {
            $data['weight'] = $weightUnit === 'kg'
                ? (int) round(((float) $rawWeight) * 1000)
                : (int) round((float) $rawWeight);
        } else {
            $data['weight'] = 1000;
        }

        unset($data['weight_unit']);
        unset($data['images']);

        return $data;
    }

    private function storeImages(Request $request, Product $product): void
    {
        $order = (int) $product->images()->max('sort_order');

        foreach ($request->file('images', []) as $file) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $file->store('products', 'public'),
                'sort_order' => ++$order,
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        do {
            $slug = Str::slug($name).'-'.Str::lower(Str::random(5));
        } while (Product::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }
}
