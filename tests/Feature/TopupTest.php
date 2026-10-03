<?php

use App\Enums\Role;
use App\Models\TopupHistory;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebSetting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

const SERVER_KEY = 'SB-Mid-server-TESTKEY1234';

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
    $this->user = User::factory()->create();
    $this->user->assignRole('pengguna');
    $this->user->refresh();
});

function configureMidtrans(): void
{
    app(SettingsService::class)->set('midtrans.server_key', SERVER_KEY, encrypted: true);
}

function pendingTopup(User $user, int $amount = 50000): TopupHistory
{
    return $user->topupHistories()->create([
        'amount' => $amount,
        'midtrans_order_id' => 'TOPUP-' . uniqid(),
        'status' => 'pending',
    ]);
}

function notif(TopupHistory $t, array $over = []): array
{
    $p = $over + [
        'order_id' => $t->midtrans_order_id,
        'status_code' => '200',
        'gross_amount' => number_format($t->amount, 2, '.', ''),
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
    ];
    $p['signature_key'] = $over['signature_key'] ?? hash('sha512', $p['order_id'] . $p['status_code'] . $p['gross_amount'] . SERVER_KEY);

    return $p;
}

test('top-up membuat transaksi pending lalu mengarahkan ke Midtrans', function () {
    configureMidtrans();
    Http::fake(['app.sandbox.midtrans.com/*' => Http::response([
        'token' => 'tok123',
        'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok123',
    ], 201)]);

    $this->actingAs($this->user)->post('/topup', ['amount' => 50000])
        ->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/tok123');

    $t = TopupHistory::first();
    expect($t->status)->toBe('pending')->and($t->amount)->toBe('50000.00')->and($t->snap_token)->toBe('tok123');
    expect($this->user->fresh()->saldo)->toBe('0.00'); // saldo belum bertambah sebelum pembayaran
});

test('top-up via AJAX/JSON mengembalikan token dan redirect_url untuk popup Snap di PWA', function () {
    configureMidtrans();
    Http::fake(['app.sandbox.midtrans.com/*' => Http::response([
        'token' => 'tok_pwa_456',
        'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok_pwa_456',
    ], 201)]);

    $response = $this->actingAs($this->user)->postJson('/topup', ['amount' => 75000]);

    $response->assertOk()
        ->assertJson([
            'token' => 'tok_pwa_456',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok_pwa_456',
        ])
        ->assertJsonStructure(['token', 'redirect_url', 'order_id']);

    $t = TopupHistory::first();
    expect($t->status)->toBe('pending')->and($t->amount)->toBe('75000.00')->and($t->snap_token)->toBe('tok_pwa_456');
});

test('top-up ditolak bila Midtrans belum dikonfigurasi atau nominal tidak valid', function () {
    $this->actingAs($this->user)->post('/topup', ['amount' => 50000])->assertSessionHasErrors('amount');

    configureMidtrans();
    $this->actingAs($this->user)->post('/topup', ['amount' => 500])->assertSessionHasErrors('amount');
    $this->actingAs($this->user)->post('/topup', ['amount' => 999999999])->assertSessionHasErrors('amount');
    expect(TopupHistory::count())->toBe(0);
});

test('webhook tanpa signature valid ditolak dan saldo tidak berubah', function () {
    configureMidtrans();
    $t = pendingTopup($this->user);

    $this->postJson('/midtrans/notification', notif($t, ['signature_key' => 'palsu']))->assertForbidden();
    $this->postJson('/midtrans/notification', ['order_id' => $t->midtrans_order_id])->assertForbidden();

    expect($t->fresh()->status)->toBe('pending')->and($this->user->fresh()->saldo)->toBe('0.00');
});

test('webhook settlement menambah saldo tepat satu kali (idempoten)', function () {
    configureMidtrans();
    $t = pendingTopup($this->user, 75000);

    $this->postJson('/midtrans/notification', notif($t))->assertOk();
    $this->postJson('/midtrans/notification', notif($t))->assertOk(); // kirim ulang

    expect($t->fresh()->status)->toBe('success')
        ->and($this->user->fresh()->saldo)->toBe('75000.00')
        ->and(WalletTransaction::where('type', 'topup')->count())->toBe(1);
});

test('nominal notifikasi tidak cocok tidak dikreditkan', function () {
    configureMidtrans();
    $t = pendingTopup($this->user, 50000);

    $this->postJson('/midtrans/notification', notif($t, ['gross_amount' => '99999999.00']))->assertOk();

    expect($t->fresh()->status)->toBe('pending')->and($this->user->fresh()->saldo)->toBe('0.00');
});

test('expire dan deny menandai gagal tanpa menambah saldo; pending diabaikan', function () {
    configureMidtrans();
    $a = pendingTopup($this->user);
    $b = pendingTopup($this->user);
    $c = pendingTopup($this->user);

    $this->postJson('/midtrans/notification', notif($a, ['transaction_status' => 'expire']))->assertOk();
    $this->postJson('/midtrans/notification', notif($b, ['transaction_status' => 'deny']))->assertOk();
    $this->postJson('/midtrans/notification', notif($c, ['transaction_status' => 'pending']))->assertOk();

    expect($a->fresh()->status)->toBe('expired')
        ->and($b->fresh()->status)->toBe('failed')
        ->and($c->fresh()->status)->toBe('pending')
        ->and($this->user->fresh()->saldo)->toBe('0.00');
});

test('top-up yang sudah final tidak bisa dibalik oleh notifikasi susulan', function () {
    configureMidtrans();
    $t = pendingTopup($this->user);
    $this->postJson('/midtrans/notification', notif($t, ['transaction_status' => 'expire']));
    $this->postJson('/midtrans/notification', notif($t, ['transaction_status' => 'settlement']));

    expect($t->fresh()->status)->toBe('expired')->and($this->user->fresh()->saldo)->toBe('0.00');
});

test('sinkron manual lewat Status API mengkredit saldo', function () {
    configureMidtrans();
    $t = pendingTopup($this->user, 20000);
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(notif($t))]);

    $this->actingAs($this->user)->post("/topup/{$t->midtrans_order_id}/sync")->assertSessionHasNoErrors();

    expect($t->fresh()->status)->toBe('success')->and($this->user->fresh()->saldo)->toBe('20000.00');
});

test('user tidak bisa menyinkronkan top-up milik orang lain', function () {
    configureMidtrans();
    $other = User::factory()->create();
    $other->assignRole('pengguna');
    $t = pendingTopup($other);

    $this->actingAs($this->user)->post("/topup/{$t->midtrans_order_id}/sync")->assertNotFound();
});

test('kunci Midtrans disimpan terenkripsi dan tidak pernah dikirim ke frontend', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $regularAdmin = User::factory()->create();
    $regularAdmin->assignRole('admin');
    $path = '/admin/pengaturan';

    $this->actingAs($this->user)->get($path)->assertNotFound();
    $this->actingAs($this->user)->put($path, [])->assertNotFound();
    $this->actingAs($regularAdmin)->get($path)->assertNotFound();
    $this->actingAs($regularAdmin)->put($path, [])->assertNotFound();

    $this->actingAs($admin)->put($path, [
        'is_production' => false,
        'server_key' => SERVER_KEY,
        'client_key' => 'SB-Mid-client-ABCD9999',
        'feature_blog' => true,
        'feature_faq' => true,
    ])->assertSessionHasNoErrors();

    $raw = WebSetting::where('key', 'midtrans.server_key')->first()->getAttributes()['value'];
    expect($raw)->not->toContain('TESTKEY')
        ->and(app(SettingsService::class)->get('midtrans.server_key'))->toBe(SERVER_KEY);

    $response = $this->actingAs($admin)->get($path)->assertOk();
    expect($response->getContent())->not->toContain('TESTKEY1234')->not->toContain('ABCD9999-full');
    $response->assertInertia(fn($p) => $p->where('midtrans.secrets.server_key', '••••1234'));

    // Kolom dikosongkan = nilai lama dipertahankan
    $this->actingAs($admin)->put($path, [
        'is_production' => true,
        'server_key' => '',
        'feature_blog' => false,
        'feature_faq' => true,
    ]);
    expect(app(SettingsService::class)->get('midtrans.server_key'))->toBe(SERVER_KEY)
        ->and(app(SettingsService::class)->bool('midtrans.is_production'))->toBeTrue()
        ->and(app(SettingsService::class)->bool('feature.blog', true))->toBeFalse();
});
