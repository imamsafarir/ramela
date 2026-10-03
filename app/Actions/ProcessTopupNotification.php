<?php

namespace App\Actions;

use App\Models\TopupHistory;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Terapkan hasil pembayaran Midtrans ke top-up. Dipakai webhook DAN sinkron manual.
 * Idempoten: top-up yang sudah final tidak diproses lagi (saldo tak pernah dobel).
 */
class ProcessTopupNotification
{
    public function __construct(private WalletService $wallet) {}

    public function execute(array $payload): ?TopupHistory
    {
        return DB::transaction(function () use ($payload) {
            $topup = TopupHistory::where('midtrans_order_id', (string) ($payload['order_id'] ?? ''))
                ->lockForUpdate()->first();

            if (! $topup || $topup->status !== 'pending') {
                return $topup;
            }

            if (bccomp((string) ($payload['gross_amount'] ?? '0'), (string) $topup->amount, 2) !== 0) {
                Log::warning('Midtrans: nominal tidak cocok', ['order_id' => $topup->midtrans_order_id]);

                return $topup;
            }

            $status = $this->mapStatus($payload);

            if ($status === null) {
                return $topup; // masih pending
            }

            $topup->update([
                'status' => $status,
                'payment_type' => $payload['payment_type'] ?? null,
                'raw_payload' => $payload,
                'paid_at' => $status === 'success' ? now() : null,
            ]);

            if ($status === 'success') {
                $this->wallet->credit($topup->user, $topup->amount, 'topup', $topup, "Top-up {$topup->midtrans_order_id}");
            }

            return $topup;
        });
    }

    /** @return 'success'|'failed'|'expired'|null  null = masih menunggu */
    private function mapStatus(array $payload): ?string
    {
        return match ($payload['transaction_status'] ?? null) {
            'settlement' => 'success',
            'capture' => ($payload['fraud_status'] ?? 'accept') === 'accept' ? 'success' : 'failed',
            'deny', 'cancel', 'failure' => 'failed',
            'expire' => 'expired',
            default => null,
        };
    }
}
