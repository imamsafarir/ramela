<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class WebSetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    /** Nilai asli (didekripsi bila perlu). Jangan kirim ke frontend untuk setting rahasia. */
    public function plain(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        return $this->is_encrypted ? Crypt::decryptString($this->value) : $this->value;
    }
}
