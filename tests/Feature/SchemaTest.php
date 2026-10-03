<?php

use App\Enums\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('saldo tidak bisa diisi lewat mass assignment', function () {
    $user = User::create(['username' => 'budi', 'password' => 'rahasia123', 'saldo' => 999999]);

    expect($user->fresh()->saldo)->toBe('0.00');
});

test('user baru hanya butuh username dan password', function () {
    $user = User::create(['username' => 'ani', 'password' => 'rahasia123']);

    expect($user->name)->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->password)->not->toBe('rahasia123');
});

test('transisi status pesanan mengikuti aturan', function () {
    expect(OrderStatus::Paid->canTransitionTo(OrderStatus::Processed))->toBeTrue()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Completed))->toBeFalse()
        ->and(OrderStatus::Completed->allowedNext())->toBe([]);
});

test('toko bisa ditambah sebagai data tanpa migrasi', function () {
    Store::create(['slug' => 'baru', 'name' => 'RAMELA BARU']);

    expect(Store::where('slug', 'baru')->exists())->toBeTrue();
});
