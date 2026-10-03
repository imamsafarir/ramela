<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CatalogSeeder::class,
            ShippingRateSeeder::class,
            PromoSeeder::class,
            OrderSeeder::class,
            ContentSeeder::class,
        ]);

        $this->command?->info('✅ Database RAMELA berhasil diseed dengan ratusan data.');
        $this->command?->info('🔑 Semua akun (Super Admin, Admin, Kurir, Pelanggan) menggunakan password: password');
        $this->command?->info('   - Super Admin : superadmin / password');
        $this->command?->info('   - Admin       : admin, admin2, manajer / password');
        $this->command?->info('   - Kurir       : kurir1, kurir2, kurir3, kurir4, kurir5 / password');
        $this->command?->info('   - Pelanggan   : user, siti, ahmad, dewi, eko, budi, dll. / password');
    }
}
