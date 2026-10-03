<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function makeUser(Role $role = Role::User, array $attrs = []): User
{
    $user = User::factory()->create($attrs + ['password' => 'password123']);
    $user->assignRole($role->value);

    return $user->refresh();
}

test('register hanya butuh username dan password, saldo awal 0', function () {
    $this->post('/register', [
        'username' => 'budi_01',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/dashboard');

    $user = User::where('username', 'budi_01')->first();
    expect($user->saldo)->toBe('0.00')
        ->and($user->hasRole('pengguna'))->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('register menolak username duplikat dan karakter tidak valid', function () {
    makeUser(attrs: ['username' => 'sudahada']);

    $this->post('/register', ['username' => 'sudahada', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertSessionHasErrors('username');
    $this->post('/register', ['username' => 'a b!', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertSessionHasErrors('username');
});

test('login berhasil dan mengarah ke dashboard', function () {
    makeUser(attrs: ['username' => 'user1']);
    makeUser(Role::Admin, ['username' => 'adm1']);

    $this->post('/login', ['username' => 'user1', 'password' => 'password123'])->assertRedirect('/dashboard');
    $this->post('/logout');
    $this->post('/login', ['username' => 'adm1', 'password' => 'password123'])->assertRedirect('/dashboard');
});

test('login salah ditolak dan dibatasi setelah 5 percobaan', function () {
    makeUser(attrs: ['username' => 'user1']);

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['username' => 'user1', 'password' => 'salah'])->assertSessionHasErrors('username');
    }

    // Percobaan ke-6 diblokir meski password benar
    $this->post('/login', ['username' => 'user1', 'password' => 'password123'])->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('tamu diarahkan ke login saat membuka dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('area admin hanya dapat diakses oleh admin dan super admin', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->actingAs(makeUser())->get('/admin')->assertNotFound();
    $this->actingAs(makeUser(Role::Admin))->get('/admin')->assertOk();
    $this->actingAs(makeUser(Role::SuperAdmin))->get('/admin')->assertOk();
});

test('pengguna tidak bisa membuka panel admin/kurir', function () {
    $this->actingAs(makeUser())->get('/admin')->assertNotFound();
    $this->actingAs(makeUser())->get('/kurir')->assertNotFound();
});

test('dashboard menampilkan saldo dan memperbarui terakhir aktif', function () {
    $user = makeUser();
    app(\App\Services\WalletService::class)->credit($user, '50000', 'topup', note: 'Top-up saldo awal');

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Dashboard')
            ->where('auth.user.saldo', '50000.00')
            ->where('profileComplete', false)
            ->has('recentWallet', 1)
            ->where('recentWallet.0.note', 'Top-up saldo awal')
            ->where('recentWallet.0.description', 'Top-up saldo awal'));

    expect($user->fresh()->last_active_at)->not->toBeNull();
});

test('pengguna bisa melengkapi profil', function () {
    $user = makeUser();

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Budi Santoso', 'email' => 'budi@example.com', 'phone' => '08123456789',
    ])->assertSessionHasNoErrors();

    expect($user->fresh())->name->toBe('Budi Santoso')->email->toBe('budi@example.com');
});

test('ganti password butuh password lama yang benar', function () {
    $user = makeUser();

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'password123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('baru12345', $user->fresh()->password))->toBeTrue();
});
