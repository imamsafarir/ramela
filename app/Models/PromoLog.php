<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoLog extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2', 'used_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function promo(): BelongsTo { return $this->belongsTo(Promo::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
}
