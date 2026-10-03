<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Address;
use App\Models\TopupHistory;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        // 1. Super Admin
        $superAdmin = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@ramela.test',
                'phone' => '081211110000',
                'password' => 'password',
            ]
        );
        $superAdmin->syncRoles([Role::SuperAdmin->value]);

        // 2. Admins
        $admins = [
            ['username' => 'admin', 'name' => 'Admin Utama RAMELA', 'email' => 'admin@ramela.test', 'phone' => '081222220001'],
            ['username' => 'admin2', 'name' => 'Admin Operasional 2', 'email' => 'admin2@ramela.test', 'phone' => '081222220002'],
            ['username' => 'manajer', 'name' => 'Manajer Toko RAMELA', 'email' => 'manajer@ramela.test', 'phone' => '081222220003'],
        ];

        foreach ($admins as $adm) {
            $user = User::updateOrCreate(
                ['username' => $adm['username']],
                [
                    'name' => $adm['name'],
                    'email' => $adm['email'],
                    'phone' => $adm['phone'],
                    'password' => 'password',
                ]
            );
            $user->syncRoles([Role::Admin->value]);
        }

        // 3. Couriers
        $couriers = [
            ['username' => 'kurir1', 'name' => 'Budi Kurir Ekspres', 'email' => 'kurir1@ramela.test', 'phone' => '081333330001'],
            ['username' => 'kurir2', 'name' => 'Agus Kurir Cepat', 'email' => 'kurir2@ramela.test', 'phone' => '081333330002'],
            ['username' => 'kurir3', 'name' => 'Joko Kurir Kilat', 'email' => 'kurir3@ramela.test', 'phone' => '081333330003'],
            ['username' => 'kurir4', 'name' => 'Doni Kurir Ramela', 'email' => 'kurir4@ramela.test', 'phone' => '081333330004'],
            ['username' => 'kurir5', 'name' => 'Hendra Kurir Cargo', 'email' => 'kurir5@ramela.test', 'phone' => '081333330005'],
        ];

        foreach ($couriers as $cr) {
            $user = User::updateOrCreate(
                ['username' => $cr['username']],
                [
                    'name' => $cr['name'],
                    'email' => $cr['email'],
                    'phone' => $cr['phone'],
                    'password' => 'password',
                ]
            );
            $user->syncRoles([Role::Courier->value]);
        }

        // 4. Customers (User)
        $customers = [
            [
                'username' => 'user', 'name' => 'Pelanggan Ramela Demo', 'email' => 'user@ramela.test', 'phone' => '081234567890', 'saldo' => 15000000,
                'addresses' => [
                    ['label' => 'Rumah Utama', 'recipient' => 'Pelanggan Ramela Demo', 'phone' => '081234567890', 'address' => 'Jl. Pandanaran No. 58, Mugassari, Kec. Semarang Selatan, Kota Semarang', 'lat' => -6.992440, 'lng' => 110.428450, 'default' => true],
                    ['label' => 'Kantor Cabang', 'recipient' => 'Pelanggan Ramela (Kantor)', 'phone' => '081234567890', 'address' => 'Gedung Grinatha Lt. 3, Jl. Pemuda No. 142, Kota Semarang', 'lat' => -6.982000, 'lng' => 110.409000, 'default' => false],
                ],
            ],
            [
                'username' => 'siti', 'name' => 'Siti Rahmawati', 'email' => 'siti@ramela.test', 'phone' => '081234567891', 'saldo' => 5000000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Siti Rahmawati', 'phone' => '081234567891', 'address' => 'Jl. Pahlawan No. 12, Pleburan, Semarang Selatan, Kota Semarang', 'lat' => -7.005140, 'lng' => 110.438120, 'default' => true],
                ],
            ],
            [
                'username' => 'ahmad', 'name' => 'Ahmad Fauzi', 'email' => 'ahmad@ramela.test', 'phone' => '081234567892', 'saldo' => 7500000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Ahmad Fauzi', 'phone' => '081234567892', 'address' => 'Jl. Diponegoro No. 88, Ungaran Barat, Kab. Semarang / Ungaran', 'lat' => -7.139620, 'lng' => 110.403810, 'default' => true],
                    ['label' => 'Toko Alat Bangunan', 'recipient' => 'TB Berkah Mandiri', 'phone' => '081234567892', 'address' => 'Jl. Gatot Subroto No. 15, Ungaran Barat, Kab. Semarang / Ungaran', 'lat' => -7.135000, 'lng' => 110.402000, 'default' => false],
                ],
            ],
            [
                'username' => 'dewi', 'name' => 'Dewi Lestari', 'email' => 'dewi@ramela.test', 'phone' => '081234567893', 'saldo' => 4000000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Dewi Lestari', 'phone' => '081234567893', 'address' => 'Jl. Jenderal Sudirman No. 45, Sidorejo, Kota Salatiga', 'lat' => -7.330520, 'lng' => 110.508430, 'default' => true],
                ],
            ],
            [
                'username' => 'eko', 'name' => 'Eko Prasetyo', 'email' => 'eko@ramela.test', 'phone' => '081234567894', 'saldo' => 8500000,
                'addresses' => [
                    ['label' => 'Rumah Tinggal', 'recipient' => 'Eko Prasetyo', 'phone' => '081234567894', 'address' => 'Jl. Slamet Riyadi No. 210, Banjarsari, Kota Solo / Surakarta', 'lat' => -7.566600, 'lng' => 110.826700, 'default' => true],
                ],
            ],
            [
                'username' => 'budi', 'name' => 'Budi Santoso', 'email' => 'budi@ramela.test', 'phone' => '081234567895', 'saldo' => 6000000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Budi Santoso', 'phone' => '081234567895', 'address' => 'Jl. Gajahmada No. 88, Kembangsari, Kota Semarang', 'lat' => -6.982000, 'lng' => 110.420000, 'default' => true],
                ],
            ],
            [
                'username' => 'rina', 'name' => 'Rina Anggraini', 'email' => 'rina@ramela.test', 'phone' => '081234567896', 'saldo' => 4200000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Rina Anggraini', 'phone' => '081234567896', 'address' => 'Jl. Soekarno-Hatta No. 34, Pegandon, Kab. Kendal', 'lat' => -6.924700, 'lng' => 110.203600, 'default' => true],
                ],
            ],
            [
                'username' => 'bambang', 'name' => 'Bambang Wijaya', 'email' => 'bambang@ramela.test', 'phone' => '081234567897', 'saldo' => 20000000,
                'addresses' => [
                    ['label' => 'Gudang Konstruksi', 'recipient' => 'PT Cipta Beton Perkasa', 'phone' => '081234567897', 'address' => 'Kawasan Industri Candi Blok 8 No. 12, Ngaliyan, Kota Semarang', 'lat' => -7.025000, 'lng' => 110.420000, 'default' => true],
                    ['label' => 'Rumah Tinggal', 'recipient' => 'Bambang Wijaya', 'phone' => '081234567897', 'address' => 'Jl. Papandayan No. 9, Gajahmungkur, Kota Semarang', 'lat' => -7.012000, 'lng' => 110.415000, 'default' => false],
                ],
            ],
            [
                'username' => 'maya', 'name' => 'Maya Putri', 'email' => 'maya@ramela.test', 'phone' => '081234567898', 'saldo' => 6500000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Maya Putri', 'phone' => '081234567898', 'address' => 'Jl. Sultan Fatah No. 19, Bintoro, Kab. Demak', 'lat' => -6.894400, 'lng' => 110.638400, 'default' => true],
                ],
            ],
            [
                'username' => 'reza', 'name' => 'Reza Pratama', 'email' => 'reza@ramela.test', 'phone' => '081234567899', 'saldo' => 3500000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Reza Pratama', 'phone' => '081234567899', 'address' => 'Jl. Malioboro No. 56, Gedongtengen, Kota Yogyakarta', 'lat' => -7.795600, 'lng' => 110.369500, 'default' => true],
                ],
            ],
            [
                'username' => 'dian', 'name' => 'Dian Sastrowardoyo', 'email' => 'dian@ramela.test', 'phone' => '081234567800', 'saldo' => 9800000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Dian Sastrowardoyo', 'phone' => '081234567800', 'address' => 'Jl. Kaliurang KM 6.5, Sinduadi, Mlati, Kab. Sleman', 'lat' => -7.768100, 'lng' => 110.378000, 'default' => true],
                ],
            ],
            [
                'username' => 'andi', 'name' => 'Andi Setiawan', 'email' => 'andi@ramela.test', 'phone' => '081234567801', 'saldo' => 5200000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Andi Setiawan', 'phone' => '081234567801', 'address' => 'Jl. Tidar No. 28, Magelang Selatan, Kota Magelang', 'lat' => -7.470500, 'lng' => 110.217800, 'default' => true],
                ],
            ],
            [
                'username' => 'fitri', 'name' => 'Fitri Handayani', 'email' => 'fitri@ramela.test', 'phone' => '081234567802', 'saldo' => 3800000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Fitri Handayani', 'phone' => '081234567802', 'address' => 'Jl. Sunan Muria No. 15, Demaan, Kota Kudus, Kab. Kudus', 'lat' => -6.804800, 'lng' => 110.840500, 'default' => true],
                ],
            ],
            [
                'username' => 'gilang', 'name' => 'Gilang Ramadhan', 'email' => 'gilang@ramela.test', 'phone' => '081234567803', 'saldo' => 7000000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Gilang Ramadhan', 'phone' => '081234567803', 'address' => 'Jl. Kartini No. 44, Panggang, Kab. Jepara', 'lat' => -6.592700, 'lng' => 110.669800, 'default' => true],
                ],
            ],
            [
                'username' => 'indah', 'name' => 'Indah Permatasari', 'email' => 'indah@ramela.test', 'phone' => '081234567804', 'saldo' => 8000000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Indah Permatasari', 'phone' => '081234567804', 'address' => 'Jl. Hayam Wuruk No. 89, Pekalongan Barat, Kota Pekalongan', 'lat' => -6.888600, 'lng' => 109.675300, 'default' => true],
                ],
            ],
            [
                'username' => 'wahyu', 'name' => 'Wahyu Hidayat', 'email' => 'wahyu@ramela.test', 'phone' => '081234567805', 'saldo' => 6800000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Wahyu Hidayat', 'phone' => '081234567805', 'address' => 'Jl. Gajah Mada No. 102, Tegal Barat, Kota Tegal', 'lat' => -6.869400, 'lng' => 109.140200, 'default' => true],
                ],
            ],
            [
                'username' => 'putra', 'name' => 'Putra Mahardika', 'email' => 'putra@ramela.test', 'phone' => '081234567806', 'saldo' => 12500000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Putra Mahardika', 'phone' => '081234567806', 'address' => 'Bukit Sari Raya No. 7, Banyumanik, Kota Semarang', 'lat' => -7.011200, 'lng' => 110.418900, 'default' => true],
                ],
            ],
            [
                'username' => 'anisa', 'name' => 'Anisa Rahmawati', 'email' => 'anisa@ramela.test', 'phone' => '081234567807', 'saldo' => 4500000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Anisa Rahmawati', 'phone' => '081234567807', 'address' => 'Jl. MT Haryono No. 512, Jagalan, Kota Semarang', 'lat' => -6.988000, 'lng' => 110.435000, 'default' => true],
                ],
            ],
            [
                'username' => 'fajar', 'name' => 'Fajar Nugroho', 'email' => 'fajar@ramela.test', 'phone' => '081234567808', 'saldo' => 5500000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Fajar Nugroho', 'phone' => '081234567808', 'address' => 'Jl. Urip Sumoharjo No. 77, Jebres, Kota Solo / Surakarta', 'lat' => -7.558000, 'lng' => 110.814000, 'default' => true],
                ],
            ],
            [
                'username' => 'nurul', 'name' => 'Nurul Aini', 'email' => 'nurul@ramela.test', 'phone' => '081234567809', 'saldo' => 3200000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Nurul Aini', 'phone' => '081234567809', 'address' => 'Jl. Bantul KM 7.5, Pendowoharjo, Sewon, Kab. Bantul', 'lat' => -7.886000, 'lng' => 110.328000, 'default' => true],
                ],
            ],
            [
                'username' => 'bayu', 'name' => 'Bayu Saputra', 'email' => 'bayu@ramela.test', 'phone' => '081234567810', 'saldo' => 9000000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Bayu Saputra', 'phone' => '081234567810', 'address' => 'Jl. Veteran No. 14, Gajahmungkur, Kota Semarang', 'lat' => -6.998000, 'lng' => 110.412000, 'default' => true],
                ],
            ],
            [
                'username' => 'rizky', 'name' => 'Rizky Pratama', 'email' => 'rizky@ramela.test', 'phone' => '081234567811', 'saldo' => 7800000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Rizky Pratama', 'phone' => '081234567811', 'address' => 'Jl. Setiabudi No. 115, Srondol Kulon, Banyumanik, Kota Semarang', 'lat' => -7.034000, 'lng' => 110.445000, 'default' => true],
                ],
            ],
            [
                'username' => 'tiara', 'name' => 'Tiara Andini', 'email' => 'tiara@ramela.test', 'phone' => '081234567812', 'saldo' => 6300000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Tiara Andini', 'phone' => '081234567812', 'address' => 'Jl. Fatmawati No. 6, Blotongan, Sidorejo, Kota Salatiga', 'lat' => -7.324000, 'lng' => 110.501000, 'default' => true],
                ],
            ],
            [
                'username' => 'yoga', 'name' => 'Yoga Pratama', 'email' => 'yoga@ramela.test', 'phone' => '081234567813', 'saldo' => 4800000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Yoga Pratama', 'phone' => '081234567813', 'address' => 'Jl. Gatot Subroto No. 42, Bandarjo, Ungaran Barat, Kab. Semarang / Ungaran', 'lat' => -7.125000, 'lng' => 110.411000, 'default' => true],
                ],
            ],
            [
                'username' => 'nadia', 'name' => 'Nadia Safitri', 'email' => 'nadia@ramela.test', 'phone' => '081234567814', 'saldo' => 8200000,
                'addresses' => [
                    ['label' => 'Rumah', 'recipient' => 'Nadia Safitri', 'phone' => '081234567814', 'address' => 'Jl. Erlangga Raya No. 22, Pleburan, Semarang Selatan, Kota Semarang', 'lat' => -6.995000, 'lng' => 110.428000, 'default' => true],
                ],
            ],
        ];

        $paymentTypes = ['qris', 'bank_transfer', 'gopay', 'shopeepay'];

        foreach ($customers as $c) {
            $user = User::updateOrCreate(
                ['username' => $c['username']],
                [
                    'name' => $c['name'],
                    'email' => $c['email'],
                    'phone' => $c['phone'],
                    'password' => 'password',
                ]
            );
            $user->syncRoles([Role::User->value]);

            // Set saldo user
            $user->saldo = $c['saldo'];
            $user->save();

            // Initial Topup Wallet Transaction
            WalletTransaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'topup',
                    'note' => 'Saldo awal deposit demo',
                ],
                [
                    'amount' => $c['saldo'],
                    'balance_before' => 0,
                    'balance_after' => $c['saldo'],
                ]
            );

            // Seed Topup History records (2-3 history entries per user)
            $topupCounts = rand(2, 3);
            for ($k = 1; $k <= $topupCounts; $k++) {
                $status = ($k === 1) ? 'success' : (rand(1, 10) <= 8 ? 'success' : 'pending');
                $histAmount = rand(100, 2500) * 10000;
                $pType = $paymentTypes[array_rand($paymentTypes)];
                $orderId = sprintf('TOPUP-%s-%s-%04d', now()->subDays($k * 3)->format('Ymd'), strtoupper($user->username), rand(100, 999));

                TopupHistory::updateOrCreate(
                    ['midtrans_order_id' => $orderId],
                    [
                        'user_id' => $user->id,
                        'amount' => $histAmount,
                        'snap_token' => 'snap_token_demo_' . md5($orderId),
                        'payment_type' => $pType,
                        'status' => $status,
                        'paid_at' => ($status === 'success') ? now()->subDays($k * 3) : null,
                        'created_at' => now()->subDays($k * 3),
                        'updated_at' => now()->subDays($k * 3),
                    ]
                );
            }

            // Seed Multiple Addresses
            foreach ($c['addresses'] as $addr) {
                Address::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'label' => $addr['label'],
                    ],
                    [
                        'recipient_name' => $addr['recipient'],
                        'phone' => $addr['phone'],
                        'full_address' => $addr['address'],
                        'latitude' => $addr['lat'],
                        'longitude' => $addr['lng'],
                        'is_default' => $addr['default'],
                    ]
                );
            }
        }
    }
}
