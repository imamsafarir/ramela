<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }

    $this->superAdmin = User::factory()->create(['username' => 'dewa']);
    $this->superAdmin->assignRole('super_admin');
    $this->superAdmin->refresh();

    $this->admin = User::factory()->create(['username' => 'admin_biasa']);
    $this->admin->assignRole('admin');
    $this->admin->refresh();

    $this->customer = User::factory()->create(['username' => 'pelanggan1']);
    $this->customer->assignRole('pengguna');
    $this->customer->refresh();

    $this->store = Store::create(['slug' => 'eats', 'name' => 'RAMELA EATS'])->refresh();
    $this->product = Product::create([
        'store_id' => $this->store->id, 'name' => 'Nasi Ayam', 'slug' => 'nasi-ayam', 'price' => 20000, 'stock' => 10,
    ]);
});

test('area manajemen admin terlindungi dari tamu dan pelanggan biasa', function () {
    $this->get('/admin/users')->assertRedirect('/login');
    $this->get('/admin/transaksi')->assertRedirect('/login');
    $this->get('/admin/pengaturan')->assertRedirect('/login');
    $this->get('/admin/kurir')->assertRedirect('/login');

    $this->actingAs($this->customer)->get('/admin/users')->assertNotFound();
    $this->actingAs($this->customer)->get('/admin/transaksi')->assertNotFound();
    $this->actingAs($this->customer)->get('/admin/pengaturan')->assertNotFound();
    $this->actingAs($this->customer)->get('/admin/kurir')->assertNotFound();

    // Admin operasional hanya bisa mengakses menu umum, DILARANG dari transaksi/bypass dan pengaturan
    $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    $this->actingAs($this->admin)->get('/admin/kurir')->assertOk();
    $this->actingAs($this->admin)->get('/admin/transaksi')->assertNotFound();
    $this->actingAs($this->admin)->get('/admin/pengaturan')->assertNotFound();

    // Hanya Super Admin yang bisa mengakses transaksi/bypass dan pengaturan
    $this->actingAs($this->superAdmin)->get('/admin/users')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/kurir')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/transaksi')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/pengaturan')->assertOk();
});

test('role admin tidak melihat pengguna superadmin dan opsi role superadmin di halaman pengguna', function () {
    // Admin biasa mengakses /admin/users
    $response = $this->actingAs($this->admin)->get('/admin/users')->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users')
        ->where('isSuperAdmin', false)
        ->where('roleCounts.super_admin', 0)
        // Tidak ada super_admin dalam daftar opsi roles
        ->where('roles', fn ($roles) => ! in_array('super_admin', $roles->toArray(), true))
        // Tidak ada user superadmin dalam data tabel
        ->where('users.data', fn ($users) => collect($users)->every(fn ($u) => $u['role'] !== 'super_admin'))
    );
});

test('role superadmin dapat melihat semua user termasuk sesama superadmin', function () {
    $response = $this->actingAs($this->superAdmin)->get('/admin/users')->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users')
        ->where('isSuperAdmin', true)
        ->where('roleCounts.super_admin', 1)
        ->where('roles', fn ($roles) => in_array('super_admin', $roles->toArray(), true))
    );
});

test('admin biasa diblokir dari aksi mutasi pengguna (tambah, edit, role, sandi, saldo, hapus)', function () {
    // Tambah user
    $this->actingAs($this->admin)->post('/admin/users', [
        'username' => 'test_user',
        'password' => 'secret123',
        'role' => 'pengguna',
    ])->assertForbidden();

    // Edit user
    $this->actingAs($this->admin)->put("/admin/users/{$this->customer->id}", [
        'username' => 'edited_user',
    ])->assertForbidden();

    // Ganti role
    $this->actingAs($this->admin)->patch("/admin/users/{$this->customer->id}/role", [
        'role' => 'kurir',
    ])->assertForbidden();

    // Reset password
    $this->actingAs($this->admin)->put("/admin/users/{$this->customer->id}/password", [
        'password' => 'password123',
    ])->assertForbidden();

    // Koreksi saldo
    $this->actingAs($this->admin)->post("/admin/users/{$this->customer->id}/saldo", [
        'type' => 'credit',
        'amount' => 50000,
        'note' => 'Bonus',
    ])->assertForbidden();

    // Hapus user
    $this->actingAs($this->admin)->delete("/admin/users/{$this->customer->id}")->assertForbidden();
});

test('super admin dapat mengubah role user lain', function () {
    $this->actingAs($this->superAdmin)
        ->patch("/admin/users/{$this->customer->id}/role", ['role' => 'kurir'])
        ->assertSessionHasNoErrors();

    expect($this->customer->fresh()->primaryRole())->toBe('kurir');
});

test('super admin dapat mereset password pengguna', function () {
    $this->actingAs($this->superAdmin)
        ->put("/admin/users/{$this->customer->id}/password", ['password' => 'passwordbaru123'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('passwordbaru123', $this->customer->fresh()->password))->toBeTrue();
});

test('super admin dapat mengoreksi saldo user secara manual (kredit dan debit)', function () {
    // Tambah saldo (+)
    $this->actingAs($this->superAdmin)
        ->post("/admin/users/{$this->customer->id}/saldo", [
            'type' => 'credit',
            'amount' => 75000,
            'note' => 'Bonus promo spesial',
        ])
        ->assertSessionHasNoErrors();

    expect($this->customer->fresh()->saldo)->toBe('75000.00');

    // Tarik saldo (-)
    $this->actingAs($this->superAdmin)
        ->post("/admin/users/{$this->customer->id}/saldo", [
            'type' => 'debit',
            'amount' => 25000,
            'note' => 'Koreksi kelebihan transfer',
        ])
        ->assertSessionHasNoErrors();

    expect($this->customer->fresh()->saldo)->toBe('50000.00');
});

test('super admin dapat menghapus akun user', function () {
    $this->actingAs($this->superAdmin)
        ->delete("/admin/users/{$this->customer->id}")
        ->assertSessionHasNoErrors();

    expect($this->customer->fresh()->trashed())->toBeTrue();

    // Tidak bisa hapus akun sendiri
    $this->actingAs($this->superAdmin)
        ->delete("/admin/users/{$this->superAdmin->id}")
        ->assertSessionHasErrors('delete');
});

test('super admin dapat menambahkan pengguna baru beserta role dan saldo awal', function () {
    $this->actingAs($this->superAdmin)->post('/admin/users', [
        'username' => 'kurir_baru',
        'name' => 'Bambang Sudiro',
        'email' => 'bambang@example.com',
        'phone' => '081298765432',
        'password' => 'secret123',
        'role' => 'kurir',
        'initial_balance' => 50000,
    ])->assertSessionHasNoErrors();

    $newUser = User::where('username', 'kurir_baru')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('Bambang Sudiro')
        ->and($newUser->email)->toBe('bambang@example.com')
        ->and($newUser->primaryRole())->toBe('kurir')
        ->and($newUser->saldo)->toBe('50000.00');

    expect(Hash::check('secret123', $newUser->password))->toBeTrue();
});

test('super admin dapat memperbarui data profil pengguna', function () {
    $this->actingAs($this->superAdmin)->put("/admin/users/{$this->customer->id}", [
        'username' => 'pelanggan_diedit',
        'name' => 'Nama Pelanggan Lengkap',
        'email' => 'pelanggan.baru@example.com',
        'phone' => '085712345678',
    ])->assertSessionHasNoErrors();

    $this->customer->refresh();
    expect($this->customer->username)->toBe('pelanggan_diedit')
        ->and($this->customer->name)->toBe('Nama Pelanggan Lengkap')
        ->and($this->customer->email)->toBe('pelanggan.baru@example.com')
        ->and($this->customer->phone)->toBe('085712345678');
});

test('admin dapat membypass dan memaksa status transaksi (termasuk pembatalan paksa & refund)', function () {
    app(WalletService::class)->credit($this->customer, 40000, 'topup');
    CartItem::create(['user_id' => $this->customer->id, 'product_id' => $this->product->id, 'quantity' => 2]);

    $tx = app(App\Actions\CheckoutAction::class)->execute($this->customer, $this->store, [
        'recipient_name' => 'Budi',
        'recipient_phone' => '08123456789',
        'shipping_address' => 'Jl. Kenanga No 5',
    ]);

    expect($tx->status)->toBe(OrderStatus::Paid)
        ->and($this->customer->fresh()->saldo)->toBe('0.00')
        ->and($this->product->fresh()->stock)->toBe(8);

    // Admin biasa tidak boleh mengakses bypass transaksi (404)
    $this->actingAs($this->admin)
        ->post("/admin/transaksi/{$tx->invoice_number}/status", [
            'status' => 'completed',
            'note' => 'Coba bypass oleh admin biasa',
        ])
        ->assertNotFound();

    // Super Admin dapat membypass paksa langsung ke completed
    $this->actingAs($this->superAdmin)
        ->post("/admin/transaksi/{$tx->invoice_number}/status", [
            'status' => 'completed',
            'note' => 'Diselesaikan langsung oleh Super Admin',
        ])
        ->assertSessionHasNoErrors();

    expect($tx->fresh()->status)->toBe(OrderStatus::Completed);

    // Sekarang batalkan paksa transaksi yang sudah completed oleh Super Admin
    $this->actingAs($this->superAdmin)
        ->post("/admin/transaksi/{$tx->invoice_number}/status", [
            'status' => 'cancelled',
            'note' => 'Pembatalan transaksi oleh Super Admin karena komplain darurat',
        ])
        ->assertSessionHasNoErrors();

    expect($tx->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->customer->fresh()->saldo)->toBe('40000.00')
        ->and($this->product->fresh()->stock)->toBe(10);
});

test('admin dapat memantau dan menugaskan kurir ke pesanan siap kirim', function () {
    $courier = User::factory()->create(['username' => 'kurir_andi']);
    $courier->assignRole('kurir');

    $tx = Transaction::create([
        'user_id' => $this->customer->id,
        'store_id' => $this->store->id,
        'invoice_number' => 'INV-202610-0099',
        'status' => OrderStatus::ReadyToShip,
        'total_amount' => 20000,
        'final_amount' => 20000,
        'recipient_name' => 'Siti',
        'recipient_phone' => '0899887766',
        'shipping_address' => 'Jl. Mawar No 1',
    ]);

    // Admin buka halaman kurir
    $this->actingAs($this->admin)->get('/admin/kurir')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Couriers')
            ->has('couriers', 1)
            ->has('unassignedOrders', 1));

    // Admin menugaskan kurir
    $this->actingAs($this->admin)->post('/admin/kurir/assign', [
        'invoice_number' => $tx->invoice_number,
        'courier_id' => $courier->id,
    ])->assertSessionHasNoErrors();

    $delivery = Delivery::where('transaction_id', $tx->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->courier_id)->toBe($courier->id)
        ->and($delivery->status)->toBe('waiting_pickup');
});

test('super admin dapat mengatur lokasi 3 toko di halaman pengaturan', function () {
    $eats = $this->store;
    $hampers = Store::create(['slug' => 'hampers', 'name' => 'RAMELA HAMPERS']);
    $beton = Store::create(['slug' => 'beton', 'name' => 'RAMELA BETON']);

    // 1. Super admin buka halaman pengaturan
    $this->actingAs($this->superAdmin)->get('/admin/pengaturan')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings')
            ->has('stores', 3)
            ->where('stores.0.slug', 'eats')
            ->where('stores.1.slug', 'hampers')
            ->where('stores.2.slug', 'beton'));

    // 2. Super admin simpan pembaruan lokasi ketiga toko
    $this->actingAs($this->superAdmin)->put('/admin/pengaturan', [
        'is_production' => false,
        'feature_blog' => true,
        'feature_faq' => true,
        'stores' => [
            [
                'id' => $eats->id,
                'address' => 'Jl. Pandanaran Baru No. 99, Semarang',
                'latitude' => -6.991234,
                'longitude' => 110.421234,
            ],
            [
                'id' => $hampers->id,
                'address' => 'Jl. Pemuda Tengah No. 200, Semarang',
                'latitude' => -6.974567,
                'longitude' => 110.428567,
            ],
            [
                'id' => $beton->id,
                'address' => 'Kawasan Industri Wijayakusuma Blok C-10, Tugu, Semarang',
                'latitude' => -6.981234,
                'longitude' => 110.341234,
            ],
        ],
    ])->assertSessionHasNoErrors();

    // 3. Verifikasi data tersimpan di database
    expect($eats->fresh()->address)->toBe('Jl. Pandanaran Baru No. 99, Semarang')
        ->and($eats->fresh()->latitude)->toBe(-6.991234)
        ->and($eats->fresh()->longitude)->toBe(110.421234);

    expect($hampers->fresh()->address)->toBe('Jl. Pemuda Tengah No. 200, Semarang')
        ->and($hampers->fresh()->latitude)->toBe(-6.974567)
        ->and($hampers->fresh()->longitude)->toBe(110.428567);

    expect($beton->fresh()->address)->toBe('Kawasan Industri Wijayakusuma Blok C-10, Tugu, Semarang')
        ->and($beton->fresh()->latitude)->toBe(-6.981234)
        ->and($beton->fresh()->longitude)->toBe(110.341234);

    // 4. Validasi koordinat tidak valid (out of range)
    $this->actingAs($this->superAdmin)->put('/admin/pengaturan', [
        'is_production' => false,
        'feature_blog' => true,
        'feature_faq' => true,
        'stores' => [
            [
                'id' => $eats->id,
                'address' => 'Test',
                'latitude' => 150.0, // Invalid: harus between -90 and 90
                'longitude' => 110.0,
            ],
        ],
    ])->assertSessionHasErrors('stores.0.latitude');
});

