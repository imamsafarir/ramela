<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    protected $guarded = [];
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }

    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}
