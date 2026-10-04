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
        $q = $request->query('q');
        $storeId = $request->query('store');
        $categoryId = $request->query('category');
        $status = $request->query('status');
        $stockStatus = $request->query('stock_status');
        $sort = in_array($request->query('sort'), ['name', 'price', 'stock', 'weight', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $products = Product::with(['store:id,name', 'category:id,name'])
            ->when($storeId, fn ($query, $s) => $query->where('store_id', $s))
            ->when($categoryId, fn ($query, $c) => $query->where('category_id', $c))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($stockStatus === 'low', fn ($query) => $query->where('stock', '<=', 5)->where('stock', '>', 0))
            ->when($stockStatus === 'empty', fn ($query) => $query->where('stock', 0))
            ->when($stockStatus === 'available', fn ($query) => $query->where('stock', '>', 5))
            ->when($q, function ($query, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                      ->orWhere('description', 'like', $like);
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'store' => $p->store->name,
                'store_id' => $p->store_id,
                'category' => $p->category?->name,
                'category_id' => $p->category_id,
                'price' => $p->price,
                'stock' => $p->stock,
                'unit' => $p->unit,
                'weight' => $p->weight,
                'is_active' => $p->is_active,
                'created_at' => $p->created_at?->format('d M Y H:i'),
            ]);

        $categories = Category::withCount('products')
            ->with('store:id,name')
            ->orderBy('store_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'store_id' => $c->store_id,
                'name' => $c->name,
                'store' => $c->store->name,
                'products_count' => $c->products_count,
            ]);

        $stores = Store::orderBy('sort_order')->get(['id', 'name']);

        $stats = [
            'total_products' => Product::count(),
            'total_categories' => Category::count(),
            'low_stock' => Product::where('is_active', true)->where('stock', '<=', 5)->count(),
            'inactive' => Product::where('is_active', false)->count(),
        ];

        return Inertia::render('Admin/Products', [
            'products' => $products,
            'categories' => $categories,
            'stores' => $stores,
            'stats' => $stats,
            'filters' => [
                'q' => $q ?? '',
                'store' => $storeId ?? '',
                'category' => $categoryId ?? '',
                'status' => $status ?? '',
                'stock_status' => $stockStatus ?? '',
                'sort' => $sort,
                'dir' => $dir,
                'tab' => $request->query('tab', 'products'),
            ],
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
