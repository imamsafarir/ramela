<?php

namespace Database\Seeders;

use App\Models\Promo;
use App\Models\Store;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $eats = Store::where('slug', 'eats')->first();
        $hampers = Store::where('slug', 'hampers')->first();
        $beton = Store::where('slug', 'beton')->first();

        // 1. Promo Global Persen
        $p1 = Promo::updateOrCreate(
            ['code' => 'DISKON10'],
            [
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount_amount' => 50000,
                'min_purchase' => 100000,
                'quota' => 200,
                'per_user_limit' => 3,
                'starts_at' => now()->subDays(10),
                'valid_until' => now()->addMonths(3),
                'is_active' => true,
            ]
        );

        // 2. Promo Nominal Belanja
        $p2 = Promo::updateOrCreate(
            ['code' => 'HEMAT25K'],
            [
                'discount_type' => 'nominal',
                'discount_value' => 25000,
                'max_discount_amount' => null,
                'min_purchase' => 150000,
                'quota' => 100,
                'per_user_limit' => 2,
                'starts_at' => now()->subDays(5),
                'valid_until' => now()->addMonths(2),
                'is_active' => true,
            ]
        );

        // 3. Khusus Hampers
        $p3 = Promo::updateOrCreate(
            ['code' => 'HAMPERS50K'],
            [
                'discount_type' => 'nominal',
                'discount_value' => 50000,
                'max_discount_amount' => null,
                'min_purchase' => 400000,
                'quota' => 80,
                'per_user_limit' => 2,
                'starts_at' => now()->subDays(7),
                'valid_until' => now()->addMonths(2),
                'is_active' => true,
            ]
        );
        if ($hampers) {
            $p3->stores()->sync([$hampers->id]);
        }

        // 4. Khusus Beton
        $p4 = Promo::updateOrCreate(
            ['code' => 'BETONHEMAT'],
            [
                'discount_type' => 'percent',
                'discount_value' => 5,
                'max_discount_amount' => 500000,
                'min_purchase' => 1000000,
                'quota' => 50,
                'per_user_limit' => 1,
                'starts_at' => now()->subDays(15),
                'valid_until' => now()->addMonths(4),
                'is_active' => true,
            ]
        );
        if ($beton) {
            $p4->stores()->sync([$beton->id]);
        }

        // 5. Khusus Eats
        $p5 = Promo::updateOrCreate(
            ['code' => 'KULINER15'],
            [
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_discount_amount' => 30000,
                'min_purchase' => 50000,
                'quota' => 150,
                'per_user_limit' => 3,
                'starts_at' => now()->subDays(10),
                'valid_until' => now()->addMonths(1),
                'is_active' => true,
            ]
        );
        if ($eats) {
            $p5->stores()->sync([$eats->id]);
        }

        // 6. Subsidi Ongkir
        $p6 = Promo::updateOrCreate(
            ['code' => 'GRATISONGKIR'],
            [
                'discount_type' => 'nominal',
                'discount_value' => 15000,
                'max_discount_amount' => null,
                'min_purchase' => 50000,
                'quota' => 250,
                'per_user_limit' => 5,
                'starts_at' => now()->subDays(20),
                'valid_until' => now()->addMonths(3),
                'is_active' => true,
            ]
        );
    }
}
