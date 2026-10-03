<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupHistory extends Model
{
    protected $guarded = [];

    protected $hidden = ['snap_token', 'raw_payload'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'raw_payload' => 'array', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
