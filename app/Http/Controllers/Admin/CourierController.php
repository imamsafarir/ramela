<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\ShippingRate;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->query('status');
        $search = $request->query('q');

        // 1. Data Kurir Terdaftar & Status Aktif Mereka
        $couriers = User::role(Role::Courier->value)
            ->get()
            ->map(function ($u) {
                $activeDelivery = Delivery::with('transaction.store:id,name')
                    ->where('courier_id', $u->id)
                    ->whereIn('status', ['waiting_pickup', 'en_route'])
                    ->first();

                $completedCount = Delivery::where('courier_id', $u->id)
                    ->where('status', 'delivered')
                    ->count();

                return [
                    'id' => $u->id,
                    'username' => $u->username,
                    'name' => $u->name ?: $u->username,
                    'phone' => $u->phone,
                    'is_busy' => (bool) $activeDelivery,
                    'active_task' => $activeDelivery ? [
                        'invoice_number' => $activeDelivery->transaction->invoice_number,
                        'store' => $activeDelivery->transaction->store->name,
                        'status' => $activeDelivery->status,
                        'current_lat' => $activeDelivery->current_lat,
                        'current_lng' => $activeDelivery->current_lng,
                    ] : null,
                    'completed_count' => $completedCount,
                    'last_active_at' => $u->last_active_at?->format('d M Y H:i'),
                ];
            });

        // 2. Monitoring Tugas Pengiriman (Deliveries)
        $deliveries = Delivery::with([
            'transaction.store:id,name',
            'transaction.user:id,username',
            'courier:id,name,username,phone',
            'photos',
        ])
            ->when($statusFilter, fn($q, $s) => $q->where('status', $s))
            ->when($search, function ($q, $t) {
                $like = '%' . addcslashes($t, '%_\\') . '%';
                $q->whereHas('transaction', fn($tr) => $tr->where('invoice_number', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhere('shipping_address', 'like', $like))
                    ->orWhereHas('courier', fn($cr) => $cr->where('username', 'like', $like)
                        ->orWhere('name', 'like', $like));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($d) => [
                'id' => $d->id,
                'invoice_number' => $d->transaction->invoice_number,
                'store' => $d->transaction->store->name,
                'recipient_name' => $d->transaction->recipient_name,
                'recipient_phone' => $d->transaction->recipient_phone,
                'shipping_address' => $d->transaction->shipping_address,
                'shipping_lat' => $d->transaction->shipping_latitude,
                'shipping_lng' => $d->transaction->shipping_longitude,
                'courier' => $d->courier ? [
                    'id' => $d->courier->id,
                    'username' => $d->courier->username,
                    'name' => $d->courier->name ?: $d->courier->username,
                    'phone' => $d->courier->phone,
                ] : null,
                'status' => $d->status,
                'current_lat' => $d->current_lat,
                'current_lng' => $d->current_lng,
                'location_updated_at' => $d->location_updated_at?->format('d M Y H:i'),
                'started_at' => $d->started_at?->format('d M Y H:i'),
                'completed_at' => $d->completed_at?->format('d M Y H:i'),
                'photos' => $d->photos->map(fn($p) => [
                    'type' => $p->type,
                    'url' => Storage::url($p->path),
                    'notes' => $p->notes,
                    'taken_at' => $p->taken_at?->format('d M Y H:i'),
                ]),
            ]);

        // 3. Pesanan Siap Kirim yang Belum Ada Kurir (Ready To Ship)
        $unassignedOrders = Transaction::with('store:id,name')
            ->where('delivery_type', 'courier')
            ->where('status', OrderStatus::ReadyToShip)
            ->where(function ($q) {
                $q->whereDoesntHave('delivery')
                    ->orWhereHas('delivery', fn($d) => $d->whereNull('courier_id'));
            })
            ->latest('id')
            ->get()
            ->map(fn($t) => [
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
                'recipient_name' => $t->recipient_name,
                'recipient_phone' => $t->recipient_phone,
                'shipping_address' => $t->shipping_address,
                'created_at' => $t->created_at->format('d M Y H:i'),
            ]);

        // 4. Ringkasan Metrik
        $stats = [
            'total_couriers' => $couriers->count(),
            'busy_couriers' => $couriers->where('is_busy', true)->count(),
            'idle_couriers' => $couriers->where('is_busy', false)->count(),
            'active_deliveries' => Delivery::whereIn('status', ['waiting_pickup', 'en_route'])->count(),
            'delivered_today' => Delivery::where('status', 'delivered')->whereDate('completed_at', today())->count(),
        ];

        $shippingRates = ShippingRate::orderBy('city_name')->get();

        return Inertia::render('Admin/Couriers', [
            'stats' => $stats,
            'couriers' => $couriers,
            'deliveries' => $deliveries,
            'unassignedOrders' => $unassignedOrders,
            'shippingRates' => $shippingRates,
            'filters' => [
                'status' => $statusFilter,
                'q' => $search,
            ],
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'exists:transactions,invoice_number'],
            'courier_id' => ['required', 'exists:users,id'],
        ]);

        $courier = User::findOrFail($data['courier_id']);
        if (! $courier->hasRole(Role::Courier->value)) {
            return back()->withErrors(['courier_id' => 'Pengguna yang dipilih bukan kurir.']);
        }

        $transaction = Transaction::where('invoice_number', $data['invoice_number'])->firstOrFail();

        Delivery::updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'courier_id' => $courier->id,
                'status' => 'waiting_pickup',
            ]
        );

        return back()->with('success', "Kurir {$courier->username} berhasil ditugaskan untuk mengantar pesanan {$transaction->invoice_number}.");
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'city_name' => ['required', 'string', 'max:255', 'unique:shipping_rates,city_name'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'pricing_type' => ['nullable', 'string', 'in:per_kg,flat'],
            'estimated_delivery' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ShippingRate::create([
            'city_name' => $data['city_name'],
            'shipping_cost' => $data['shipping_cost'],
            'pricing_type' => $data['pricing_type'] ?? 'per_kg',
            'estimated_delivery' => $data['estimated_delivery'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return back()->with('success', 'Tarif ongkir kurir berhasil ditambahkan.');
    }

    public function updateRate(Request $request, ShippingRate $rate): RedirectResponse
    {
        $data = $request->validate([
            'city_name' => ['required', 'string', 'max:255', 'unique:shipping_rates,city_name,' . $rate->id],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'pricing_type' => ['nullable', 'string', 'in:per_kg,flat'],
            'estimated_delivery' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rate->update([
            'city_name' => $data['city_name'],
            'shipping_cost' => $data['shipping_cost'],
            'pricing_type' => $data['pricing_type'] ?? $rate->pricing_type ?? 'per_kg',
            'estimated_delivery' => $data['estimated_delivery'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $rate->is_active,
        ]);

        return back()->with('success', 'Tarif ongkir kurir berhasil diperbarui.');
    }

    public function destroyRate(ShippingRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('success', 'Tarif ongkir kurir berhasil dihapus.');
    }
}
