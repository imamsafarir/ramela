<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Product;
use App\Models\Promo;
use App\Models\PromoLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalan mengubah status pesanan. Memastikan:
 *  - transisi sah (OrderStatus::allowedNext),
 *  - pembatalan => refund saldo + stok kembali, tepat sekali,
 *  - setiap perubahan tercatat di transaction_status_logs.
 */
class ChangeOrderStatus
{
    public function __construct(private WalletService $wallet) {}

    public function execute(Transaction $transaction, OrderStatus $to, ?User $by = null, ?string $note = null, bool $force = false): Transaction
    {
        return DB::transaction(function () use ($transaction, $to, $by, $note, $force) {
            // Kunci baris agar dua admin tidak mengubah/membatalkan pesanan yang sama bersamaan.
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $force && ! $from->canTransitionTo($to)) {
                throw new CheckoutException("Status tidak bisa diubah dari {$from->label()} ke {$to->label()}.");
            }

            if ($to === OrderStatus::Cancelled && $from !== OrderStatus::Cancelled) {
                $this->refundAndRestock($locked, $by);
            }

            if ($to === OrderStatus::ReadyToShip) {
                \App\Models\Delivery::firstOrCreate(
                    ['transaction_id' => $locked->id],
                    ['status' => 'waiting_pickup']
                );
            }

            $locked->update(['status' => $to]);
            $locked->statusLogs()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $by?->id,
                'note' => $note,
            ]);

            return $locked;
        });
    }

    private function refundAndRestock(Transaction $transaction, ?User $by): void
    {
        // Stok dikembalikan (urut id agar konsisten dengan checkout, mencegah deadlock).
        $details = $transaction->details()->orderBy('product_id')->get();
        foreach ($details as $detail) {
            Product::withTrashed()->whereKey($detail->product_id)->lockForUpdate()->first()
                ?->increment('stock', $detail->quantity);
        }

        $this->wallet->credit(
            $transaction->user, $transaction->final_amount, 'refund', $transaction,
            "Refund {$transaction->invoice_number}", $by,
        );

        $this->restorePromo($transaction);
    }

    private function restorePromo(Transaction $transaction): void
    {
        if (! $transaction->promo_id) {
            return;
        }

        $promo = Promo::withTrashed()->whereKey($transaction->promo_id)->lockForUpdate()->first();
        $log = PromoLog::where('transaction_id', $transaction->id)->whereNull('cancelled_at')->first();

        if ($promo && $log) {
            $log->update(['cancelled_at' => now()]);
            $promo->decrement('used_count');
        }
    }
}
