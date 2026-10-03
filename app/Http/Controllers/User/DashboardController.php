<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // 5 transaksi terakhir untuk pemantauan instan
        $recentOrders = $user->transactions()
            ->with(['store:id,name', 'delivery:id,transaction_id,status,current_lat,current_lng'])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'invoice_number' => $t->invoice_number,
                'store' => $t->store->name,
                'final_amount' => $t->final_amount,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'created_at' => $t->created_at->toIso8601String(),
                'has_delivery' => (bool) $t->delivery,
            ]);

        $orderStats = [
            'total' => $user->transactions()->count(),
            'shipping' => $user->transactions()->where('status', \App\Enums\OrderStatus::Shipping)->count(),
            'completed' => $user->transactions()->where('status', \App\Enums\OrderStatus::Completed)->count(),
        ];

        $recentWallet = $user->walletTransactions()
            ->latest('id')
            ->limit(5)
            ->get(['type', 'amount', 'note', 'created_at'])
            ->map(fn ($w) => [
                'type' => $w->type,
                'amount' => $w->amount,
                'note' => $w->note,
                'description' => $w->note,
                'created_at' => $w->created_at?->toIso8601String(),
            ]);

        return Inertia::render('User/Dashboard', [
            'lastActive' => $user->last_active_at?->toIso8601String(),
            'profileComplete' => filled($user->name) && filled($user->phone),
            'stores' => \App\Models\Store::where('is_active', true)->orderBy('sort_order')
                ->get(['slug', 'name', 'tagline']),
            'recentWallet' => $recentWallet,
            'recentOrders' => $recentOrders,
            'orderStats' => $orderStats,
        ]);
    }
}
