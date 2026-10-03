<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPhoto extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
    }

    public function delivery(): BelongsTo { return $this->belongsTo(Delivery::class); }
}
