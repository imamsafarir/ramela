<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Enums\Role;
use App\Models\Delivery;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /** Status yang boleh diatur Admin. Shipping milik Kurir. */
    private const ADMIN_STATUSES = [
        OrderStatus::Processed,
        OrderStatus::ReadyToShip,
        OrderStatus::ReadyForPickup,
        OrderStatus::Completed,
        OrderStatus::Cancelled,
    ];

    public function index(Request $request): Response
    {
        $sort = in_array($request->query('sort'), ['created_at', 'final_amount'], true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $orders = Transaction::with(['store:id,name', 'user:id,username', 'promo:id,code'])
            ->when($request->query('store'), fn($q, $s) => $q->where('store_id', $s))
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->query('delivery_type'), fn($q, $dt) => $q->where('delivery_type', $dt))
            ->when($request->query('today'), fn($q) => $q->whereDate('created_at', today()))
            ->when($request->query('q'), function ($q, $t) {
                $like = '%' . addcslashes($t, '%_\\') . '%';
                $q->where(fn($w) => $w->where('invoice_number', 'like', $like)
                    ->orWhereHas('user', fn($u) => $u->where('username', 'like', $like)));
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString()
            ->through(fn($t) => [
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
                'username' => $t->user->username,
                'recipient_name' => $t->recipient_name,
                'recipient_phone' => $t->recipient_phone,
                'total_amount' => (string) $t->total_amount,
                'shipping_cost' => (string) $t->shipping_cost,
                'discount_amount' => (string) $t->discount_amount,
                'final_amount' => (string) $t->final_amount,
                'total_weight' => (int) ($t->total_weight ?? 0),
                'promo_code' => $t->promo?->code,
                'delivery_type' => $t->delivery_type ?? 'courier',
                'shipping_city' => $t->shipping_city,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        $stats = [
            'total_orders' => Transaction::count(),
            'today_orders' => Transaction::whereDate('created_at', today())->count(),
            'paid_orders' => Transaction::where('status', OrderStatus::Paid->value)->count(),
            'ready_orders' => Transaction::whereIn('status', [OrderStatus::ReadyToShip->value, OrderStatus::ReadyForPickup->value])->count(),
            'shipping_orders' => Transaction::where('status', OrderStatus::Shipping->value)->count(),
            'completed_orders' => Transaction::where('status', OrderStatus::Completed->value)->count(),
        ];

        return Inertia::render('Admin/Orders', [
            'orders' => $orders,
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'statuses' => collect(OrderStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'filters' => $request->only('store', 'status', 'delivery_type', 'today', 'q', 'sort', 'dir'),
            'stats' => $stats,
        ]);
    }

    public function show(string $invoice): Response
    {
        $t = Transaction::where('invoice_number', $invoice)
            ->with(['store', 'user:id,username,phone', 'promo:id,code', 'details', 'statusLogs.changedBy:id,username', 'delivery.courier:id,username,name,phone', 'delivery.photos', 'delivery.locations'])
            ->firstOrFail();

        $couriers = User::role(Role::Courier->value)
            ->get(['id', 'name', 'username', 'phone'])
            ->map(function ($u) {
                $busy = Delivery::where('courier_id', $u->id)
                    ->whereIn('status', ['waiting_pickup', 'en_route'])
                    ->exists();
                return [
                    'id' => $u->id,
                    'name' => $u->name ?: $u->username,
                    'username' => $u->username,
                    'phone' => $u->phone,
                    'is_busy' => $busy,
                ];
            });

        return Inertia::render('Admin/OrderShow', [
            'order' => [
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
                'store_slug' => $t->store->slug,
                'store_address' => $t->store->address,
                'store_latitude' => (float) $t->store->latitude,
                'store_longitude' => (float) $t->store->longitude,
                'customer' => $t->user->username,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'promo_code' => $t->promo?->code,
                'total_amount' => $t->total_amount,
                'discount_amount' => $t->discount_amount,
                'shipping_cost' => $t->shipping_cost,
                'final_amount' => $t->final_amount,
                'total_weight' => (int) ($t->total_weight ?? 0),
                'delivery_type' => $t->delivery_type ?? 'courier',
                'recipient_name' => $t->recipient_name,
                'recipient_phone' => $t->recipient_phone,
                'shipping_city' => $t->shipping_city,
                'shipping_district' => $t->shipping_district,
                'shipping_postal_code' => $t->shipping_postal_code,
                'shipping_address' => $t->shipping_address,
                'note' => $t->note,
                'shipping_latitude' => $t->shipping_latitude,
                'shipping_longitude' => $t->shipping_longitude,
                'created_at' => $t->created_at->toIso8601String(),
                'details' => $t->details->map->only('product_name', 'quantity', 'price_at_transaction', 'subtotal'),
                'delivery' => $t->delivery ? [
                    'status' => $t->delivery->status,
                    'courier_id' => $t->delivery->courier_id,
                    'courier_name' => $t->delivery->courier?->name ?: $t->delivery->courier?->username,
                    'courier_phone' => $t->delivery->courier?->phone,
                    'current_lat' => $t->delivery->current_lat,
                    'current_lng' => $t->delivery->current_lng,
                    'location_updated_at' => $t->delivery->location_updated_at?->toIso8601String(),
                    'locations' => $t->delivery->locations()->latest('recorded_at')->take(100)->get(['latitude', 'longitude'])->reverse()->values()->map(fn($l) => [(float) $l->latitude, (float) $l->longitude]),
                    'photos' => $t->delivery->photos->map(fn($p) => [
                        'type' => $p->type,
                        'url' => \Illuminate\Support\Facades\Storage::url($p->path),
                        'taken_at' => $p->taken_at->toIso8601String(),
                    ]),
                ] : null,
                'timeline' => $t->statusLogs->map(fn($l) => [
                    'status' => $l->to_status,
                    'note' => $l->note,
                    'by' => $l->changedBy?->username,
                    'at' => $l->created_at->toIso8601String(),
                ]),
            ],
            'couriers' => $couriers,
            'actions' => collect($t->status->allowedNext($t->delivery_type ?? 'courier'))
                ->filter(fn($s) => in_array($s, self::ADMIN_STATUSES, true))
                ->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
        ]);
    }

    public function updateStatus(Request $request, string $invoice, ChangeOrderStatus $change): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn($s) => $s->value, self::ADMIN_STATUSES))],
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
