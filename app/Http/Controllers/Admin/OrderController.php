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

class OrderController extends Controller
{
    /** Status yang boleh diatur Admin. Shipping/Completed milik Kurir. */
    private const ADMIN_STATUSES = [OrderStatus::Processed, OrderStatus::ReadyToShip, OrderStatus::Cancelled];

    public function index(Request $request): Response
    {
        $sort = in_array($request->query('sort'), ['created_at', 'final_amount'], true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $orders = Transaction::with('store:id,name', 'user:id,username')
            ->when($request->query('store'), fn ($q, $s) => $q->where('store_id', $s))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('today'), fn ($q) => $q->whereDate('created_at', today()))
            ->when($request->query('q'), function ($q, $t) {
                $like = '%'.addcslashes($t, '%_\\').'%';
                $q->where(fn ($w) => $w->where('invoice_number', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)));
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($t) => [
                'invoice_number' => $t->invoice_number, 'store' => $t->store->name, 'username' => $t->user->username,
                'final_amount' => $t->final_amount, 'status' => $t->status->value, 'status_label' => $t->status->label(),
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Orders', [
            'orders' => $orders,
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'filters' => $request->only('store', 'status', 'today', 'q', 'sort', 'dir'),
        ]);
    }

    public function show(string $invoice): Response
    {
        $t = Transaction::where('invoice_number', $invoice)
            ->with(['store:id,name', 'user:id,username,phone', 'promo:id,code', 'details', 'statusLogs.changedBy:id,username', 'delivery.courier:id,username', 'delivery.photos'])
            ->firstOrFail();

        return Inertia::render('Admin/OrderShow', [
            'order' => [
                'invoice_number' => $t->invoice_number, 'store' => $t->store->name,
                'customer' => $t->user->username,
                'status' => $t->status->value, 'status_label' => $t->status->label(),
                'promo_code' => $t->promo?->code,
                'total_amount' => $t->total_amount, 'discount_amount' => $t->discount_amount, 'final_amount' => $t->final_amount,
                'recipient_name' => $t->recipient_name, 'recipient_phone' => $t->recipient_phone,
                'shipping_address' => $t->shipping_address, 'note' => $t->note,
                'shipping_latitude' => $t->shipping_latitude, 'shipping_longitude' => $t->shipping_longitude,
                'created_at' => $t->created_at->toIso8601String(),
                'details' => $t->details->map->only('product_name', 'quantity', 'price_at_transaction', 'subtotal'),
                'delivery' => $t->delivery ? [
                    'status' => $t->delivery->status,
                    'courier_name' => $t->delivery->courier?->username,
                    'current_lat' => $t->delivery->current_lat,
                    'current_lng' => $t->delivery->current_lng,
                    'location_updated_at' => $t->delivery->location_updated_at?->toIso8601String(),
                    'photos' => $t->delivery->photos->map(fn ($p) => [
                        'type' => $p->type,
                        'url' => \Illuminate\Support\Facades\Storage::url($p->path),
                        'taken_at' => $p->taken_at->toIso8601String(),
                    ]),
                ] : null,
                'timeline' => $t->statusLogs->map(fn ($l) => [
                    'status' => $l->to_status, 'note' => $l->note, 'by' => $l->changedBy?->username,
                    'at' => $l->created_at->toIso8601String(),
                ]),
            ],
            'actions' => collect($t->status->allowedNext())
                ->filter(fn ($s) => in_array($s, self::ADMIN_STATUSES, true))
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
        ]);
    }

    public function updateStatus(Request $request, string $invoice, ChangeOrderStatus $change): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, self::ADMIN_STATUSES))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = Transaction::where('invoice_number', $invoice)->firstOrFail();

        try {
            $change->execute($transaction, OrderStatus::from($data['status']), $request->user(), $data['note'] ?? null);
        } catch (CheckoutException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Status pesanan diperbarui.');
    }
}
