<?php

use App\Actions\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promo;
use App\Models\PromoLog;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
    $this->store = Store::create(['slug' => 'eats', 'name' => 'RAMELA EATS']);
    $this->other = Store::create(['slug' => 'beton', 'name' => 'RAMELA BETON']);
    $this->product = Product::create([
        'store_id' => $this->store->id, 'name' => 'Nasi', 'slug' => 'nasi', 'price' => 50000, 'stock' => 20,
    ]);
    $this->user = User::factory()->create();
    $this->user->assignRole('pengguna');
    $this->user->refresh();
    app(WalletService::class)->credit($this->user, 500000, 'topup');
});

function mkPromo(array $o = []): Promo
{
    return Promo::create(array_merge([
        'code' => 'HEMAT10', 'discount_type' => 'percent', 'discount_value' => 10,
        'min_purchase' => 0, 'is_active' => true,
    ], $o))->refresh();
}

function buy($t, int $qty = 2, ?string $code = 'HEMAT10')
{
    CartItem::updateOrCreate(['user_id' => $t->user->id, 'product_id' => $t->product->id], ['quantity' => $qty]);

    return $t->actingAs($t->user)->post('/checkout/eats', [
        'recipient_name' => 'Budi', 'recipient_phone' => '08123456789',
        'shipping_address' => 'Jl. Mawar 1', 'promo_code' => $code,
    ]);
}

test('promo persen terpotong, log & used_count tercatat, kode case-insensitive', function () {
    mkPromo();
    buy($this, 2, ' hemat10 ')->assertSessionHasNoErrors();

    $o = Transaction::first();
    expect($o->discount_amount)->toBe('10000.00')
        ->and($o->final_amount)->toBe('90000.00')
        ->and(PromoLog::count())->toBe(1)
        ->and(Promo::first()->used_count)->toBe(1)
        ->and($this->user->fresh()->saldo)->toBe('410000.00');
});

test('promo nominal dibatasi subtotal & persen dibatasi max_discount', function () {
    $svc = app(App\Services\PromoService::class);
    $big = mkPromo(['code' => 'BESAR2', 'discount_type' => 'nominal', 'discount_value' => 999999]);
    expect($svc->discount($big, '50000.00'))->toBe('50000.00');

    $p = mkPromo(['code' => 'CAP', 'discount_value' => 50, 'max_discount_amount' => 5000]);
    expect(app(App\Services\PromoService::class)->discount($p, '100000.00'))->toBe('5000.00');
});

test('promo ditolak: scope, min belanja, kuota, kedaluwarsa, nonaktif', function () {
    $svc = app(App\Services\PromoService::class);
    $cases = [
        mkPromo(['code' => 'A'])->stores()->sync([$this->other->id]) ?? 'A',
    ];
    mkPromo(['code' => 'B', 'min_purchase' => 999999]);
    mkPromo(['code' => 'C', 'quota' => 1, 'used_count' => 1]);
    mkPromo(['code' => 'D', 'valid_until' => now()->subDay()]);
    mkPromo(['code' => 'E', 'is_active' => false]);
    mkPromo(['code' => 'F', 'starts_at' => now()->addDay()]);

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'NOPE'] as $code) {
        expect(fn () => $svc->evaluate($code, $this->user, $this->store, '100000.00'))
            ->toThrow(App\Exceptions\PromoException::class);
    }
});

test('per_user_limit; batal mengembalikan kuota sekali dan promo bisa dipakai lagi', function () {
    mkPromo(['per_user_limit' => 1, 'quota' => 5]);
    buy($this);
    buy($this)->assertSessionHasErrors('checkout'); // batas per user
    expect(Promo::first()->used_count)->toBe(1);

    $order = Transaction::first();
    $cancel = fn () => app(ChangeOrderStatus::class)->execute($order->fresh(), OrderStatus::Cancelled, null, 'x');
    $cancel();
    expect(Promo::first()->used_count)->toBe(0)
        ->and(PromoLog::first()->cancelled_at)->not->toBeNull();

    expect(fn () => $cancel())->toThrow(Exception::class);
    expect(Promo::first()->used_count)->toBe(0);

    buy($this)->assertSessionHasNoErrors();
    expect(Promo::first()->used_count)->toBe(1);
});

test('checkout gagal (saldo kurang) tidak menaikkan used_count', function () {
    $poor = User::factory()->create();
    $poor->assignRole('pengguna');
    mkPromo();
    CartItem::create(['user_id' => $poor->id, 'product_id' => $this->product->id, 'quantity' => 2]);
    $this->actingAs($poor)->post('/checkout/eats', [
        'recipient_name' => 'B', 'recipient_phone' => '08123456789',
        'shipping_address' => 'x', 'promo_code' => 'HEMAT10',
    ])->assertSessionHasErrors('checkout');

    expect(Promo::first()->used_count)->toBe(0)->and(PromoLog::count())->toBe(0);
});

test('hanya admin yang bisa kelola promo', function () {
    $this->actingAs($this->user)->get('/admin/promo')->assertNotFound();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)->post('/admin/promo', [
        'code' => 'baru', 'discount_type' => 'percent', 'discount_value' => 15,
        'store_ids' => [$this->store->id],
    ])->assertSessionHasNoErrors();

    $p = Promo::where('code', 'BARU')->first();
    expect($p)->not->toBeNull()->and($p->stores)->toHaveCount(1);
    $this->actingAs($admin)->get('/admin/promo')->assertOk();
    $this->actingAs($admin)->get("/admin/promo/{$p->id}/log")->assertOk();
    $this->actingAs($admin)->patch("/admin/promo/{$p->id}/toggle");
    expect($p->fresh()->is_active)->toBeFalse();
});
