<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
    $this->store = Store::create(['slug' => 'eats', 'name' => 'RAMELA EATS']);
    $this->product = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Nasi Goreng',
        'slug' => 'nasi-goreng',
        'price' => 25000,
        'stock' => 10,
    ]);
    $this->user = User::factory()->create();
    $this->user->assignRole('pengguna');
    $this->user->refresh();
});

function topUp(User $user, int $amount): void
{
    app(WalletService::class)->credit($user, $amount, 'topup');
}

function shipping(array $over = []): array
{
    return $over + [
        'recipient_name' => 'Budi',
        'recipient_phone' => '08123456789',
        'shipping_address' => 'Jl. Mawar 1',
    ];
}

test('checkout sukses: saldo terpotong, stok berkurang, ledger & keranjang beres', function () {
    topUp($this->user, 100000);
    CartItem::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'quantity' => 2]);

    $this->actingAs($this->user)->post('/checkout/eats', shipping())->assertSessionHasNoErrors();

    $order = Transaction::first();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->final_amount)->toBe('50000.00')
        ->and($order->details)->toHaveCount(1)
        ->and($order->statusLogs)->toHaveCount(2)
        ->and($this->user->fresh()->saldo)->toBe('50000.00')
        ->and($this->product->fresh()->stock)->toBe(8)
        ->and(CartItem::count())->toBe(0);

    $ledger = WalletTransaction::where('type', 'purchase')->first();
    expect($ledger->amount)->toBe('-50000.00')
        ->and($ledger->balance_before)->toBe('100000.00')
        ->and($ledger->balance_after)->toBe('50000.00')
        ->and($ledger->reference_id)->toBe($order->id);
});

test('saldo kurang: tidak ada yang berubah sama sekali', function () {
    topUp($this->user, 10000);
    CartItem::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'quantity' => 2]);

    $this->actingAs($this->user)->post('/checkout/eats', shipping())->assertSessionHasErrors('checkout');

    expect(Transaction::count())->toBe(0)
        ->and($this->user->fresh()->saldo)->toBe('10000.00')
        ->and($this->product->fresh()->stock)->toBe(10)
        ->and(CartItem::count())->toBe(1)
        ->and(WalletTransaction::where('type', 'purchase')->count())->toBe(0);
});

test('stok tidak cukup ditolak', function () {
    topUp($this->user, 1000000);
    CartItem::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'quantity' => 11]);

    $this->actingAs($this->user)->post('/checkout/eats', shipping())->assertSessionHasErrors('checkout');

    expect(Transaction::count())->toBe(0)->and($this->product->fresh()->stock)->toBe(10);
});

test('harga di transaksi adalah snapshot, tidak ikut berubah', function () {
    topUp($this->user, 100000);
    CartItem::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'quantity' => 1]);
    $this->actingAs($this->user)->post('/checkout/eats', shipping());

    $this->product->update(['price' => 99999, 'name' => 'Diganti']);

    $detail = Transaction::first()->details->first();
    expect($detail->price_at_transaction)->toBe('25000.00')->and($detail->product_name)->toBe('Nasi Goreng');
});

test('keranjang toko lain tidak ikut tercheckout', function () {
    $other = Store::create(['slug' => 'beton', 'name' => 'RAMELA BETON']);
    $semen = Product::create(['store_id' => $other->id, 'name' => 'Semen', 'slug' => 'semen', 'price' => 1000, 'stock' => 5]);
    topUp($this->user, 100000);
    CartItem::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'quantity' => 1]);
    CartItem::create(['user_id' => $this->user->id, 'product_id' => $semen->id, 'quantity' => 1]);

    $this->actingAs($this->user)->post('/checkout/eats', shipping());

    expect(Transaction::first()->store_id)->toBe($this->store->id)
        ->and(CartItem::where('product_id', $semen->id)->exists())->toBeTrue();
});

test('wallet tidak pernah negatif dan nominal nol ditolak', function () {
    $wallet = app(WalletService::class);

    expect(fn() => $wallet->debit($this->user, 1, 'purchase'))
        ->toThrow(\App\Exceptions\InsufficientBalanceException::class);
    expect(fn() => $wallet->credit($this->user, 0, 'topup'))->toThrow(\InvalidArgumentException::class);
    expect($this->user->fresh()->saldo)->toBe('0.00');
});

test('user tidak bisa melihat pesanan atau menghapus keranjang milik orang lain', function () {
    topUp($this->user, 100000);
    CartItem::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'quantity' => 1]);
    $this->actingAs($this->user)->post('/checkout/eats', shipping());
    $invoice = Transaction::first()->invoice_number;

    $intruder = User::factory()->create();
    $intruder->assignRole('pengguna');
    $item = CartItem::create(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'quantity' => 1]);

    $this->actingAs($intruder)->get("/pesanan/{$invoice}")->assertNotFound();
    $this->actingAs($intruder)->delete("/keranjang/{$item->id}")->assertNotFound();
    $this->actingAs($this->user)->get("/pesanan/{$invoice}")->assertOk();
});

test('katalog toko hanya menampilkan produk aktif dan pencarian aman', function () {
    Product::create(['store_id' => $this->store->id, 'name' => 'Disembunyikan', 'slug' => 'x', 'price' => 1, 'stock' => 1, 'is_active' => false]);

    $this->actingAs($this->user)->get('/toko/eats')->assertOk()
        ->assertInertia(fn($p) => $p->component('User/Store')->has('products.data', 1));

    $this->actingAs($this->user)->get('/toko/eats?q=%25')->assertOk()
        ->assertInertia(fn($p) => $p->has('products.data', 0));
});

test('menambah ke keranjang dibatasi stok', function () {
    $this->actingAs($this->user)->post('/keranjang', ['product_id' => $this->product->id, 'quantity' => 500]);

    expect(CartItem::first()->quantity)->toBe(10);
});
