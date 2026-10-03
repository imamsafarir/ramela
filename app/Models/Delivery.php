<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_lat' => 'float',
            'current_lng' => 'float',
            'location_updated_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }

    public function courier(): BelongsTo { return $this->belongsTo(User::class, 'courier_id'); }

    public function photos(): HasMany { return $this->hasMany(DeliveryPhoto::class); }

    public function locations(): HasMany { return $this->hasMany(DeliveryLocation::class); }
}
