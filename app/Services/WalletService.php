<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan saldo. Setiap perubahan:
 *  - dalam transaksi DB dengan row lock (aman dari race condition),
 *  - tercatat di ledger wallet_transactions (before/after),
 *  - tidak pernah membuat saldo negatif.
 */
class WalletService
{
    public function credit(User $user, string|int $amount, string $type, ?Model $reference = null, ?string $note = null, ?User $by = null): WalletTransaction
    {
        return $this->apply($user, (string) $amount, $type, $reference, $note, $by);
    }

    public function debit(User $user, string|int $amount, string $type, ?Model $reference = null, ?string $note = null, ?User $by = null): WalletTransaction
    {
        return $this->apply($user, bcmul((string) $amount, '-1', 2), $type, $reference, $note, $by);
    }

    private function apply(User $user, string $signed, string $type, ?Model $reference, ?string $note, ?User $by): WalletTransaction
    {
        if (bccomp($signed, '0', 2) === 0) {
            throw new \InvalidArgumentException('Nominal tidak boleh nol.');
        }

        return DB::transaction(function () use ($user, $signed, $type, $reference, $note, $by) {
            $locked = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $before = (string) $locked->getRawOriginal('saldo');
            $after = bcadd($before, $signed, 2);

            if (bccomp($after, '0', 2) < 0) {
                throw new InsufficientBalanceException;
            }

            $locked->forceFill(['saldo' => $after])->saveQuietly();
            $user->setRawAttributes(array_merge($user->getAttributes(), ['saldo' => $after]), true);

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'amount' => $signed,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => $by?->id,
            ]);
        });
    }
}
