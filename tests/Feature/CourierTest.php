<?php

use App\Actions\ChangeOrderStatus;
use App\Actions\CheckoutAction;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\DeliveryPhoto;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
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

    $this->store = Store::create(['slug' => 'eats', 'name' => 'RAMELA EATS'])->refresh();

    $this->product = Product::create([
        'store_id' => $this->store->id, 'name' => 'Paket Ayam', 'slug' => 'paket-ayam', 'price' => 30000, 'stock' => 10,
    ]);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('pengguna');
    $this->customer->refresh();
    app(WalletService::class)->credit($this->customer, 100000, 'topup');

    $this->courier = User::factory()->create();
    $this->courier->assignRole('kurir');
    $this->courier->refresh();

    $this->courier2 = User::factory()->create();
    $this->courier2->assignRole('kurir');
    $this->courier2->refresh();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->refresh();
});

function orderReady(User $customer, Store $store, Product $product, User $admin): Transaction
{
    $rate = \App\Models\ShippingRate::firstOrCreate(
        ['city_name' => 'Jakarta Selatan'],
        ['shipping_cost' => 15000, 'pricing_type' => 'flat', 'is_active' => true]
    );

    app(WalletService::class)->credit($customer, 15000, 'topup');

    CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);
    $t = app(CheckoutAction::class)->execute($customer, $store, [
        'delivery_type' => 'courier',
        'shipping_rate_id' => $rate->id,
        'shipping_district' => 'Kebayoran Baru',
        'shipping_postal_code' => '12110',
        'recipient_name' => 'Budi',
        'recipient_phone' => '08123456789',
        'shipping_address' => 'Jl. Kebon Jeruk No 1',
        'shipping_latitude' => -6.2088,
        'shipping_longitude' => 106.8456,
    ]);

    $changer = app(ChangeOrderStatus::class);
    $changer->execute($t, OrderStatus::Processed, $admin);
    return $changer->execute($t->fresh(), OrderStatus::ReadyToShip, $admin);
}

test('hanya user role kurir yang bisa mengakses panel kurir', function () {
    $this->actingAs($this->customer)->get('/kurir')->assertNotFound();
    $this->actingAs($this->admin)->get('/kurir')->assertNotFound();
    $this->actingAs($this->courier)->get('/kurir')->assertOk();
});

test('kurir bisa melihat pesanan siap dikirim dan mengambil tugas (claim)', function () {
    $t = orderReady($this->customer, $this->store, $this->product, $this->admin);

    $response = $this->actingAs($this->courier)->get('/kurir');
    $response->assertOk();

    // Kurir klaim tugas
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t->invoice_number}/ambil")
        ->assertSessionHasNoErrors();

    $delivery = Delivery::where('transaction_id', $t->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->courier_id)->toBe($this->courier->id)
        ->and($delivery->status)->toBe('waiting_pickup');

    // Kurir lain tidak bisa klaim order yang sudah diambil
    $this->actingAs($this->courier2)->post("/kurir/tugas/{$t->invoice_number}/ambil")
        ->assertSessionHasErrors('error');
});

test('kurir tidak bisa mengambil tugas baru jika masih memiliki tugas aktif', function () {
    $t1 = orderReady($this->customer, $this->store, $this->product, $this->admin);
    $t2 = orderReady($this->customer, $this->store, $this->product, $this->admin);

    $this->actingAs($this->courier)->post("/kurir/tugas/{$t1->invoice_number}/ambil")->assertSessionHasNoErrors();
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t2->invoice_number}/ambil")->assertSessionHasErrors('error');
});

test('alur pickup foto: status berubah jadi shipping, status order terupdate', function () {
    Storage::fake('public');
    $t = orderReady($this->customer, $this->store, $this->product, $this->admin);
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t->invoice_number}/ambil");

    $photo = UploadedFile::fake()->image('pickup.jpg');

    $this->actingAs($this->courier)->post('/kurir/pickup', [
        'photo' => $photo,
        'latitude' => -6.2100,
        'longitude' => 106.8400,
    ])->assertSessionHasNoErrors();

    $t->refresh();
    $delivery = $t->delivery->fresh();

    expect($t->status)->toBe(OrderStatus::Shipping)
        ->and($delivery->status)->toBe('en_route')
        ->and(DeliveryPhoto::where('delivery_id', $delivery->id)->where('type', 'pickup')->exists())->toBeTrue()
        ->and(DeliveryLocation::where('delivery_id', $delivery->id)->count())->toBe(1);
});

test('kurir sinkronisasi lokasi realtime GPS saat en_route', function () {
    Storage::fake('public');
    $t = orderReady($this->customer, $this->store, $this->product, $this->admin);
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t->invoice_number}/ambil");
    $this->actingAs($this->courier)->post('/kurir/pickup', [
        'photo' => UploadedFile::fake()->image('pickup.jpg'),
    ]);

    $this->actingAs($this->courier)->postJson('/kurir/location', [
        'latitude' => -6.2150,
        'longitude' => 106.8450,
    ])->assertOk()->assertJson(['status' => 'ok']);

    $delivery = $t->fresh()->delivery->fresh();
    expect($delivery->current_lat)->toBe(-6.215)
        ->and($delivery->current_lng)->toBe(106.845)
        ->and(DeliveryLocation::where('delivery_id', $delivery->id)->count())->toBe(1);
});

test('alur dropoff foto: status pesanan completed, tugas pengantaran selesai', function () {
    Storage::fake('public');
    $t = orderReady($this->customer, $this->store, $this->product, $this->admin);
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t->invoice_number}/ambil");
    $this->actingAs($this->courier)->post('/kurir/pickup', [
        'photo' => UploadedFile::fake()->image('pickup.jpg'),
    ]);

    $dropoffPhoto = UploadedFile::fake()->image('dropoff.jpg');
    $this->actingAs($this->courier)->post('/kurir/dropoff', [
        'photo' => $dropoffPhoto,
        'latitude' => -6.2200,
        'longitude' => 106.8500,
    ])->assertSessionHasNoErrors();

    $t->refresh();
    $delivery = $t->delivery->fresh();

    expect($t->status)->toBe(OrderStatus::Completed)
        ->and($delivery->status)->toBe('delivered')
        ->and($delivery->completed_at)->not->toBeNull()
        ->and(DeliveryPhoto::where('delivery_id', $delivery->id)->where('type', 'dropoff')->exists())->toBeTrue();

    // Sekarang kurir sudah bebas dan bisa mengambil tugas baru
    $t3 = orderReady($this->customer, $this->store, $this->product, $this->admin);
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t3->invoice_number}/ambil")->assertSessionHasNoErrors();
});

test('pelanggan dan admin bisa melihat data tracking kurir dan foto validasi', function () {
    Storage::fake('public');
    $t = orderReady($this->customer, $this->store, $this->product, $this->admin);
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t->invoice_number}/ambil");
    $this->actingAs($this->courier)->post('/kurir/pickup', [
        'photo' => UploadedFile::fake()->image('pickup.jpg'),
        'latitude' => -6.2100,
        'longitude' => 106.8400,
    ]);

    // Halaman OrderShow pelanggan
    $resCustomer = $this->actingAs($this->customer)->get("/pesanan/{$t->invoice_number}");
    $resCustomer->assertOk();

    // Halaman OrderShow admin
    $resAdmin = $this->actingAs($this->admin)->get("/admin/pesanan/{$t->invoice_number}");
    $resAdmin->assertOk();
});

test('kurir bisa melepas tugas yang belum di-pickup kembali ke antrean', function () {
    $t = orderReady($this->customer, $this->store, $this->product, $this->admin);

    // Kurir ambil tugas
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t->invoice_number}/ambil")
        ->assertSessionHasNoErrors();

    // Kurir lepas tugas
    $this->actingAs($this->courier)->post("/kurir/tugas/{$t->invoice_number}/lepas")
        ->assertSessionHasNoErrors();

    // courier_id kembali null
    expect(Delivery::where('transaction_id', $t->id)->value('courier_id'))->toBeNull();

    // Kurir 2 sekarang bisa mengambil tugas ini
    $this->actingAs($this->courier2)->post("/kurir/tugas/{$t->invoice_number}/ambil")
        ->assertSessionHasNoErrors();

    expect(Delivery::where('transaction_id', $t->id)->value('courier_id'))->toBe($this->courier2->id);
});

