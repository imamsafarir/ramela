<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = Transaction::whereDate('created_at', today());

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'orders_today' => (clone $today)->count(),
                'revenue_today' => (clone $today)->where('status', '!=', OrderStatus::Cancelled->value)->sum('final_amount'),
                'to_process' => Transaction::where('status', OrderStatus::Paid->value)->count(),
                'to_ship' => Transaction::where('status', OrderStatus::ReadyToShip->value)->count(),
                'to_pickup' => Transaction::where('status', OrderStatus::ReadyForPickup->value)->count(),
                'low_stock' => Product::where('is_active', true)->where('stock', '<=', 5)->count(),
            ],
            'perStore' => Store::orderBy('sort_order')->get(['id', 'name'])->map(fn ($s) => [
                'name' => $s->name,
                'orders_today' => Transaction::where('store_id', $s->id)->whereDate('created_at', today())->count(),
                'to_process' => Transaction::where('store_id', $s->id)->where('status', OrderStatus::Paid->value)->count(),
            ]),
        ]);
    }
}
