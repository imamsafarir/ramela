<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Store;
use App\Services\PromoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PromoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Promos', [
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'promos' => Promo::with('stores:id,name')->latest()->get()->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'discount_type' => $p->discount_type,
                'discount_value' => $p->discount_value,
                'quota' => $p->quota,
                'used_count' => $p->used_count,
                'valid_until' => $p->valid_until?->format('Y-m-d H:i'),
                'is_active' => $p->is_active,
                'scope' => $p->stores->isEmpty() ? 'Global' : $p->stores->pluck('name')->join(', '),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => PromoService::normalize((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promos', 'code')->whereNull('deleted_at')],
            'discount_type' => ['required', Rule::in(['percent', 'nominal'])],
            'discount_value' => ['required', 'numeric', 'gt:0', $request->input('discount_type') === 'percent' ? 'max:100' : 'max:999999999'],
            'max_discount_amount' => ['nullable', 'numeric', 'gt:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'store_ids' => ['array'],
            'store_ids.*' => ['exists:stores,id'],
        ]);

        $stores = $data['store_ids'] ?? [];
        unset($data['store_ids']);
        $data['min_purchase'] = $data['min_purchase'] ?? 0;

        $promo = Promo::create($data);
        $promo->stores()->sync($stores);

        return back()->with('success', 'Promo dibuat.');
    }

    public function toggle(Promo $promo): RedirectResponse
    {
        $promo->update(['is_active' => ! $promo->is_active]);

        return back()->with('success', 'Status promo diubah.');
    }

    public function destroy(Promo $promo): RedirectResponse
    {
        $promo->delete();

        return back()->with('success', 'Promo dihapus.');
    }

    public function log(Promo $promo): Response
    {
        return Inertia::render('Admin/PromoLog', [
            'promo' => $promo->only('id', 'code'),
            'logs' => $promo->logs()->with(['user:id,username', 'transaction:id,invoice_number'])->latest('used_at')->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'username' => $l->user?->username,
                    'invoice' => $l->transaction?->invoice_number,
                    'discount' => $l->discount_amount,
                    'used_at' => $l->used_at?->format('d M Y H:i'),
                    'cancelled' => (bool) $l->cancelled_at,
                ]),
        ]);
    }
}
