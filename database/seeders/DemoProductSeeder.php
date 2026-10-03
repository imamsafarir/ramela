<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;

/** Produk contoh untuk pengembangan lokal. Admin akan mengelola produk asli lewat panel (tahap 6). */
class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'eats' => [
                ['Nasi Goreng Spesial', 25000, 50, 'porsi'],
                ['Ayam Bakar Madu', 35000, 30, 'porsi'],
                ['Es Teh Manis', 6000, 100, 'gelas'],
            ],
            'hampers' => [
                ['Hampers Lebaran Premium', 450000, 20, 'paket'],
                ['Hampers Ulang Tahun', 250000, 15, 'paket'],
            ],
            'beton' => [
                ['Beton Ready Mix K-225', 950000, 100, 'm³'],
                ['Beton Ready Mix K-300', 1050000, 100, 'm³'],
            ],
        ];

        foreach ($items as $slug => $products) {
            $store = Store::where('slug', $slug)->first();
            if (! $store) {
                continue;
            }
            foreach ($products as [$name, $price, $stock, $unit]) {
                Product::firstOrCreate(
                    ['slug' => str($name)->slug()->toString()],
                    [
                        'store_id' => $store->id,
                        'name' => $name,
                        'price' => $price,
                        'stock' => $stock,
                        'unit' => $unit,
                        'description' => 'Produk contoh.'
                    ],
                );
            }
        }
    }
}
