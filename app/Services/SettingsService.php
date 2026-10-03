<?php

namespace App\Services;

use App\Models\WebSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Akses pengaturan web (key-value) dengan cache. Nilai rahasia disimpan terenkripsi.
 * Sengaja tidak ada method yang mengirim nilai rahasia ke frontend.
 */
class SettingsService
{
    private const CACHE_KEY = 'web_settings.v1';

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->all()[$key] ?? null;

        if (! $row || $row['value'] === null || $row['value'] === '') {
            return $default;
        }

        if (! $row['encrypted']) {
            return $row['value'];
        }

        try {
            return Crypt::decryptString($row['value']);
        } catch (DecryptException) {
            return $default; // APP_KEY berubah / data rusak
        }
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function set(string $key, ?string $value, ?bool $encrypted = null, ?string $description = null): void
    {
        $setting = WebSetting::firstOrNew(['key' => $key]);

        if ($encrypted !== null) {
            $setting->is_encrypted = $encrypted;
        }
        if ($description !== null) {
            $setting->description = $description;
        }

        $setting->value = ($value !== null && $value !== '' && $setting->is_encrypted)
            ? Crypt::encryptString($value)
            : $value;
        $setting->save();

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array{value: ?string, encrypted: bool}> */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn() => WebSetting::query()->get()
            ->mapWithKeys(fn($s) => [$s->key => [
                'value' => $s->getAttributes()['value'] ?? null,
                'encrypted' => (bool) $s->is_encrypted,
            ]])->all());
    }
}
