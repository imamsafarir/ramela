<?php

namespace Database\Seeders;

use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

class ShippingRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            [
                'city_name' => 'Kota Semarang',
                'shipping_cost' => 8000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1 hari kerja (Sameday/Next day)',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kab. Semarang / Ungaran',
                'shipping_cost' => 10000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kota Salatiga',
                'shipping_cost' => 12000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kota Solo / Surakarta',
                'shipping_cost' => 14000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kota Yogyakarta',
                'shipping_cost' => 15000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kab. Sleman',
                'shipping_cost' => 15000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kab. Bantul',
                'shipping_cost' => 16000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kota Magelang',
                'shipping_cost' => 13000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kab. Kudus',
                'shipping_cost' => 12000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kab. Jepara',
                'shipping_cost' => 15000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kab. Demak',
                'shipping_cost' => 10000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kab. Kendal',
                'shipping_cost' => 10000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kota Pekalongan',
                'shipping_cost' => 15000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '1-2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Kota Tegal',
                'shipping_cost' => 18000,
                'pricing_type' => 'per_kg',
                'estimated_delivery' => '2 hari kerja',
                'is_active' => true,
            ],
            [
                'city_name' => 'Promo Flat Seluruh Jawa Tengah',
                'shipping_cost' => 20000,
                'pricing_type' => 'flat',
                'estimated_delivery' => '1-2 hari kerja (Flat rate)',
                'is_active' => true,
            ],
            [
                'city_name' => 'Layanan Instan Express Flat',
                'shipping_cost' => 30000,
                'pricing_type' => 'flat',
                'estimated_delivery' => '3-5 jam sampai (Same Day Flat)',
                'is_active' => true,
            ],
        ];

        foreach ($rates as $r) {
            ShippingRate::updateOrCreate(
                ['city_name' => $r['city_name']],
                $r
            );
        }
    }
}
