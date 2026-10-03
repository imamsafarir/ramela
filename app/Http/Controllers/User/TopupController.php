<?php

namespace App\Http\Controllers\User;

use App\Actions\ProcessTopupNotification;
use App\Http\Controllers\Controller;
use App\Models\TopupHistory;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TopupController extends Controller
{
    public const MIN = 10000;

    public const MAX = 10000000;

    public function index(Request $request, MidtransService $midtrans, ProcessTopupNotification $process): Response
    {
        // Setelah kembali dari halaman bayar Midtrans, sinkronkan status (webhook belum tentu sampai di lokal).
        if ($orderId = $request->query('order_id')) {
            $this->trySync($request, (string) $orderId, $midtrans, $process);
        }

        $user = $request->user();

        return Inertia::render('User/Topup', [
            'ready' => $midtrans->isConfigured(),
            'min' => self::MIN,
            'max' => self::MAX,
            'history' => $user->topupHistories()->latest()->limit(25)
                ->get(['midtrans_order_id', 'amount', 'status', 'payment_type', 'created_at']),
            'walletHistory' => $user->walletTransactions()->latest('id')->limit(25)
                ->get(['id', 'type', 'amount', 'note', 'created_at'])
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'type' => $w->type,
                    'amount' => $w->amount,
                    'note' => $w->note,
                    'created_at' => $w->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, MidtransService $midtrans): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:' . self::MIN, 'max:' . self::MAX],
        ]);

        if (! $midtrans->isConfigured()) {
            return back()->withErrors(['amount' => 'Pembayaran belum tersedia. Hubungi admin.']);
        }

        $topup = $request->user()->topupHistories()->create([
            'amount' => $data['amount'],
            'midtrans_order_id' => 'TOPUP-' . $request->user()->id . '-' . now()->format('ymdHis') . '-' . Str::upper(Str::random(4)),
            'status' => 'pending',
        ]);

        try {
            $snap = $midtrans->createSnap($topup, route('topup.index'));
        } catch (RuntimeException $e) {
            $topup->update(['status' => 'failed']);

            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $topup->update(['snap_token' => $snap['token']]);

        // Redirect penuh ke halaman pembayaran Midtrans (di luar SPA).
        return Inertia::location($snap['redirect_url']);
    }

    public function sync(Request $request, string $orderId, MidtransService $midtrans, ProcessTopupNotification $process): RedirectResponse
    {
        $topup = $request->user()->topupHistories()->where('midtrans_order_id', $orderId)->firstOrFail();

        if ($topup->status !== 'pending') {
            return back();
        }

        try {
            $result = $midtrans->status($orderId);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }

        if ($result === null) {
            return back()->with('success', 'Pembayaran belum diterima Midtrans.');
        }

        $process->execute($result);

        return back()->with('success', 'Status diperbarui.');
    }

    private function trySync(Request $request, string $orderId, MidtransService $midtrans, ProcessTopupNotification $process): void
    {
        $topup = $request->user()->topupHistories()
            ->where('midtrans_order_id', $orderId)->where('status', 'pending')->first();

        if (! $topup) {
            return;
        }

        try {
            if ($result = $midtrans->status($orderId)) {
                $process->execute($result);
            }
        } catch (RuntimeException) {
            // diam: user bisa menekan "Cek status" manual
        }
    }
}
