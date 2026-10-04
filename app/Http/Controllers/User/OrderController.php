<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()->transactions()
            ->with('store:id,name')
            ->latest()
            ->paginate(10)
            ->through(fn($t) => [
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
                'final_amount' => $t->final_amount,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        return Inertia::render('User/Orders', ['orders' => $orders]);
    }

    public function show(Request $request, string $invoice): Response
    {
        // Dibatasi ke milik user: invoice orang lain => 404
        $t = $request->user()->transactions()
            ->where('invoice_number', $invoice)
            ->with(['store:id,name', 'promo:id,code', 'details', 'statusLogs', 'delivery.courier:id,username,name,phone', 'delivery.photos', 'delivery.locations'])
            ->firstOrFail();

        return Inertia::render('User/OrderShow', [
            'order' => [
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
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
                'shipping_latitude' => $t->shipping_latitude,
                'shipping_longitude' => $t->shipping_longitude,
                'note' => $t->note,
                'created_at' => $t->created_at->toIso8601String(),
                'details' => $t->details->map->only('product_name', 'quantity', 'price_at_transaction', 'subtotal'),
                'delivery' => $t->delivery ? [
                    'status' => $t->delivery->status,
                    'courier_name' => $t->delivery->courier?->name ?: $t->delivery->courier?->username,
                    'courier_phone' => $t->delivery->courier?->phone,
                    'current_lat' => $t->delivery->current_lat,
                    'current_lng' => $t->delivery->current_lng,
                    'location_updated_at' => $t->delivery->location_updated_at?->toIso8601String(),
                    'locations' => $t->delivery->locations()->orderBy('recorded_at')->take(100)->get(['latitude', 'longitude'])->map(fn($l) => [(float) $l->latitude, (float) $l->longitude]),
                    'photos' => $t->delivery->photos->map(fn($p) => [
                        'type' => $p->type,
                        'url' => \Illuminate\Support\Facades\Storage::url($p->path),
                        'taken_at' => $p->taken_at->toIso8601String(),
                    ]),
                ] : null,
                'timeline' => $t->statusLogs->map(fn($l) => [
                    'status' => $l->to_status,
                    'note' => $l->note,
                    'at' => $l->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }
}
