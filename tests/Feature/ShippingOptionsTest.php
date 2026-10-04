<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }

    $this->user = User::factory()->create([
        'username' => 'buyer123',
        'saldo' => '500000.00',
    ]);
    $this->user->assignRole(Role::User->value);

    $this->admin = User::factory()->create([
        'username' => 'adminramela',
    ]);
    $this->admin->assignRole(Role::Admin->value);

    $this->store = Store::create([
        'name' => 'RAMELA Eats',
        'slug' => 'ramela-eats',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Nasi Kotak Spesial',
        'slug' => 'nasi-kotak-spesial',
        'price' => '25000.00',
        'stock' => 50,
        'is_active' => true,
    ]);

    $this->shippingRate = ShippingRate::create([
        'city_name' => 'Jakarta Selatan',
        'shipping_cost' => '15000.00',
        'estimated_delivery' => '1-2 jam',
        'is_active' => true,
    ]);
});

test('user dapat memilih metode pickup ambil sendiri dengan ongkir nol', function () {
    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($this->user)->post("/checkout/{$this->store->slug}", [
        'delivery_type' => 'pickup',
        'recipient_name' => 'Budi Santoso',
        'recipient_phone' => '081234567890',
        'note' => 'Ambil jam 12 siang',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'delivery_type' => 'pickup',
        'total_amount' => 50000.00,
        'shipping_cost' => 0.00,
        'final_amount' => 50000.00,
        'status' => OrderStatus::Paid->value,
    ]);
});

test('user memilih kurir wajib memilih kab/kota, kec/kel, kode pos dan alamat lengkap', function () {
    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ]);

    // Request kurir tanpa rincian alamat
    $response = $this->actingAs($this->user)->post("/checkout/{$this->store->slug}", [
        'delivery_type' => 'courier',
        'recipient_name' => 'Budi Santoso',
        'recipient_phone' => '081234567890',
    ]);

    $response->assertSessionHasErrors([
        'shipping_rate_id',
        'shipping_district',
        'shipping_postal_code',
        'shipping_address',
    ]);

    // Request lengkap dengan tarif kurir
    $successResponse = $this->actingAs($this->user)->post("/checkout/{$this->store->slug}", [
        'delivery_type' => 'courier',
        'recipient_name' => 'Budi Santoso',
        'recipient_phone' => '081234567890',
        'shipping_rate_id' => $this->shippingRate->id,
        'shipping_district' => 'Kebayoran Baru',
        'shipping_postal_code' => '12110',
        'shipping_address' => 'Jl. Senopati No. 45',
        'note' => 'Pagar warna hitam',
    ]);

    $successResponse->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'delivery_type' => 'courier',
        'shipping_city' => 'Jakarta Selatan',
        'shipping_district' => 'Kebayoran Baru',
        'shipping_postal_code' => '12110',
        'shipping_cost' => 15000.00,
        'total_amount' => 25000.00,
        'final_amount' => 40000.00, // 25000 + 15000
    ]);
});

test('admin dapat mengelola tarif ongkir kurir', function () {
    // Tambah tarif dengan pricing_type flat
    $storeRes = $this->actingAs($this->admin)->post('/admin/kurir/tarif', [
        'city_name' => 'Depok',
        'shipping_cost' => 20000,
        'pricing_type' => 'flat',
        'estimated_delivery' => '2-3 jam',
        'is_active' => true,
    ]);
    $storeRes->assertRedirect();
    $this->assertDatabaseHas('shipping_rates', [
        'city_name' => 'Depok',
        'shipping_cost' => 20000.00,
        'pricing_type' => 'flat',
    ]);

    $rate = ShippingRate::where('city_name', 'Depok')->first();

    // Update tarif ke per_kg
    $updateRes = $this->actingAs($this->admin)->put("/admin/kurir/tarif/{$rate->id}", [
        'city_name' => 'Depok Kota',
        'shipping_cost' => 22000,
        'pricing_type' => 'per_kg',
        'estimated_delivery' => '1-2 jam',
        'is_active' => true,
    ]);
    $updateRes->assertRedirect();
    $this->assertDatabaseHas('shipping_rates', [
        'id' => $rate->id,
        'city_name' => 'Depok Kota',
        'shipping_cost' => 22000.00,
        'pricing_type' => 'per_kg',
    ]);

    // Hapus tarif
    $deleteRes = $this->actingAs($this->admin)->delete("/admin/kurir/tarif/{$rate->id}");
    $deleteRes->assertRedirect();
    $this->assertDatabaseMissing('shipping_rates', [
        'id' => $rate->id,
    ]);
});

test('checkout kurir dengan total berat kurang dari 1 kg tetap dihitung 1 kg', function () {
    $lightProduct = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Kerupuk Kaleng',
        'slug' => 'kerupuk-kaleng',
        'price' => '10000.00',
        'stock' => 50,
        'weight' => 250, // 250 gram
        'is_active' => true,
    ]);

    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $lightProduct->id,
        'quantity' => 2, // 2 * 250g = 500g (< 1 kg)
    ]);

    $response = $this->actingAs($this->user)->post("/checkout/{$this->store->slug}", [
        'delivery_type' => 'courier',
        'recipient_name' => 'Budi Santoso',
        'recipient_phone' => '081234567890',
        'shipping_rate_id' => $this->shippingRate->id, // 15000 / kg
        'shipping_district' => 'Kebayoran Baru',
        'shipping_postal_code' => '12110',
        'shipping_address' => 'Jl. Senopati No. 45',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'delivery_type' => 'courier',
        'total_weight' => 500,
        'shipping_cost' => 15000.00, // Dihitung 1 kg (1 * 15000)
        'total_amount' => 20000.00,
        'final_amount' => 35000.00, // 20000 + 15000
    ]);
});

test('checkout kurir dengan total berat lebih dari 1 kg dihitung kelipatannya (ceil)', function () {
    $heavyProduct = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Beras Organik',
        'slug' => 'beras-organik',
        'price' => '30000.00',
        'stock' => 50,
        'weight' => 700, // 700 gram
        'is_active' => true,
    ]);

    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $heavyProduct->id,
        'quantity' => 2, // 2 * 700g = 1400g (1.4 kg => dibulatkan 2 kg)
    ]);

    $response = $this->actingAs($this->user)->post("/checkout/{$this->store->slug}", [
        'delivery_type' => 'courier',
        'recipient_name' => 'Budi Santoso',
        'recipient_phone' => '081234567890',
        'shipping_rate_id' => $this->shippingRate->id, // 15000 / kg
        'shipping_district' => 'Kebayoran Baru',
        'shipping_postal_code' => '12110',
        'shipping_address' => 'Jl. Senopati No. 45',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'delivery_type' => 'courier',
        'total_weight' => 1400,
        'shipping_cost' => 30000.00, // 2 kg * 15000 = 30000
        'total_amount' => 60000.00,
        'final_amount' => 90000.00, // 60000 + 30000
    ]);
});

test('checkout kurir dengan tarif flat tidak terpengaruh oleh berat belanjaan', function () {
    $flatRate = ShippingRate::create([
        'city_name' => 'Bandung Flat',
        'shipping_cost' => '25000.00',
        'pricing_type' => 'flat',
        'estimated_delivery' => '1 hari',
        'is_active' => true,
    ]);

    $heavyProduct = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Minyak Goreng Jirigen',
        'slug' => 'minyak-goreng-jirigen',
        'price' => '50000.00',
        'stock' => 50,
        'weight' => 5000, // 5000 gram (5 kg)
        'is_active' => true,
    ]);

    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $heavyProduct->id,
        'quantity' => 2, // 10 kg
    ]);

    $response = $this->actingAs($this->user)->post("/checkout/{$this->store->slug}", [
        'delivery_type' => 'courier',
        'recipient_name' => 'Budi Santoso',
        'recipient_phone' => '081234567890',
        'shipping_rate_id' => $flatRate->id,
        'shipping_district' => 'Coblong',
        'shipping_postal_code' => '40132',
        'shipping_address' => 'Jl. Dago No. 100',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'delivery_type' => 'courier',
        'total_weight' => 10000,
        'shipping_cost' => 25000.00, // Tetap flat 25000
        'total_amount' => 100000.00,
        'final_amount' => 125000.00, // 100000 + 25000
    ]);
});

test('admin dapat menyimpan produk dengan berat dalam satuan kg dan dikonversi ke gram', function () {
    $response = $this->actingAs($this->admin)->post('/admin/produk', [
        'store_id' => $this->store->id,
        'name' => 'Gula Pasir 2.5 Kg',
        'price' => 35000,
        'stock' => 20,
        'unit' => 'bungkus',
        'weight' => 2.5,
        'weight_unit' => 'kg',
        'is_active' => true,
    ]);

    $response->assertRedirect('/admin/produk');

    $this->assertDatabaseHas('products', [
        'name' => 'Gula Pasir 2.5 Kg',
        'weight' => 2500, // 2.5 kg dikonversi jadi 2500 gram
    ]);
});

test('admin hanya melihat aksi Siap Dijemput untuk order pickup dan Siap Dikirim untuk order kurir', function () {
    // 1. Buat pesanan pickup berstatus processed
    $pickupTx = Transaction::create([
        'user_id' => $this->user->id,
        'store_id' => $this->store->id,
        'invoice_number' => 'INV-PICKUP-001',
        'total_amount' => 50000,
        'final_amount' => 50000,
        'status' => OrderStatus::Processed,
        'delivery_type' => 'pickup',
        'recipient_name' => 'Pelanggan Pickup',
        'recipient_phone' => '0812345678',
        'shipping_address' => 'Ambil di Toko',
    ]);

    // 2. Buat pesanan kurir berstatus processed
    $courierTx = Transaction::create([
        'user_id' => $this->user->id,
        'store_id' => $this->store->id,
        'invoice_number' => 'INV-COURIER-001',
        'total_amount' => 50000,
        'shipping_cost' => 15000,
        'final_amount' => 65000,
        'status' => OrderStatus::Processed,
        'delivery_type' => 'courier',
        'recipient_name' => 'Pelanggan Kurir',
        'recipient_phone' => '0812345678',
        'shipping_address' => 'Jl. Pengiriman No 1',
    ]);

    // Admin buka detail pesanan pickup
    $this->actingAs($this->admin)->get("/admin/pesanan/{$pickupTx->invoice_number}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/OrderShow')
            ->where('actions', fn ($actions) =>
                collect($actions)->pluck('value')->contains('ready_for_pickup') &&
                ! collect($actions)->pluck('value')->contains('ready_to_ship')
            )
        );

    // Admin buka detail pesanan kurir
    $this->actingAs($this->admin)->get("/admin/pesanan/{$courierTx->invoice_number}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/OrderShow')
            ->where('actions', fn ($actions) =>
                collect($actions)->pluck('value')->contains('ready_to_ship') &&
                ! collect($actions)->pluck('value')->contains('ready_for_pickup')
            )
        );
});
