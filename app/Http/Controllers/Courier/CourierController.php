<?php

namespace App\Http\Controllers\Courier;

use App\Actions\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\DeliveryPhoto;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Tugas aktif kurir saat ini (sedang berjalan / belum selesai)
        $activeDelivery = Delivery::with(['transaction.store:id,name', 'transaction.details', 'photos'])
            ->where('courier_id', $user->id)
            ->whereIn('status', ['waiting_pickup', 'en_route'])
            ->first();

        // Daftar pesanan siap dikirim (belum diambil siapa pun)
        $availableOrders = Transaction::with(['store:id,name', 'details'])
            ->where('status', OrderStatus::ReadyToShip)
            ->where(function ($q) {
                $q->whereDoesntHave('delivery')
                  ->orWhereHas('delivery', fn ($d) => $d->whereNull('courier_id'));
            })
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
                'recipient_name' => $t->recipient_name,
                'recipient_phone' => $t->recipient_phone,
                'shipping_address' => $t->shipping_address,
                'items_count' => $t->details->count(),
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        // Riwayat pengantaran yang diselesaikan oleh kurir ini
        $history = Delivery::with(['transaction.store:id,name'])
            ->where('courier_id', $user->id)
            ->where('status', 'delivered')
            ->latest('completed_at')
            ->take(10)
            ->get()
            ->map(fn ($d) => [
                'invoice_number' => $d->transaction->invoice_number,
                'store' => $d->transaction->store->name,
                'recipient_name' => $d->transaction->recipient_name,
                'shipping_address' => $d->transaction->shipping_address,
                'completed_at' => $d->completed_at?->toIso8601String(),
            ]);

        return Inertia::render('Courier/Dashboard', [
            'activeDelivery' => $activeDelivery ? [
                'id' => $activeDelivery->id,
                'status' => $activeDelivery->status,
                'current_lat' => $activeDelivery->current_lat,
                'current_lng' => $activeDelivery->current_lng,
                'location_updated_at' => $activeDelivery->location_updated_at?->toIso8601String(),
                'started_at' => $activeDelivery->started_at?->toIso8601String(),
                'transaction' => [
                    'invoice_number' => $activeDelivery->transaction->invoice_number,
                    'store' => $activeDelivery->transaction->store->name,
                    'recipient_name' => $activeDelivery->transaction->recipient_name,
                    'recipient_phone' => $activeDelivery->transaction->recipient_phone,
                    'shipping_address' => $activeDelivery->transaction->shipping_address,
                    'shipping_latitude' => $activeDelivery->transaction->shipping_latitude,
                    'shipping_longitude' => $activeDelivery->transaction->shipping_longitude,
                    'note' => $activeDelivery->transaction->note,
                    'details' => $activeDelivery->transaction->details->map->only('product_name', 'quantity'),
                ],
                'photos' => $activeDelivery->photos->map(fn ($p) => [
                    'type' => $p->type,
                    'url' => Storage::url($p->path),
                    'taken_at' => $p->taken_at->toIso8601String(),
                ]),
            ] : null,
            'availableOrders' => $availableOrders,
            'history' => $history,
        ]);
    }

    public function claim(Request $request, string $invoice): RedirectResponse
    {
        $user = $request->user();

        // Pastikan kurir tidak memiliki tugas lain yang sedang aktif
        $hasActive = Delivery::where('courier_id', $user->id)
            ->whereIn('status', ['waiting_pickup', 'en_route'])
            ->exists();

        if ($hasActive) {
            return back()->withErrors(['error' => 'Anda masih memiliki tugas pengantaran yang sedang berjalan.']);
        }

        $transaction = Transaction::where('invoice_number', $invoice)->firstOrFail();

        if ($transaction->status !== OrderStatus::ReadyToShip) {
            return back()->withErrors(['error' => 'Pesanan tidak dalam status siap dikirim.']);
        }

        try {
            DB::transaction(function () use ($transaction, $user) {
                $delivery = Delivery::firstOrCreate(
                    ['transaction_id' => $transaction->id],
                    ['status' => 'waiting_pickup']
                );

                // Kunci baris agar tidak diklaim kurir lain secara bersamaan
                $lockedDelivery = Delivery::whereKey($delivery->id)->lockForUpdate()->first();
                if ($lockedDelivery->courier_id && $lockedDelivery->courier_id !== $user->id) {
                    throw new CheckoutException('Pesanan ini sudah diambil kurir lain.');
                }

                $lockedDelivery->update([
                    'courier_id' => $user->id,
                ]);
            });
        } catch (CheckoutException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Tugas pengantaran berhasil diambil.');
    }

    public function pickup(Request $request, ChangeOrderStatus $change): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // maks 5MB
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();

        $delivery = Delivery::with('transaction')
            ->where('courier_id', $user->id)
            ->where('status', 'waiting_pickup')
            ->firstOrFail();

        $path = $request->file('photo')->store('deliveries/pickup', 'public');

        DB::transaction(function () use ($delivery, $path, $request, $user, $change) {
            DeliveryPhoto::updateOrCreate(
                ['delivery_id' => $delivery->id, 'type' => 'pickup'],
                [
                    'path' => $path,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'taken_at' => now(),
                ]
            );

            $delivery->update([
                'status' => 'en_route',
                'started_at' => now(),
                'current_lat' => $request->latitude ?? $delivery->current_lat,
                'current_lng' => $request->longitude ?? $delivery->current_lng,
                'location_updated_at' => now(),
            ]);

            if ($request->filled('latitude') && $request->filled('longitude')) {
                DeliveryLocation::create([
                    'delivery_id' => $delivery->id,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'recorded_at' => now(),
                ]);
            }

            // Ubah status pesanan menjadi shipping
            $change->execute($delivery->transaction, OrderStatus::Shipping, $user, 'Barang diambil oleh kurir, sedang diantar');
        });

        return back()->with('success', 'Pengantaran dimulai. Mohon aktifkan GPS selama perjalanan.');
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();

        $delivery = Delivery::where('courier_id', $user->id)
            ->where('status', 'en_route')
            ->first();

        if (! $delivery) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $delivery->update([
            'current_lat' => $data['latitude'],
            'current_lng' => $data['longitude'],
            'location_updated_at' => now(),
        ]);

        DeliveryLocation::create([
            'delivery_id' => $delivery->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'recorded_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function dropoff(Request $request, ChangeOrderStatus $change): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();

        $delivery = Delivery::with('transaction')
            ->where('courier_id', $user->id)
            ->where('status', 'en_route')
            ->firstOrFail();

        $path = $request->file('photo')->store('deliveries/dropoff', 'public');

        DB::transaction(function () use ($delivery, $path, $request, $user, $change) {
            DeliveryPhoto::updateOrCreate(
                ['delivery_id' => $delivery->id, 'type' => 'dropoff'],
                [
                    'path' => $path,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'taken_at' => now(),
                ]
            );

            $delivery->update([
                'status' => 'delivered',
                'completed_at' => now(),
                'current_lat' => $request->latitude ?? $delivery->current_lat,
                'current_lng' => $request->longitude ?? $delivery->current_lng,
                'location_updated_at' => now(),
            ]);

            if ($request->filled('latitude') && $request->filled('longitude')) {
                DeliveryLocation::create([
                    'delivery_id' => $delivery->id,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'recorded_at' => now(),
                ]);
            }

            // Ubah status pesanan menjadi completed
            $change->execute($delivery->transaction, OrderStatus::Completed, $user, 'Barang telah diterima oleh pelanggan');
        });

        return back()->with('success', 'Pesanan selesai diantar. Terima kasih atas kerja keras Anda!');
    }
}
