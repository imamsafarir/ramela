<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * `saldo` sengaja TIDAK mass-assignable: hanya berubah lewat ledger (WalletTransaction).
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'email',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'saldo' => 'decimal:2',
            'last_active_at' => 'datetime',
        ];
    }

    /** Role dengan prioritas tertinggi (super_admin > admin > kurir > pengguna). */
    public function primaryRole(): string
    {
        foreach ([Role::SuperAdmin, Role::Admin, Role::Courier] as $role) {
            if ($this->hasRole($role->value)) {
                return $role->value;
            }
        }

        return Role::User->value;
    }

    /** Nama route dashboard sesuai role (user dan admin diarahkan ke dashboard belanja terlebih dahulu). */
    public function homeRoute(): string
    {
        return match ($this->primaryRole()) {
            Role::Courier->value => 'courier.dashboard',
            default => 'dashboard',
        };
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function topupHistories(): HasMany
    {
        return $this->hasMany(TopupHistory::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
