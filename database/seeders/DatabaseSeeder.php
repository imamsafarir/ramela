<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        $stores = [
            ['slug' => 'eats', 'name' => 'RAMELA EATS', 'tagline' => 'Kuliner', 'sort_order' => 1],
            ['slug' => 'hampers', 'name' => 'RAMELA HAMPERS', 'tagline' => 'Bingkisan', 'sort_order' => 2],
            ['slug' => 'beton', 'name' => 'RAMELA BETON', 'tagline' => 'Konstruksi', 'sort_order' => 3],
        ];
        foreach ($stores as $store) {
            Store::updateOrCreate(['slug' => $store['slug']], $store);
        }

        $settings = [
            ['key' => 'feature.blog', 'value' => 'true', 'description' => 'Tampilkan menu Blog'],
            ['key' => 'feature.faq', 'value' => 'true', 'description' => 'Tampilkan menu FAQ'],
            ['key' => 'midtrans.is_production', 'value' => 'false', 'description' => 'true = Production, false = Sandbox'],
            ['key' => 'midtrans.merchant_id', 'value' => null, 'is_encrypted' => true, 'description' => 'Midtrans Merchant ID'],
            ['key' => 'midtrans.client_key', 'value' => null, 'is_encrypted' => true, 'description' => 'Midtrans Client Key'],
            ['key' => 'midtrans.server_key', 'value' => null, 'is_encrypted' => true, 'description' => 'Midtrans Server Key'],
        ];
        foreach ($settings as $setting) {
            WebSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        if (! User::where('username', 'superadmin')->exists()) {
            $password = env('SEED_SUPERADMIN_PASSWORD') ?: Str::password(16, symbols: false);

            User::create(['username' => 'superadmin', 'password' => $password, 'name' => 'Super Admin'])
                ->assignRole(Role::SuperAdmin->value);

            $this->command?->warn("Super Admin dibuat. username: superadmin | password: {$password}");
            $this->command?->warn('Simpan password ini sekarang; tidak akan ditampilkan lagi.');
        }
    }
}
