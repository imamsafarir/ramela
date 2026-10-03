<?php

namespace App\Services;

use App\Models\TopupHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Klien Midtrans (Snap + Status API) lewat HTTP biasa; kunci dibaca dari pengaturan web. */
class MidtransService
{
    public function __construct(private SettingsService $settings) {}

    public function isConfigured(): bool
    {
        return $this->serverKey() !== null;
    }

    public function isProduction(): bool
    {
        return $this->settings->bool('midtrans.is_production');
    }

    /** @return array{token:string, redirect_url:string} */
    public function createSnap(TopupHistory $topup, string $finishUrl): array
    {
        $response = $this->http()->post($this->snapUrl() . '/snap/v1/transactions', [
            'transaction_details' => [
                'order_id' => $topup->midtrans_order_id,
                'gross_amount' => (int) $topup->amount,
            ],
            'customer_details' => ['first_name' => $topup->user->username],
            'item_details' => [[
                'id' => 'topup',
                'name' => 'Top-Up Saldo',
                'price' => (int) $topup->amount,
                'quantity' => 1,
            ]],
            'callbacks' => ['finish' => $finishUrl],
        ]);

        if (! $response->successful() || ! $response->json('token')) {
            Log::error('Midtrans createSnap gagal', ['status' => $response->status(), 'body' => $response->json()]);
            throw new RuntimeException('Gagal membuat pembayaran. Silakan coba lagi.');
        }

        return ['token' => $response->json('token'), 'redirect_url' => $response->json('redirect_url')];
    }

    /** Status transaksi dari Midtrans, atau null bila transaksi belum ada di sisi Midtrans. */
    public function status(string $orderId): ?array
    {
        $response = $this->http()->get($this->apiUrl() . '/v2/' . rawurlencode($orderId) . '/status');

        if ($response->status() === 404 || $response->json('status_code') === '404') {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Gagal memeriksa status pembayaran.');
        }

        return $response->json();
    }

    /** Verifikasi signature notifikasi: sha512(order_id + status_code + gross_amount + server_key). */
    public function validSignature(array $payload): bool
    {
        $key = $this->serverKey();

        if (! $key || ! isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key'])) {
            return false;
        }

        $expected = hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $key);

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    private function http()
    {
        $key = $this->serverKey() ?? throw new RuntimeException('Midtrans belum dikonfigurasi.');

        return Http::withBasicAuth($key, '')->acceptJson()->asJson()->timeout(15);
    }

    private function serverKey(): ?string
    {
        return $this->settings->get('midtrans.server_key');
    }

    private function snapUrl(): string
    {
        return $this->isProduction() ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    private function apiUrl(): string
    {
        return $this->isProduction() ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }
}
