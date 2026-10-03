<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Admin/Settings', [
            'midtrans' => [
                'is_production' => $settings->bool('midtrans.is_production'),
                'secrets' => $secretState,
            ],
            'features' => [
                'blog' => $settings->bool('feature.blog', true),
                'faq' => $settings->bool('feature.faq', true),
            ],
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
            'feature_blog' => ['required', 'boolean'],
            'feature_faq' => ['required', 'boolean'],
        ]);

        $settings->set('midtrans.is_production', $data['is_production'] ? 'true' : 'false');
        $settings->set('feature.blog', $data['feature_blog'] ? 'true' : 'false');
        $settings->set('feature.faq', $data['feature_faq'] ? 'true' : 'false');

        // Kolom rahasia yang dikosongkan = tidak diubah (nilai lama dipertahankan).
        foreach (self::SECRETS as $name) {
            if (filled($data[$name] ?? null)) {
                $settings->set("midtrans.$name", trim($data[$name]), encrypted: true);
            }
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
