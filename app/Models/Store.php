<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function getLatitudeAttribute($val): float
    {
        if ($val !== null && $val !== '') {
            return (float) $val;
        }

        return match ($this->slug) {
            'beton' => -6.987540,
            'hampers' => -6.973050,
            default => -6.989720, // eats
        };
    }

    public function getLongitudeAttribute($val): float
    {
        if ($val !== null && $val !== '') {
            return (float) $val;
        }

        return match ($this->slug) {
            'beton' => 110.345020,
            'hampers' => 110.428510,
            default => 110.421930, // eats
        };
    }

    public function getAddressAttribute($val): string
    {
        if ($val !== null && $val !== '') {
            return (string) $val;
        }

        return match ($this->slug) {
            'beton' => 'Kawasan Industri Candi Blok 8 No. 12, Ngaliyan, Kota Semarang',
            'hampers' => 'Jl. Pemuda No. 142, Sekayu, Semarang Tengah, Kota Semarang',
            default => 'Jl. Pandanaran No. 58, Mugassari, Semarang Selatan, Kota Semarang',
        };
    }

    public function products(): HasMany { return $this->hasMany(Product::class); }

    public function categories(): HasMany { return $this->hasMany(Category::class); }
}
