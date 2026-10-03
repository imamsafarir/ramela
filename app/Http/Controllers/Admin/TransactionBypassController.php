<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionBypassController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('q');
        $status = $request->query('status');
        $storeId = $request->query('store');

        $transactions = Transaction::with(['user:id,username', 'store:id,name'])
            ->when($search, function ($q, $t) {
                $like = '%'.addcslashes($t, '%_\\').'%';
                $q->where('invoice_number', 'like', $like)
                  ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like));
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($storeId, fn ($q, $s) => $q->where('store_id', $s))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($t) => [
                'id' => $t->id,
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
                'username' => $t->user->username,
                'final_amount' => $t->final_amount,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'created_at' => $t->created_at->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Transactions', [
            'transactions' => $transactions,
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'store' => $storeId,
            ],
            'urls' => [
                'base' => url('/admin'),
            ],
        ]);
    }

    public function forceStatus(Request $request, string $invoice, ChangeOrderStatus $change): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, OrderStatus::cases()))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = Transaction::where('invoice_number', $invoice)->firstOrFail();

        try {
            $change->execute(
                $transaction,
                OrderStatus::from($data['status']),
                $request->user(),
                $data['note'] ?: 'Bypass status paksa oleh Administrator',
                force: true
            );
        } catch (CheckoutException $e) {
            return back()->withErrors(['bypass' => $e->getMessage()]);
        }

        return back()->with('success', "Status transaksi {$invoice} berhasil diubah paksa ke {$data['status']}.");
    }
}
