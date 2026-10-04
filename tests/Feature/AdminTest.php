<?php

use App\Actions\CheckoutAction;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
    $this->eats = Store::create(['slug' => 'eats', 'name' => 'RAMELA EATS'])->refresh();
    $this->beton = Store::create(['slug' => 'beton', 'name' => 'RAMELA BETON'])->refresh();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->customer = User::factory()->create();
    $this->customer->assignRole('pengguna');
    $this->customer->refresh();
});

function placeOrder(User $customer, Store $store, int $price = 25000, int $qty = 2, int $stock = 10, string $deliveryType = 'pickup'): Transaction
{
    $rate = \App\Models\ShippingRate::firstOrCreate(
        ['city_name' => 'Semarang'],
        ['shipping_cost' => 10000, 'pricing_type' => 'flat', 'is_active' => true]
    );

    $product = Product::create([
        'store_id' => $store->id, 'name' => 'Item '.uniqid(), 'slug' => 'i-'.uniqid(), 'price' => $price, 'stock' => $stock,
    ]);
    $shippingCost = ($deliveryType === 'courier') ? 10000 : 0;
    app(WalletService::class)->credit($customer, ($price * $qty) + $shippingCost, 'topup');
    CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => $qty]);

    $shippingData = [
        'delivery_type' => $deliveryType,
        'recipient_name' => 'Budi',
        'recipient_phone' => '08123456789',
        'shipping_address' => 'Jl. Mawar 1',
    ];

    if ($deliveryType === 'courier') {
        $shippingData['shipping_rate_id'] = $rate->id;
        $shippingData['shipping_district'] = 'Semarang Tengah';
        $shippingData['shipping_postal_code'] = '50134';
    }

    return app(CheckoutAction::class)->execute($customer->refresh(), $store, $shippingData);
}

test('hanya admin yang bisa membuka panel admin', function () {
    $this->actingAs($this->customer)->get('/admin')->assertNotFound();
    $this->actingAs($this->customer)->get('/admin/produk')->assertNotFound();
    $this->actingAs($this->customer)->post('/admin/produk', [])->assertNotFound();
    $this->actingAs($this->admin)->get('/admin')->assertOk();
    $this->actingAs($this->admin)->get('/admin/produk')->assertOk();
});

test('admin menambah produk dengan foto', function () {
    Storage::fake('public');
    $cat = Category::create(['store_id' => $this->eats->id, 'name' => 'Makanan', 'slug' => 'makanan']);

    $this->actingAs($this->admin)->post('/admin/produk', [
        'store_id' => $this->eats->id, 'category_id' => $cat->id, 'name' => 'Nasi Goreng', 'price' => 25000,
        'stock' => 10, 'is_active' => true, 'images' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertSessionHasNoErrors()->assertRedirect('/admin/produk');

    $p = Product::first();
    expect($p->name)->toBe('Nasi Goreng')->and($p->slug)->toStartWith('nasi-goreng-');
    expect($p->images)->toHaveCount(1);
    Storage::disk('public')->assertExists($p->images->first()->path);
});

test('kategori harus milik toko yang sama dan file harus gambar valid', function () {
    Storage::fake('public');
    $catBeton = Category::create(['store_id' => $this->beton->id, 'name' => 'Semen', 'slug' => 'semen']);
    $base = ['store_id' => $this->eats->id, 'name' => 'X', 'price' => 1000, 'stock' => 1, 'is_active' => true];

    $this->actingAs($this->admin)->post('/admin/produk', $base + ['category_id' => $catBeton->id])
        ->assertSessionHasErrors('category_id');
    $this->actingAs($this->admin)->post('/admin/produk', $base + ['images' => [UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')]])
        ->assertSessionHasErrors('images.0');
    $this->actingAs($this->admin)->post('/admin/produk', $base + ['images' => [UploadedFile::fake()->create('big.jpg', 5000, 'image/jpeg')]])
        ->assertSessionHasErrors('images.0');
    $this->actingAs($this->admin)->post('/admin/produk', array_merge($base, ['price' => -5]))->assertSessionHasErrors('price');

    expect(Product::count())->toBe(0);
});

test('admin mengubah produk dan menghapus foto lama', function () {
    Storage::fake('public');
    $this->actingAs($this->admin)->post('/admin/produk', [
        'store_id' => $this->eats->id, 'name' => 'Teh', 'price' => 5000, 'stock' => 5, 'is_active' => true,
        'images' => [UploadedFile::fake()->image('a.jpg')],
    ]);
    $product = Product::first();
    $image = $product->images->first();

    $this->actingAs($this->admin)->post("/admin/produk/{$product->id}", [
        '_method' => 'put', 'store_id' => $this->eats->id, 'name' => 'Teh Manis', 'price' => 6000, 'stock' => 8,
        'is_active' => true, 'remove_images' => [$image->id],
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->name)->toBe('Teh Manis')->and($product->images()->count())->toBe(0);
    Storage::disk('public')->assertMissing($image->path);
});

test('menghapus produk tidak merusak riwayat pesanan', function () {
    $order = placeOrder($this->customer, $this->eats);
    $productId = $order->details->first()->product_id;

    $this->actingAs($this->admin)->delete("/admin/produk/{$productId}")->assertSessionHasNoErrors();

    expect(Product::find($productId))->toBeNull()
        ->and(Product::withTrashed()->find($productId))->not->toBeNull()
        ->and($order->fresh()->details->first()->product_name)->not->toBeEmpty();
    $this->actingAs($this->customer)->get("/pesanan/{$order->invoice_number}")->assertOk();
});

test('alur status: Dibayar -> Diproses -> Siap Dikirim, tercatat siapa yang mengubah', function () {
    $order = placeOrder($this->customer, $this->eats, deliveryType: 'courier');

    $this->actingAs($this->admin)->patch("/admin/pesanan/{$order->invoice_number}/status", ['status' => 'processed'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->patch("/admin/pesanan/{$order->invoice_number}/status", ['status' => 'ready_to_ship'])
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::ReadyToShip);
    $log = $order->statusLogs()->latest('id')->first();
    expect($log->from_status)->toBe('processed')->and($log->to_status)->toBe('ready_to_ship')->and($log->changed_by)->toBe($this->admin->id);
});

test('alur status pesanan pickup: Dibayar -> Diproses -> Siap Dijemput -> Selesai', function () {
    $order = placeOrder($this->customer, $this->eats, deliveryType: 'pickup');

    // 1. Admin memproses pesanan
    $this->actingAs($this->admin)->patch("/admin/pesanan/{$order->invoice_number}/status", ['status' => 'processed'])
        ->assertSessionHasNoErrors();

    // 2. Untuk pickup, admin TIDAK BISA menandai siap dikirim (harus gagal)
    $this->actingAs($this->admin)->patch("/admin/pesanan/{$order->invoice_number}/status", ['status' => 'ready_to_ship'])
        ->assertSessionHasErrors('status');

    // 3. Admin menandai siap dijemput
    $this->actingAs($this->admin)->patch("/admin/pesanan/{$order->invoice_number}/status", ['status' => 'ready_for_pickup'])
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::ReadyForPickup);
    $log = $order->statusLogs()->latest('id')->first();
    expect($log->from_status)->toBe('processed')
        ->and($log->to_status)->toBe('ready_for_pickup')
        ->and($log->changed_by)->toBe($this->admin->id);

    // 4. Saat pelanggan datang mengambil pesanan, admin menandai selesai
    $this->actingAs($this->admin)->patch("/admin/pesanan/{$order->invoice_number}/status", ['status' => 'completed'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    $finalLog = $order->statusLogs()->latest('id')->first();
    expect($finalLog->from_status)->toBe('ready_for_pickup')
        ->and($finalLog->to_status)->toBe('completed')
        ->and($finalLog->changed_by)->toBe($this->admin->id);
});

test('admin tidak bisa melompati status atau mengatur status milik kurir', function () {
    $order = placeOrder($this->customer, $this->eats, deliveryType: 'courier');
    $url = "/admin/pesanan/{$order->invoice_number}/status";

    $this->actingAs($this->admin)->patch($url, ['status' => 'ready_to_ship'])->assertSessionHasErrors('status'); // lompat
    $this->actingAs($this->admin)->patch($url, ['status' => 'completed'])->assertSessionHasErrors('status');   // milik kurir
    $this->actingAs($this->admin)->patch($url, ['status' => 'shipping'])->assertSessionHasErrors('status');
    $this->actingAs($this->admin)->patch($url, ['status' => 'ngawur'])->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('pembatalan me-refund saldo dan memulihkan stok, hanya sekali', function () {
    $order = placeOrder($this->customer, $this->eats, price: 25000, qty: 2, stock: 10);
    $product = Product::find($order->details->first()->product_id);
    expect($this->customer->fresh()->saldo)->toBe('0.00')->and($product->stock)->toBe(8);

    $url = "/admin/pesanan/{$order->invoice_number}/status";
    $this->actingAs($this->admin)->patch($url, ['status' => 'cancelled', 'note' => 'Stok habis di dapur'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->patch($url, ['status' => 'cancelled'])->assertSessionHasErrors('status'); // dobel klik

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->customer->fresh()->saldo)->toBe('50000.00')
        ->and($product->fresh()->stock)->toBe(10)
        ->and(WalletTransaction::where('type', 'refund')->count())->toBe(1);

    $refund = WalletTransaction::where('type', 'refund')->first();
    expect($refund->amount)->toBe('50000.00')->and($refund->created_by)->toBe($this->admin->id)->and($refund->reference_id)->toBe($order->id);
});

test('filter, cari, dan urutkan daftar pesanan', function () {
    $a = placeOrder($this->customer, $this->eats, price: 10000, qty: 1);
    $b = placeOrder($this->customer, $this->beton, price: 900000, qty: 1);
    app(\App\Actions\ChangeOrderStatus::class)->execute($b, OrderStatus::Processed, $this->admin);

    $get = fn (string $qs) => $this->actingAs($this->admin)->get('/admin/pesanan?'.$qs);

    $get('store='.$this->beton->id)->assertInertia(fn ($p) => $p->has('orders.data', 1)->where('orders.data.0.invoice_number', $b->invoice_number));
    $get('status=processed')->assertInertia(fn ($p) => $p->has('orders.data', 1));
    $get('status=paid')->assertInertia(fn ($p) => $p->has('orders.data', 1)->where('orders.data.0.invoice_number', $a->invoice_number));
    $get('q='.$this->customer->username)->assertInertia(fn ($p) => $p->has('orders.data', 2));
    $get('q=%25')->assertInertia(fn ($p) => $p->has('orders.data', 0)); // wildcard di-escape
    $get('today=1')->assertInertia(fn ($p) => $p->has('orders.data', 2));
    $get('sort=final_amount&dir=desc')->assertInertia(fn ($p) => $p->where('orders.data.0.invoice_number', $b->invoice_number));
    $get('sort=final_amount&dir=asc')->assertInertia(fn ($p) => $p->where('orders.data.0.invoice_number', $a->invoice_number));
    $get('sort=password')->assertOk(); // kolom tak dikenal jatuh ke default
});

test('kategori: tidak boleh duplikat dalam satu toko, hapus membuat produk tanpa kategori', function () {
    $this->actingAs($this->admin)->post('/admin/kategori', ['store_id' => $this->eats->id, 'name' => 'Minuman'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post('/admin/kategori', ['store_id' => $this->eats->id, 'name' => 'Minuman'])->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->post('/admin/kategori', ['store_id' => $this->beton->id, 'name' => 'Minuman'])->assertSessionHasNoErrors();

    $cat = Category::where('store_id', $this->eats->id)->first();
    $p = Product::create(['store_id' => $this->eats->id, 'category_id' => $cat->id, 'name' => 'Es', 'slug' => 'es', 'price' => 1, 'stock' => 1]);

    $this->actingAs($this->admin)->get('/admin/kategori')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Products')
            ->has('categories', 2));

    $this->actingAs($this->admin)->delete("/admin/kategori/{$cat->id}");

    expect($p->fresh()->category_id)->toBeNull();
});
