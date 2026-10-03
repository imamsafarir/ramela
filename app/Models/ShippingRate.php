<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_name',
        'shipping_cost',
        'pricing_type',
        'estimated_delivery',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
