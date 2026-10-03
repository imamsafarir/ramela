<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    protected $guarded = [];
    use SoftDeletes;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'meta' => 'array', 'is_active' => 'boolean'];
    }

    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function images(): HasMany { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
}
