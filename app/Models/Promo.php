<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    protected $guarded = [];
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'starts_at' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Tanpa toko terpilih = promo global. */
    public function stores(): BelongsToMany { return $this->belongsToMany(Store::class, 'promo_store'); }

    public function logs(): HasMany { return $this->hasMany(PromoLog::class); }

    public function isGlobal(): bool { return $this->stores->isEmpty(); }
}
