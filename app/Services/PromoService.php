<?php

namespace App\Services;

use App\Exceptions\PromoException;
use App\Models\Promo;
use App\Models\PromoLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

class PromoService
{
    public static function normalize(string $code): string
    {
        return Str::upper(trim($code));
    }

    /**
     * Validasi promo untuk user + toko + subtotal, lalu hitung potongan.
     * $lock = true (di dalam transaksi checkout) mengunci baris promo agar kuota
     * tidak bisa terlampaui oleh checkout yang berjalan bersamaan.
     *
     * @return array{promo: Promo, discount: string}
     *
     * @throws PromoException
     */
    public function evaluate(string $code, User $user, Store $store, string $subtotal, bool $lock = false): array
    {
        $query = Promo::with('stores:id')->where('code', self::normalize($code));
        $promo = ($lock ? $query->lockForUpdate() : $query)->first();

        if (! $promo || ! $promo->is_active) {
            throw new PromoException('Kode promo tidak valid.');
        }
        if ($promo->starts_at && $promo->starts_at->isFuture()) {
            throw new PromoException('Promo belum dimulai.');
        }
        if ($promo->valid_until && $promo->valid_until->isPast()) {
            throw new PromoException('Promo sudah berakhir.');
        }
        if ($promo->stores->isNotEmpty() && ! $promo->stores->contains('id', $store->id)) {
            throw new PromoException("Promo tidak berlaku untuk {$store->name}.");
        }
        if (bccomp($subtotal, (string) $promo->min_purchase, 2) < 0) {
            throw new PromoException('Minimum belanja promo ini Rp '.number_format((float) $promo->min_purchase, 0, ',', '.').'.');
        }
        if ($promo->quota !== null && $promo->used_count >= $promo->quota) {
            throw new PromoException('Kuota promo sudah habis.');
        }
        if ($promo->per_user_limit !== null) {
            $used = PromoLog::where('promo_id', $promo->id)->where('user_id', $user->id)->whereNull('cancelled_at')->count();
            if ($used >= $promo->per_user_limit) {
                throw new PromoException('Anda sudah mencapai batas pemakaian promo ini.');
            }
        }

        $discount = $this->discount($promo, $subtotal);

        if (bccomp($discount, '0', 2) <= 0) {
            throw new PromoException('Promo tidak memberikan potongan untuk belanja ini.');
        }

        return ['promo' => $promo, 'discount' => $discount];
    }

    /** Potongan tidak pernah melebihi subtotal; persen dibulatkan ke bawah. */
    public function discount(Promo $promo, string $subtotal): string
    {
        if ($promo->discount_type === 'percent') {
            $discount = bcdiv(bcmul($subtotal, (string) $promo->discount_value, 4), '100', 2);

            if ($promo->max_discount_amount !== null && bccomp($discount, (string) $promo->max_discount_amount, 2) > 0) {
                $discount = (string) $promo->max_discount_amount;
            }
        } else {
            $discount = (string) $promo->discount_value;
        }

        return bccomp($discount, $subtotal, 2) > 0 ? bcadd($subtotal, '0', 2) : bcadd($discount, '0', 2);
    }
}
