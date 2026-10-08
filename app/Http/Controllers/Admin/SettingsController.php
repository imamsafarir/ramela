<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const SECRETS = ['merchant_id', 'client_key', 'server_key'];

    public function edit(SettingsService $settings): Response
    {
        $secretState = [];
        foreach (self::SECRETS as $name) {
            $value = $settings->get("midtrans.$name");
            // Hanya kirim petunjuk (4 karakter terakhir), TIDAK PERNAH nilai penuh.
            $secretState[$name] = $value ? '••••'.substr($value, -4) : null;
        }

        $stores = Store::orderBy('sort_order')->get()->map(fn (Store $s) => [
            'id' => $s->id,
            'slug' => $s->slug,
            'name' => $s->name,
            'tagline' => $s->tagline,
            'address' => $s->address,
            'latitude' => $s->latitude,
            'longitude' => $s->longitude,
            'icon' => $s->icon,
        ]);

        $googleKey = $settings->get('google.maps_api_key') ?: config('services.google.maps_key');

        return Inertia::render('Admin/Settings', [
            'midtrans' => [
                'is_production' => $settings->bool('midtrans.is_production'),
                'secrets' => $secretState,
            ],
            'google' => [
                'has_key' => !empty($googleKey),
                'key_hint' => $googleKey ? '••••'.substr($googleKey, -4) : null,
            ],
            'features' => [
                'blog' => $settings->bool('feature.blog', true),
                'faq' => $settings->bool('feature.faq', true),
            ],
            'stores' => $stores,
            'webhookUrl' => route('midtrans.notification'),
            'urls' => [
                'update' => route('admin.settings.update'),
            ],
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'is_production' => ['required', 'boolean'],
            'merchant_id' => ['nullable', 'string', 'max:100'],
            'client_key' => ['nullable', 'string', 'max:200'],
            'server_key' => ['nullable', 'string', 'max:200'],
            'google_maps_api_key' => ['nullable', 'string', 'max:200'],
            'feature_blog' => ['required', 'boolean'],
            'feature_faq' => ['required', 'boolean'],
            'stores' => ['nullable', 'array'],
            'stores.*.id' => ['required_with:stores', 'integer', 'exists:stores,id'],
            'stores.*.address' => ['required_with:stores', 'string', 'max:500'],
            'stores.*.latitude' => ['required_with:stores', 'numeric', 'between:-90,90'],
            'stores.*.longitude' => ['required_with:stores', 'numeric', 'between:-180,180'],
        ]);

        $settings->set('midtrans.is_production', $data['is_production'] ? 'true' : 'false');
        $settings->set('feature.blog', $data['feature_blog'] ? 'true' : 'false');
        $settings->set('feature.faq', $data['feature_faq'] ? 'true' : 'false');

        if (filled($data['google_maps_api_key'] ?? null)) {
            $settings->set('google.maps_api_key', trim($data['google_maps_api_key']), encrypted: true);
        }

        // Kolom rahasia yang dikosongkan = tidak diubah (nilai lama dipertahankan).
        foreach (self::SECRETS as $name) {
            if (filled($data[$name] ?? null)) {
                $settings->set("midtrans.$name", trim($data[$name]), encrypted: true);
            }
        }

        if (!empty($data['stores'])) {
            foreach ($data['stores'] as $storeData) {
                $store = Store::find($storeData['id']);
                if ($store) {
                    $store->update([
                        'address' => trim($storeData['address']),
                        'latitude' => (float) $storeData['latitude'],
                        'longitude' => (float) $storeData['longitude'],
                    ]);
                }
            }
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
