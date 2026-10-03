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
    // Tambah tarif
    $storeRes = $this->actingAs($this->admin)->post('/admin/kurir/tarif', [
        'city_name' => 'Depok',
        'shipping_cost' => 20000,
        'estimated_delivery' => '2-3 jam',
        'is_active' => true,
    ]);
    $storeRes->assertRedirect();
    $this->assertDatabaseHas('shipping_rates', [
        'city_name' => 'Depok',
        'shipping_cost' => 20000.00,
    ]);

    $rate = ShippingRate::where('city_name', 'Depok')->first();

    // Update tarif
    $updateRes = $this->actingAs($this->admin)->put("/admin/kurir/tarif/{$rate->id}", [
        'city_name' => 'Depok Kota',
        'shipping_cost' => 22000,
        'estimated_delivery' => '1-2 jam',
        'is_active' => true,
    ]);
    $updateRes->assertRedirect();
    $this->assertDatabaseHas('shipping_rates', [
        'id' => $rate->id,
        'city_name' => 'Depok Kota',
        'shipping_cost' => 22000.00,
    ]);

    // Hapus tarif
    $deleteRes = $this->actingAs($this->admin)->delete("/admin/kurir/tarif/{$rate->id}");
    $deleteRes->assertRedirect();
    $this->assertDatabaseMissing('shipping_rates', [
        'id' => $rate->id,
    ]);
});
