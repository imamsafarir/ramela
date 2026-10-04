<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Address;
use App\Models\Blog;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\DeliveryPhoto;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promo;
use App\Models\PromoLog;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\TopupHistory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionStatusLog;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Kosongkan tabel transaksi, pengiriman kurir, dan keranjang untuk simulasi fresh
        $this->cleanTransactions();

        // 2. Seed User & Role (Super Admin, Admin, Kurir, Pelanggan beserta Saldo & Alamat)
        $this->seedUsers();

        // 3. Seed Pilar Toko RAMELA (Eats, Hampers, Beton)
        $stores = $this->seedStores();

        // 4. Seed Kategori Produk
        $categories = $this->seedCategories($stores);

        // 5. Seed Katalog Produk Lengkap (112 items)
        $this->seedProducts($stores, $categories);

        // 6. Seed Tarif Pengiriman & Ongkir
        $this->seedShippingRates();

        // 7. Seed Kupon Promo
        $this->seedPromos($stores);

        // 8. Seed Konten Web (Settings, FAQ, Blog)
        $this->seedContent($stores);

        $this->command?->info('✅ Database RAMELA berhasil diseed dalam satu file seeder tunggal.');
        $this->command?->info('🛒 Transaksi & Penugasan Kurir dikosongkan untuk kebutuhan simulasi transaksi dari nol.');
        $this->command?->info('🔑 Semua akun menggunakan password: password');
        $this->command?->info('   - Super Admin : superadmin / password');
        $this->command?->info('   - Admin       : admin, admin2, manajer / password');
        $this->command?->info('   - Kurir       : kurir1, kurir2, kurir3, kurir4, kurir5 / password');
        $this->command?->info('   - Pelanggan   : user, siti, ahmad, dewi, eko, budi, dll. / password');
    }

    /**
     * Kosongkan riwayat pesanan, penugasan kurir, dan keranjang agar selalu siap simulasi.
     */
    protected function cleanTransactions(): void
    {
        Schema::disableForeignKeyConstraints();

        DeliveryLocation::truncate();
        DeliveryPhoto::truncate();
        Delivery::truncate();
        TransactionStatusLog::truncate();
        TransactionDetail::truncate();
        PromoLog::truncate();
        CartItem::truncate();
        Transaction::truncate();

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Seed Roles, Super Admin, Admins, Couriers, and Customers.
     */
    protected function seedUsers(): void
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

        // 4. Customers
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

            $user->saldo = $c['saldo'];
            $user->save();

            // Saldo awal deposit demo
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

    /**
     * Seed 3 Pilar Toko RAMELA (EATS, HAMPERS, BETON).
     */
    protected function seedStores(): array
    {
        $storesData = [
            [
                'slug' => 'eats',
                'name' => 'RAMELA EATS',
                'tagline' => 'Kuliner Nusantara & Cita Rasa Otentik',
                'address' => 'Jl. Pandanaran No. 58, Mugassari, Semarang Selatan, Kota Semarang',
                'latitude' => -6.989720,
                'longitude' => 110.421930,
                'icon' => 'utensils',
                'sort_order' => 1,
            ],
            [
                'slug' => 'hampers',
                'name' => 'RAMELA HAMPERS',
                'tagline' => 'Bingkisan Eksklusif & Hadiah Istimewa',
                'address' => 'Jl. Pemuda No. 142, Sekayu, Semarang Tengah, Kota Semarang',
                'latitude' => -6.973050,
                'longitude' => 110.428510,
                'icon' => 'gift',
                'sort_order' => 2,
            ],
            [
                'slug' => 'beton',
                'name' => 'RAMELA BETON',
                'tagline' => 'Solusi Ready Mix & Precast Beton Kokoh Berkualitas',
                'address' => 'Kawasan Industri Candi Blok 8 No. 12, Ngaliyan, Kota Semarang',
                'latitude' => -6.987540,
                'longitude' => 110.345020,
                'icon' => 'truck',
                'sort_order' => 3,
            ],
        ];

        $stores = [];
        foreach ($storesData as $s) {
            $stores[$s['slug']] = Store::updateOrCreate(
                ['slug' => $s['slug']],
                [
                    'name' => $s['name'],
                    'tagline' => $s['tagline'],
                    'address' => $s['address'],
                    'latitude' => $s['latitude'],
                    'longitude' => $s['longitude'],
                    'icon' => $s['icon'],
                    'sort_order' => $s['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        return $stores;
    }

    /**
     * Seed Kategori Produk.
     */
    protected function seedCategories(array $stores): array
    {
        $categoriesData = [
            // EATS (8 kategori)
            'eats' => [
                ['slug' => 'nasi-olahan', 'name' => 'Nasi & Menu Utama', 'sort_order' => 1],
                ['slug' => 'ayam-unggas', 'name' => 'Ayam & Unggas', 'sort_order' => 2],
                ['slug' => 'daging-seafood', 'name' => 'Daging Sapi & Seafood', 'sort_order' => 3],
                ['slug' => 'sayur-sup', 'name' => 'Aneka Sayur & Sup', 'sort_order' => 4],
                ['slug' => 'camilan-snack', 'name' => 'Camilan & Gorengan', 'sort_order' => 5],
                ['slug' => 'minuman-dingin', 'name' => 'Minuman Dingin & Es Segar', 'sort_order' => 6],
                ['slug' => 'kopi-teh', 'name' => 'Kopi Nusantara & Teh', 'sort_order' => 7],
                ['slug' => 'dessert-manis', 'name' => 'Dessert & Tradisional', 'sort_order' => 8],
            ],
            // HAMPERS (7 kategori)
            'hampers' => [
                ['slug' => 'hampers-lebaran', 'name' => 'Hampers Idul Fitri & Ramadhan', 'sort_order' => 1],
                ['slug' => 'hampers-natal-tahun-baru', 'name' => 'Hampers Natal & Tahun Baru', 'sort_order' => 2],
                ['slug' => 'hampers-birthday', 'name' => 'Hampers Ulang Tahun & Perayaan', 'sort_order' => 3],
                ['slug' => 'hampers-wedding', 'name' => 'Hampers Pernikahan & Hantaran', 'sort_order' => 4],
                ['slug' => 'hampers-kue-kering', 'name' => 'Hampers Aneka Cookies & Kue Kering', 'sort_order' => 5],
                ['slug' => 'hampers-buah-segar', 'name' => 'Parcel Buah Segar Premium', 'sort_order' => 6],
                ['slug' => 'hampers-newborn-baby', 'name' => 'Hampers Bayi & Newborn Gift', 'sort_order' => 7],
            ],
            // BETON (7 kategori)
            'beton' => [
                ['slug' => 'ready-mix-standar', 'name' => 'Beton Ready Mix Standar', 'sort_order' => 1],
                ['slug' => 'ready-mix-mutu-tinggi', 'name' => 'Beton Cor Siap Pakai Mutu Tinggi', 'sort_order' => 2],
                ['slug' => 'paving-kanstin', 'name' => 'Paving Block & Kanstin', 'sort_order' => 3],
                ['slug' => 'buis-pipa-beton', 'name' => 'Buis Beton & Pipa Sumur', 'sort_order' => 4],
                ['slug' => 'u-ditch-saluran', 'name' => 'U-Ditch & Tutup Saluran', 'sort_order' => 5],
                ['slug' => 'box-culvert', 'name' => 'Box Culvert Jembatan & Gorong', 'sort_order' => 6],
                ['slug' => 'semen-agregat', 'name' => 'Material Semen & Agregat', 'sort_order' => 7],
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $storeSlug => $cats) {
            $store = $stores[$storeSlug];
            foreach ($cats as $c) {
                $categories[$storeSlug . ':' . $c['slug']] = Category::updateOrCreate(
                    ['store_id' => $store->id, 'slug' => $c['slug']],
                    ['name' => $c['name'], 'sort_order' => $c['sort_order']]
                );
            }
        }

        return $categories;
    }

    /**
     * Seed 112 Produk Lengkap beserta Gambar Produk.
     */
    protected function seedProducts(array $stores, array $categories): void
    {
        $products = [
            // RAMELA EATS (45 produk)
            [
                'store' => 'eats', 'cat' => 'nasi-olahan',
                'name' => 'Nasi Goreng Spesial Babat', 'price' => 32000, 'stock' => 50, 'unit' => 'porsi', 'weight' => 450,
                'desc' => 'Nasi goreng khas Semarang dengan potongan babat empuk, telur orak-arik, dan bumbu rempah harum.'
            ],
            [
                'store' => 'eats', 'cat' => 'nasi-olahan',
                'name' => 'Nasi Liwet Solo Komplit', 'price' => 35000, 'stock' => 40, 'unit' => 'porsi', 'weight' => 550,
                'desc' => 'Nasi gurih beraroma santan dan daun salam disajikan dengan sayur labu siam, suwiran ayam opor, dan areh.'
            ],
            [
                'store' => 'eats', 'cat' => 'nasi-olahan',
                'name' => 'Nasi Bakar Cakalang Kemangi', 'price' => 28000, 'stock' => 35, 'unit' => 'porsi', 'weight' => 400,
                'desc' => 'Nasi bakar daun pisang dengan isian suwiran cakalang rica dan kemangi segar wangi menggugah selera.'
            ],
            [
                'store' => 'eats', 'cat' => 'nasi-olahan',
                'name' => 'Nasi Tumpeng Mini Nusantara', 'price' => 75000, 'stock' => 25, 'unit' => 'pax', 'weight' => 1200,
                'desc' => 'Nasi kuning tumpeng mini dengan lauk komplit ayam goreng, perkedel, telur rawis, tempe orek, dan sambal goreng ati.'
            ],
            [
                'store' => 'eats', 'cat' => 'nasi-olahan',
                'name' => 'Nasi Campur Bali Ramela', 'price' => 38000, 'stock' => 30, 'unit' => 'porsi', 'weight' => 500,
                'desc' => 'Nasi putih dengan ayam suwir bumbu betutu, sate lilit ikan, lawar sayur, telur pedas, dan sambal matah.'
            ],
            [
                'store' => 'eats', 'cat' => 'ayam-unggas',
                'name' => 'Ayam Bakar Taliwang Pedas', 'price' => 42000, 'stock' => 30, 'unit' => 'ekor', 'weight' => 650,
                'desc' => 'Ayam bakar muda utuh khas Lombok dengan baluran bumbu pedas manis aroma terasi khas.'
            ],
            [
                'store' => 'eats', 'cat' => 'ayam-unggas',
                'name' => 'Ayam Goreng Kremes Renyah', 'price' => 38000, 'stock' => 45, 'unit' => 'ekor', 'weight' => 600,
                'desc' => 'Ayam pejantan bumbu lengkuas ungkep empuk dengan taburan kremesan renyah gurih berlimpah.'
            ],
            [
                'store' => 'eats', 'cat' => 'ayam-unggas',
                'name' => 'Bebek Goreng Sambal Korek', 'price' => 48000, 'stock' => 25, 'unit' => 'porsi', 'weight' => 700,
                'desc' => 'Bebek empuk tidak amis digoreng garing di luar juicy di dalam, disajikan dengan lalapan dan sambal korek pedas nampol.'
            ],
            [
                'store' => 'eats', 'cat' => 'ayam-unggas',
                'name' => 'Ayam Betutu Gilimanuk', 'price' => 75000, 'stock' => 20, 'unit' => 'ekor', 'weight' => 800,
                'desc' => 'Ayam kampung utuh matang perlahan dengan rempah base genep Bali berkuah kental gurih pedas.'
            ],
            [
                'store' => 'eats', 'cat' => 'ayam-unggas',
                'name' => 'Sate Ayam Madura Bumbu Kacang', 'price' => 28000, 'stock' => 60, 'unit' => '10 tusuk', 'weight' => 350,
                'desc' => 'Daging ayam tanpa lemak empuk dibakar arang disiram bumbu saus kacang legit dan kecap manis khas.'
            ],
            [
                'store' => 'eats', 'cat' => 'daging-seafood',
                'name' => 'Rendang Sapi Minang Otentik', 'price' => 85000, 'stock' => 30, 'unit' => 'pack', 'weight' => 500,
                'desc' => 'Daging sapi pilihan dimasak perlahan 8 jam dalam santan dan rempah sampai berwarna gelap dan bumbu meresap sempurna.'
            ],
            [
                'store' => 'eats', 'cat' => 'daging-seafood',
                'name' => 'Iga Sapi Bakar Madu Pedas', 'price' => 80000, 'stock' => 20, 'unit' => 'porsi', 'weight' => 600,
                'desc' => 'Iga sapi empuk lepas dari tulang dengan glasir madu murni dan cabai bakar disajikan bersama kuah sop hangat.'
            ],
            [
                'store' => 'eats', 'cat' => 'daging-seafood',
                'name' => 'Gurame Terbang Sambal Terasi', 'price' => 65000, 'stock' => 25, 'unit' => 'ekor', 'weight' => 750,
                'desc' => 'Ikan gurame segar fillet goreng bentuk terbang mekar super renyah disajikan dengan sambal terasi dadak.'
            ],
            [
                'store' => 'eats', 'cat' => 'daging-seafood',
                'name' => 'Udang Bakar Saus Madu', 'price' => 55000, 'stock' => 30, 'unit' => 'porsi', 'weight' => 400,
                'desc' => 'Udang laut windu segar dibakar bumbu mentega madu dengan aroma asap yang harum.'
            ],
            [
                'store' => 'eats', 'cat' => 'daging-seafood',
                'name' => 'Cumi Goreng Tepung Crispy', 'price' => 45000, 'stock' => 35, 'unit' => 'porsi', 'weight' => 350,
                'desc' => 'Ring cumi segar renyah gurih tidak liat dengan cocolan saus tartar dan saus asam manis.'
            ],
            [
                'store' => 'eats', 'cat' => 'sayur-sup',
                'name' => 'Sup Buntut Sapi Kuah Gurih', 'price' => 75000, 'stock' => 25, 'unit' => 'porsi', 'weight' => 700,
                'desc' => 'Buntut sapi impor empuk dalam kuah kaldu rempah bening kaya pala, wortel, kentang, dan emping melinjo.'
            ],
            [
                'store' => 'eats', 'cat' => 'sayur-sup',
                'name' => 'Rawon Daging Sapi Khas Jatim', 'price' => 45000, 'stock' => 30, 'unit' => 'porsi', 'weight' => 650,
                'desc' => 'Sup daging sapi kuah hitam pekat kluwek khas Jawa Timur disajikan dengan tauge pendek dan telur asin.'
            ],
            [
                'store' => 'eats', 'cat' => 'sayur-sup',
                'name' => 'Soto Ayam Lamongan Komplit', 'price' => 25000, 'stock' => 40, 'unit' => 'porsi', 'weight' => 500,
                'desc' => 'Soto kuah kuning gurih segar dengan suwiran ayam, sohun, kol, telur rebus, dan bubuk koya gurih melimpah.'
            ],
            [
                'store' => 'eats', 'cat' => 'sayur-sup',
                'name' => 'Sayur Asem Jawa Tengah', 'price' => 18000, 'stock' => 30, 'unit' => 'mangkuk', 'weight' => 450,
                'desc' => 'Kuah sayur asem bening segar dengan jagung manis, melinjo, labu siam, dan kacang tanah gurih.'
            ],
            [
                'store' => 'eats', 'cat' => 'sayur-sup',
                'name' => 'Cah Kangkung Terasi Hotplate', 'price' => 16000, 'stock' => 40, 'unit' => 'porsi', 'weight' => 300,
                'desc' => 'Kangkung hijau segar ditumis cepat dengan cabai merah, bawang, dan terasi khas beraroma sedap.'
            ],
            [
                'store' => 'eats', 'cat' => 'camilan-snack',
                'name' => 'Bakwan Jagung Manis Renyah', 'price' => 15000, 'stock' => 50, 'unit' => '5 pcs', 'weight' => 300,
                'desc' => 'Bakwan jagung manis pipil segar digoreng renyah dengan daun seledri dan cocolan cabai rawit hijau.'
            ],
            [
                'store' => 'eats', 'cat' => 'camilan-snack',
                'name' => 'Tahu Bakso Khas Ungaran', 'price' => 30000, 'stock' => 40, 'unit' => '10 pcs', 'weight' => 450,
                'desc' => 'Tahu cokelat tebal dengan isian daging sapi cincang padat kenyal gurih, nikmat digoreng atau kukus.'
            ],
            [
                'store' => 'eats', 'cat' => 'camilan-snack',
                'name' => 'Lumpia Semarang Basah & Goreng', 'price' => 40000, 'stock' => 35, 'unit' => '5 pcs', 'weight' => 500,
                'desc' => 'Lumpia otentik isi rebung manis renyah, udang, dan telur dengan saus kental bawang putih dan lokio.'
            ],
            [
                'store' => 'eats', 'cat' => 'camilan-snack',
                'name' => 'Tempe Mendoan Purwokerto', 'price' => 15000, 'stock' => 50, 'unit' => '5 pcs', 'weight' => 350,
                'desc' => 'Tempe kedelai lembaran berbalut tepung bumbu kencur dan daun bawang digoreng setengah matang lembut.'
            ],
            [
                'store' => 'eats', 'cat' => 'camilan-snack',
                'name' => 'Risoles Rogout Ayam Keju', 'price' => 25000, 'stock' => 40, 'unit' => '5 pcs', 'weight' => 300,
                'desc' => 'Kulit risol lembut renyah tepung panir dengan isian rogout susu gurih, wortel, suwir ayam dan keju cheddar.'
            ],
            [
                'store' => 'eats', 'cat' => 'camilan-snack',
                'name' => 'Pastel Tutup Kentang Daging', 'price' => 32000, 'stock' => 30, 'unit' => 'pax', 'weight' => 400,
                'desc' => 'Pastel panggang kentang tumbuk mentega lembut isi daging cincang, soun, jamur, wortel, dan keju parmesan.'
            ],
            [
                'store' => 'eats', 'cat' => 'minuman-dingin',
                'name' => 'Es Teler Durian Istimewa', 'price' => 25000, 'stock' => 40, 'unit' => 'gelas', 'weight' => 450,
                'desc' => 'Alpukat mentega, kelapa muda, nangka harum, es serut susu manis dan topping durian montong manis legit.'
            ],
            [
                'store' => 'eats', 'cat' => 'minuman-dingin',
                'name' => 'Es Kelapa Muda Jeruk', 'price' => 15000, 'stock' => 50, 'unit' => 'gelas', 'weight' => 400,
                'desc' => 'Air kelapa murni segar dengan kerukan daging kelapa muda dipadu perasan jeruk manis alami menyegarkan.'
            ],
            [
                'store' => 'eats', 'cat' => 'minuman-dingin',
                'name' => 'Es Campur Rumput Laut', 'price' => 20000, 'stock' => 40, 'unit' => 'gelas', 'weight' => 450,
                'desc' => 'Kombinasi cincau hitam, kolang kaling, rumput laut, mutiara, sirup cocopandan dan krimer kental manis.'
            ],
            [
                'store' => 'eats', 'cat' => 'minuman-dingin',
                'name' => 'Jus Alpukat Kocok Milo', 'price' => 22000, 'stock' => 45, 'unit' => 'gelas', 'weight' => 400,
                'desc' => 'Alpukat mentega kocok kental dengan susu kental manis cokelat dan taburan bubuk cokelat milo renyah.'
            ],
            [
                'store' => 'eats', 'cat' => 'minuman-dingin',
                'name' => 'Es Teh Manis Melati Solo', 'price' => 6000, 'stock' => 150, 'unit' => 'cup', 'weight' => 350,
                'desc' => 'Teh racikan tiga merek khas Solo Jawa Tengah yang kental, sepet, harum melati alami, dan manis gula tebu.'
            ],
            [
                'store' => 'eats', 'cat' => 'kopi-teh',
                'name' => 'Kopi Tubruk Robusta Temanggung', 'price' => 18000, 'stock' => 60, 'unit' => 'cangkir', 'weight' => 250,
                'desc' => 'Kopi biji robusta lereng Gunung Sindoro Temanggung dengan crema tebal aroma cokelat karamel.'
            ],
            [
                'store' => 'eats', 'cat' => 'kopi-teh',
                'name' => 'Kopi Susu Gula Aren Ramela', 'price' => 20000, 'stock' => 80, 'unit' => 'cup', 'weight' => 350,
                'desc' => 'Espresso arabika blend dengan susu segar creamy dan lelehan gula aren murni alami dingin segar.'
            ],
            [
                'store' => 'eats', 'cat' => 'kopi-teh',
                'name' => 'Kopi V60 Arabika Gayo Aceh', 'price' => 28000, 'stock' => 40, 'unit' => 'cangkir', 'weight' => 250,
                'desc' => 'Single origin Arabika Gayo diseduh metode pour over V60 manual brew dengan notes floral dan fruity asam lembut.'
            ],
            [
                'store' => 'eats', 'cat' => 'kopi-teh',
                'name' => 'Teh Tarik Hangat / Dingin', 'price' => 16000, 'stock' => 50, 'unit' => 'cup', 'weight' => 350,
                'desc' => 'Teh hitam pekat ditarik berbuih tebal dipadu kental manis dan evaporated milk lembut harum.'
            ],
            [
                'store' => 'eats', 'cat' => 'kopi-teh',
                'name' => 'Wedang Ronde Jahe Komplit', 'price' => 18000, 'stock' => 35, 'unit' => 'mangkuk', 'weight' => 400,
                'desc' => 'Bola-bola ketan isi kacang tanah tumbuk manis disajikan dalam kuah wedang jahe emprit pedas hangat.'
            ],
            [
                'store' => 'eats', 'cat' => 'kopi-teh',
                'name' => 'Wedang Uwuh Rempah Imogiri', 'price' => 15000, 'stock' => 40, 'unit' => 'cangkir', 'weight' => 300,
                'desc' => 'Seduhan kayu secang merah, cengkih, kapulaga, pala, jahe geprek, dan gula batu khas Imogiri Yogyakarta.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Kolak Pisang Ubi Santan', 'price' => 16000, 'stock' => 30, 'unit' => 'mangkuk', 'weight' => 450,
                'desc' => 'Pisang kepok manis dan ubi jalar lembut dalam kuah santan daun pandan wangi berbalut gula merah.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Serabi Solo Aneka Rasa', 'price' => 25000, 'stock' => 40, 'unit' => 'box 6 pcs', 'weight' => 300,
                'desc' => 'Serabi tepung beras santan lembut berkerak tipis renyah dengan topping cokelat, keju, dan polos original.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Klepon Gula Merah Pandan', 'price' => 18000, 'stock' => 45, 'unit' => 'box 8 pcs', 'weight' => 250,
                'desc' => 'Kue bola ketan hijau pandan alami dengan lelehan gula merah di dalam serta taburan kelapa parut kukus gurih.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Puding Cokelat Vla Vanila', 'price' => 20000, 'stock' => 35, 'unit' => 'cup', 'weight' => 350,
                'desc' => 'Puding susu dark chocolate premium lembut lumer dipadu saus vla vanila kental creamy harum.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Tape Ketan Hijau Muntilan', 'price' => 30000, 'stock' => 25, 'unit' => 'toples', 'weight' => 500,
                'desc' => 'Tape ketan manis beraroma daun katuk segar khas lereng Merapi Muntilan rasa manis asam alami.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Jenang Kudus Wijen Asli', 'price' => 35000, 'stock' => 30, 'unit' => 'kotak', 'weight' => 500,
                'desc' => 'Dodol jenang ketan legit kenyal manis gula merah khas Kudus dengan taburan biji wijen wangi.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Wingko Babat Semarang Spesial', 'price' => 45000, 'stock' => 50, 'unit' => 'box 20 pcs', 'weight' => 600,
                'desc' => 'Wingko kelapa muda bakar gurih manis legit khas stasiun Tawang Semarang kemasan higienis.'
            ],
            [
                'store' => 'eats', 'cat' => 'dessert-manis',
                'name' => 'Roti Ganjel Rel Tradisional', 'price' => 32000, 'stock' => 25, 'unit' => 'loaf', 'weight' => 500,
                'desc' => 'Roti rempah khas Semarang dengan aroma kayu manis kapulaga dan taburan wijen harum tempo dulu.'
            ],

            // RAMELA HAMPERS (35 produk)
            [
                'store' => 'hampers', 'cat' => 'hampers-lebaran',
                'name' => 'Hampers Idul Fitri Royal Emerald', 'price' => 650000, 'stock' => 15, 'unit' => 'box set', 'weight' => 3500,
                'desc' => 'Box eksklusif beludru hijau zamrud isi Nastar Wisman, Kastengel Edam, Kurma Sukari, Sajadah Turki, dan mug porselen.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-lebaran',
                'name' => 'Hampers Lebaran Mubarak Silver', 'price' => 450000, 'stock' => 25, 'unit' => 'box set', 'weight' => 2500,
                'desc' => 'Paket bingkisan Idul Fitri isi 3 toples cookies favorit, sirup markisa premium, dan greeting card custom kaligrafi.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-lebaran',
                'name' => 'Hampers Berkah Ramadhan Gold', 'price' => 850000, 'stock' => 12, 'unit' => 'keranjang rotan', 'weight' => 4000,
                'desc' => 'Keranjang rotan anyaman premium isi 4 toples cookies, madu hutan murni, kurma ajwa Madinah, dan scented candle aromaterapi.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-lebaran',
                'name' => 'Hampers Kurma Ajwa & Sajadah Sutra', 'price' => 380000, 'stock' => 20, 'unit' => 'hardbox', 'weight' => 1800,
                'desc' => 'Bingkisan islami elegan kotak magnetik isi Kurma Ajwa 500g, tasbih kayu kokka, dan sajadah travel sutra lembut.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-lebaran',
                'name' => 'Hampers Sirup & Aneka Snack Lebaran', 'price' => 320000, 'stock' => 30, 'unit' => 'box set', 'weight' => 3000,
                'desc' => 'Paket hemat bingkisan silaturahmi isi 2 botol sirup premium, aneka keripik buah nusantara, dan wafer cokelat kaleng.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-natal-tahun-baru',
                'name' => 'Hampers Natal Joyful Christmas Pine', 'price' => 720000, 'stock' => 15, 'unit' => 'box set', 'weight' => 3800,
                'desc' => 'Box tema cemara natal isi Ginger Cookies, Fruitcake brandy-free, Sparkling Red Grape, dan ornamen lonceng emas.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-natal-tahun-baru',
                'name' => 'Hampers Christmas Wonderland White', 'price' => 520000, 'stock' => 18, 'unit' => 'hardbox', 'weight' => 2800,
                'desc' => 'Hardbox putih salju isi Snowflake Butter Cookies, hot chocolate jar, mug keramik natal bertutup, dan lampu LED hias.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-natal-tahun-baru',
                'name' => 'Hampers New Year Resolution 2027', 'price' => 400000, 'stock' => 20, 'unit' => 'box set', 'weight' => 2200,
                'desc' => 'Paket tahun baru penyemangat isi agenda kulit exclusive, tumbler stainless vacuum, dan artisanal dark chocolate bar.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-natal-tahun-baru',
                'name' => 'Hampers Cokelat & Sparkling Grape', 'price' => 490000, 'stock' => 15, 'unit' => 'box set', 'weight' => 3200,
                'desc' => 'Bingkisan perayaan mewah isi botol sparkling juice anggur merah, praline chocolate gift box, dan gelas goblet kristal.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-natal-tahun-baru',
                'name' => 'Hampers Winter Glow Aromatherapy', 'price' => 350000, 'stock' => 20, 'unit' => 'gift box', 'weight' => 1500,
                'desc' => 'Kotak relaksasi musim dingin isi soy wax candle beraroma cinnamon pine, reed diffuser, dan hand sanitizer mist floral.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-birthday',
                'name' => 'Hampers Birthday Blossom Pastel', 'price' => 320000, 'stock' => 25, 'unit' => 'box set', 'weight' => 1800,
                'desc' => 'Kado ulang tahun cantik nuansa pastel isi dried flower bouquet mini, scented candle peach, dan cokelat praline.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-birthday',
                'name' => 'Hampers Birthday Party Celebration', 'price' => 450000, 'stock' => 15, 'unit' => 'box set', 'weight' => 2500,
                'desc' => 'Bingkisan pesta ulang tahun isi mini cake toples, cookies sprinkle, confetti popper, balon foil, dan kartu ucapan kustom.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-birthday',
                'name' => 'Hampers Sweet 17 Pinkish Dream', 'price' => 390000, 'stock' => 15, 'unit' => 'hardbox', 'weight' => 1600,
                'desc' => 'Kado ultah manis remaja putri isi cermin LED lipat, body mist vanila, permen marshmallow toples, dan boneka beruang mini.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-birthday',
                'name' => 'Hampers Gentlemen Birthday Leather', 'price' => 420000, 'stock' => 15, 'unit' => 'leather box', 'weight' => 1400,
                'desc' => 'Kotak kulit pria maskulin isi dompet kulit sapi asli, gantungan kunci ukir nama, dan parfum maskulin beraroma woody.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-birthday',
                'name' => 'Hampers Tea Artisanal & Mug Keramik', 'price' => 280000, 'stock' => 30, 'unit' => 'gift box', 'weight' => 1200,
                'desc' => 'Kado teh elegan isi 3 tin box tisane chamomile earl grey, saringan teh stainless gold, dan cangkir keramik handmade.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-wedding',
                'name' => 'Seserahan Pernikahan Luxury Velvet', 'price' => 1200000, 'stock' => 8, 'unit' => 'box akrilik', 'weight' => 4500,
                'desc' => 'Kotak akrilik transparan berbingkai emas isi kain brokat sutra, mukena bordir padang, parfum pengantin, dan ornamen bunga.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-wedding',
                'name' => 'Hampers Souvenir Wedding Rose Gold', 'price' => 280000, 'stock' => 25, 'unit' => 'hardbox', 'weight' => 1500,
                'desc' => 'Bingkisan bridesmaid & groomsmen isi sendok garpu set rose gold, tumbler kustom grafir, dan hand cream aroma mawar.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-wedding',
                'name' => 'Hantaran Lamaran Premium Batik & Perlengkapan', 'price' => 1500000, 'stock' => 5, 'unit' => 'set akrilik', 'weight' => 5000,
                'desc' => 'Set seserahan kotak kaca hias isi kain batik tulis Solo sutra, tas pesta satin, dan selop bordir pengantin wanita.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-wedding',
                'name' => 'Hampers Wedding Anniversary Crystal', 'price' => 850000, 'stock' => 10, 'unit' => 'exclusive box', 'weight' => 3000,
                'desc' => 'Kado ulang tahun pernikahan sepasang cangkir kristal gold rim, sparkling white grape, dan album foto kulit memorial.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-wedding',
                'name' => 'Souvenir Mangkok Keramik Pasangan', 'price' => 180000, 'stock' => 40, 'unit' => 'gift box', 'weight' => 1200,
                'desc' => 'Set dua mangkok keramik jepang motif marble lengkap dengan sumpit kayu sonokeling dalam box motif pengantin.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-kue-kering',
                'name' => 'Hampers Nastar Wisman & Kastengel Edam', 'price' => 350000, 'stock' => 30, 'unit' => 'box toples 2', 'weight' => 1800,
                'desc' => 'Kombinasi klasik legendaris 1 toples nastar nanas legit lumer butter wisman dan 1 toples kastengel keju edam tua gurih renyah.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-kue-kering',
                'name' => 'Paket Kue Kering 3 Toples Elegan', 'price' => 450000, 'stock' => 25, 'unit' => 'box toples 3', 'weight' => 2400,
                'desc' => 'Box eksklusif isi Nastar Keju, Kastengel Edam, dan Putri Salju Pandan Mede dalam toples kristal segi delapan higienis.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-kue-kering',
                'name' => 'Hampers Putri Salju Pandan & Sagu Keju', 'price' => 290000, 'stock' => 30, 'unit' => 'box toples 2', 'weight' => 1600,
                'desc' => 'Dua toples cookies favorit keluarga putri salju berhawa dingin gula halus dan sagu keju renyah lumer di mulut.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-kue-kering',
                'name' => 'Exclusive Cookie Tin Box Heritage', 'price' => 320000, 'stock' => 25, 'unit' => 'tin box', 'weight' => 1500,
                'desc' => 'Kaleng kaligrafi vintage isi assorted cookies Belgian chocolate chip, almond biscotti, dan butter cookies wangi.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-kue-kering',
                'name' => 'Hampers Lidah Kucing & Choco Crunch', 'price' => 280000, 'stock' => 30, 'unit' => 'box toples 2', 'weight' => 1700,
                'desc' => 'Kue lidah kucing keju tipis renyah dan choco crunch cookies bertabur chocochips premium dalam box pita satin.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-buah-segar',
                'name' => 'Parcel Buah Segar Nusantara Tropis', 'price' => 320000, 'stock' => 20, 'unit' => 'keranjang rotan', 'weight' => 5000,
                'desc' => 'Keranjang buah segar nusantara isi mangga arumanis, pisang cavendish, buah naga merah, jeruk medan manis, dan salak pondoh.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-buah-segar',
                'name' => 'Parcel Buah Impor Eksklusif Sunpride & Pear', 'price' => 480000, 'stock' => 15, 'unit' => 'keranjang rotan', 'weight' => 6000,
                'desc' => 'Buah impor pilihan apel fuji super, sunkist navel manis, pear century madu, anggur red globe, dan kiwi gold segar.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-buah-segar',
                'name' => 'Fruit Basket Deluxe with Honey & Juicer', 'price' => 750000, 'stock' => 10, 'unit' => 'wooden crate', 'weight' => 7500,
                'desc' => 'Crate kayu pinus mewah isi aneka buah impor grade A, botol madu murni randu, dan portable glass juicer blender.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-buah-segar',
                'name' => 'Hampers Buah Sehat Get Well Soon', 'price' => 380000, 'stock' => 20, 'unit' => 'keranjang', 'weight' => 4500,
                'desc' => 'Parcel buah penyemangat kesembuhan isi buah kaya vitamin C, sarang burung walet botol, dan kartu ucapan lekas sembuh.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-buah-segar',
                'name' => 'Hampers Apel Fuji & Anggur Muscat', 'price' => 580000, 'stock' => 12, 'unit' => 'keranjang pita', 'weight' => 5500,
                'desc' => 'Bingkisan mewah buah premium anggur shine muscat tanpa biji, apel envy new zealand, dan buah delima merah.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-newborn-baby',
                'name' => 'Hampers Newborn Baby Boy Blue Cloud', 'price' => 420000, 'stock' => 15, 'unit' => 'box set', 'weight' => 2200,
                'desc' => 'Kado bayi laki-laki tema awan biru isi jumper katun SNI, topi kupluk, kaos kaki rajut, handuk microfiber, dan rattle mainan.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-newborn-baby',
                'name' => 'Hampers Newborn Baby Girl Sweet Pink', 'price' => 420000, 'stock' => 15, 'unit' => 'box set', 'weight' => 2200,
                'desc' => 'Kado bayi perempuan tema pink isi bandana pita, jumper renda halus, selimut bedong instan, dan teether gigitan silikon BPA free.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-newborn-baby',
                'name' => 'Hampers Organik Mandi Bayi', 'price' => 350000, 'stock' => 20, 'unit' => 'keranjang katun', 'weight' => 2000,
                'desc' => 'Set perawatan kulit bayi sensitif sabun cair organic 2in1, minyak telon wangi, baby lotion, dan washlap katun jepang lembut.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-newborn-baby',
                'name' => 'Hampers Selimut Bulu & Boneka Rajut', 'price' => 380000, 'stock' => 18, 'unit' => 'hardbox', 'weight' => 1800,
                'desc' => 'Kotak kado hangat berisi selimut fleece premium dua lapis motif bintang dan boneka kelinci rajut amigurumi handmade.'
            ],
            [
                'store' => 'hampers', 'cat' => 'hampers-newborn-baby',
                'name' => 'Hampers Baju Bayi Katun Bambu SNI', 'price' => 290000, 'stock' => 25, 'unit' => 'gift box', 'weight' => 1500,
                'desc' => 'Paket isi 3 stel baju pendek celana bayi bahan serat bambu adem anti alergi berstandar nasional Indonesia.'
            ],

            // RAMELA BETON (32 produk)
            [
                'store' => 'beton', 'cat' => 'ready-mix-standar',
                'name' => 'Beton Cor Ready Mix K-175 Standar', 'price' => 820000, 'stock' => 100, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton cor siap pakai mutu K-175 untuk lantai kerja, jalan setapak lingkungan, dan pengurukan non struktural.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-standar',
                'name' => 'Beton Cor Ready Mix K-200 Standar', 'price' => 860000, 'stock' => 100, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton cor mutu K-200 untuk dak rumah 1 lantai, garasi mobil ringan, dan jalan gang perumahan.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-standar',
                'name' => 'Beton Cor Ready Mix K-225 Rumah Tinggal', 'price' => 910000, 'stock' => 150, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton cor standar mutu K-225 paling direkomendasikan untuk struktur kolom, balok gantung, dan dak cor lantai 2 rumah.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-standar',
                'name' => 'Beton Cor Ready Mix K-250 Konstruksi Ruko', 'price' => 950000, 'stock' => 150, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton cor mutu K-250 untuk bangunan komersial, ruko 2-3 lantai, dan lantai pergudangan beban standar.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-standar',
                'name' => 'Beton Cor Slump 12±2 K-225 Fly Ash', 'price' => 890000, 'stock' => 100, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton cor campuran fly ash ekonomis mudah dipompa concrete pump dengan kemampuan aliran pasta beton baik.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-mutu-tinggi',
                'name' => 'Beton Cor K-300 Gedung Bertingkat', 'price' => 1020000, 'stock' => 120, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton cor mutu K-300 berkekuatan tekan 300 kg/cm² umur 28 hari untuk struktur beban berat gedung 3-5 lantai.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-mutu-tinggi',
                'name' => 'Beton Cor K-350 Jalan Rigid Pavement', 'price' => 1090000, 'stock' => 100, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton mutu tinggi K-350 khusus perkerasan kaku jalan raya beton dilewati truk kontainer dan tronton.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-mutu-tinggi',
                'name' => 'Beton Cor K-400 Heavy Duty Dermaga & Gudang', 'price' => 1180000, 'stock' => 80, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton mutu K-400 daya tahan tinggi terhadap abrasi dan beban statis sangat berat pada lantai pabrik manufaktur.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-mutu-tinggi',
                'name' => 'Beton Cor K-500 Jembatan Bentang Panjang', 'price' => 1350000, 'stock' => 50, 'unit' => 'm³', 'weight' => 240000,
                'desc' => 'Beton mutu ultra K-500 dengan silica fume untuk konstruksi balok girder prategang jembatan bentang panjang.'
            ],
            [
                'store' => 'beton', 'cat' => 'ready-mix-mutu-tinggi',
                'name' => 'Beton Porous Non-Pasir Saluran Air', 'price' => 950000, 'stock' => 60, 'unit' => 'm³', 'weight' => 200000,
                'desc' => 'Beton ramah lingkungan berpori meloloskan air ke dalam tanah untuk lapangan olahraga dan area resapan parkir.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Paving Block Bata Abu-abu 6cm', 'price' => 75000, 'stock' => 1000, 'unit' => 'm²', 'weight' => 2500,
                'desc' => 'Paving bata pres hidrolik tebal 6cm warna abu natural kuat tekan K-250 cocok untuk halaman rumah dan trotoar.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Paving Block Bata Merah 6cm Warna', 'price' => 85000, 'stock' => 800, 'unit' => 'm²', 'weight' => 2500,
                'desc' => 'Paving block tebal 6cm dengan pigmen warna merah feri oksida anti pudar untuk aksen pola lantai taman.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Paving Block Bata Abu-abu 8cm Heavy Duty', 'price' => 95000, 'stock' => 600, 'unit' => 'm²', 'weight' => 3500,
                'desc' => 'Paving tebal 8cm kuat tekan K-350 dirancang menahan lintasan kendaraan niaga, truk pengangkut, dan area parkir mall.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Paving Segi Enam (Hexagon) 6cm', 'price' => 82000, 'stock' => 500, 'unit' => 'm²', 'weight' => 3000,
                'desc' => 'Paving bentuk heksagonal geometris artistik interlock kuat untuk taman rekreasi dan pedestrian.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Paving Topi Uskup 6cm Abu', 'price' => 4500, 'stock' => 1200, 'unit' => 'pcs', 'weight' => 2800,
                'desc' => 'Paving kuncian sudut tepi pemasangan model bata agar barisan paving terkunci rapat rapi tidak bergeser.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Kanstin Taman 40x20x10cm', 'price' => 28000, 'stock' => 400, 'unit' => 'batang', 'weight' => 16000,
                'desc' => 'Kanstin pembatas rumput taman dan jalur jalan lingkungan, finishing halus presisi cetakan baja.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Kanstin Trotoar DKI 60x25x15cm', 'price' => 65000, 'stock' => 300, 'unit' => 'batang', 'weight' => 42000,
                'desc' => 'Kanstin dimensi standar dinas bina marga trotoar kota dengan bevel lengkung rapi kuat benturan ban mobil.'
            ],
            [
                'store' => 'beton', 'cat' => 'paving-kanstin',
                'name' => 'Kanstin S 40x30x15cm Tali Air', 'price' => 52000, 'stock' => 250, 'unit' => 'batang', 'weight' => 35000,
                'desc' => 'Kanstin bentuk profil S berparit pembuangan air ke grill drainase samping jalan perumahan.'
            ],
            [
                'store' => 'beton', 'cat' => 'buis-pipa-beton',
                'name' => 'Buis Beton Bulat Dia 40cm x 100cm', 'price' => 95000, 'stock' => 80, 'unit' => 'batang', 'weight' => 65000,
                'desc' => 'Pipa beton gorong-gorong diameter 40 cm panjang 1 meter tulangan kawat baja untuk saluran drainase air hujan.'
            ],
            [
                'store' => 'beton', 'cat' => 'buis-pipa-beton',
                'name' => 'Buis Beton Bulat Dia 60cm x 100cm', 'price' => 165000, 'stock' => 60, 'unit' => 'batang', 'weight' => 120000,
                'desc' => 'Buis beton saluran air bersih dan got drainase diameter dalam 60 cm sambungan male-female interlocking.'
            ],
            [
                'store' => 'beton', 'cat' => 'buis-pipa-beton',
                'name' => 'Buis Beton Bulat Dia 80cm x 50cm', 'price' => 190000, 'stock' => 50, 'unit' => 'batang', 'weight' => 140000,
                'desc' => 'Buis beton tebal dinding kokoh diameter 80 cm tinggi 50 cm umum digunakan untuk dinding sumur resapan air tanah.'
            ],
            [
                'store' => 'beton', 'cat' => 'buis-pipa-beton',
                'name' => 'Buis Beton Bulat Dia 100cm x 50cm Resapan', 'price' => 260000, 'stock' => 40, 'unit' => 'batang', 'weight' => 190000,
                'desc' => 'Cincin sumur buis beton diameter besar 1 meter kuat menahan tekanan tanah lateral untuk septictank dan sumur gali.'
            ],
            [
                'store' => 'beton', 'cat' => 'buis-pipa-beton',
                'name' => 'Buis Beton Belah U Dia 30cm x 100cm', 'price' => 60000, 'stock' => 120, 'unit' => 'batang', 'weight' => 35000,
                'desc' => 'Pipa beton setengah lingkaran model U ukuran 30cm untuk saluran parit terbuka perumahan rapi dan lancar.'
            ],
            [
                'store' => 'beton', 'cat' => 'u-ditch-saluran',
                'name' => 'U-Ditch Beton 30 x 30 x 120 cm', 'price' => 140000, 'stock' => 100, 'unit' => 'batang', 'weight' => 90000,
                'desc' => 'Saluran drainase pracetak beton mutu K-350 bertulang lebar dalam 30cm tinggi 30cm panjang efektif 1.2m.'
            ],
            [
                'store' => 'beton', 'cat' => 'u-ditch-saluran',
                'name' => 'U-Ditch Beton 40 x 40 x 120 cm', 'price' => 210000, 'stock' => 80, 'unit' => 'batang', 'weight' => 135000,
                'desc' => 'Saluran pracetak U-ditch dimensi 40x40cm kapasitas debit aliran sedang untuk tepi jalan aspal lingkungan.'
            ],
            [
                'store' => 'beton', 'cat' => 'u-ditch-saluran',
                'name' => 'U-Ditch Beton 50 x 50 x 120 cm', 'price' => 320000, 'stock' => 60, 'unit' => 'batang', 'weight' => 190000,
                'desc' => 'U-ditch beton precast dimensi besar 50x50x120cm untuk saluran drainase primer perumahan bebas banjir.'
            ],
            [
                'store' => 'beton', 'cat' => 'u-ditch-saluran',
                'name' => 'Tutup U-Ditch 30cm Light Duty Pejalan Kaki', 'price' => 70000, 'stock' => 150, 'unit' => 'pcs', 'weight' => 35000,
                'desc' => 'Cover tutup saluran beton U-30 ketebalan 7cm dilengkapi lubang tali kaitan untuk trotoar pedestrian.'
            ],
            [
                'store' => 'beton', 'cat' => 'u-ditch-saluran',
                'name' => 'Tutup U-Ditch 40cm Heavy Duty Beban Truk', 'price' => 125000, 'stock' => 100, 'unit' => 'pcs', 'weight' => 65000,
                'desc' => 'Cover tutup U-ditch ukuran 40cm dengan tulangan ganda tebal 10cm mampu dilewati mobil dan truk melintas.'
            ],
            [
                'store' => 'beton', 'cat' => 'box-culvert',
                'name' => 'Box Culvert Precast 60 x 60 x 100 cm', 'price' => 850000, 'stock' => 30, 'unit' => 'unit', 'weight' => 450000,
                'desc' => 'Gorong-gorong kotak beton pracetak bertulang mutu K-350 untuk jembatan jalan kecil dan crossing drainase tertutup.'
            ],
            [
                'store' => 'beton', 'cat' => 'box-culvert',
                'name' => 'Box Culvert Precast 80 x 80 x 100 cm', 'price' => 1350000, 'stock' => 20, 'unit' => 'unit', 'weight' => 750000,
                'desc' => 'Box culvert beton pracetak 80x80cm dengan lidah alur sambungan rapat kedap air tahan beban gandar 20 ton.'
            ],
            [
                'store' => 'beton', 'cat' => 'semen-agregat',
                'name' => 'Semen Portland Komposit Gresik 50kg', 'price' => 68000, 'stock' => 500, 'unit' => 'sak', 'weight' => 50000,
                'desc' => 'Semen PPC kualitas SNI daya lekat kuat dan panas hidrasi rendah untuk plesteran, acian, dan cor beton.'
            ],
            [
                'store' => 'beton', 'cat' => 'semen-agregat',
                'name' => 'Pasir Cor Merapi Muntilan Asli 1 Truk', 'price' => 1650000, 'stock' => 20, 'unit' => 'dump truck 6m³', 'weight' => 6000000,
                'desc' => 'Pasir vulkanik sungai lereng Merapi Muntilan butiran tajam bebas lumpur untuk adukan cor beton kokoh maksimal.'
            ],
        ];

        foreach ($products as $p) {
            $store = $stores[$p['store']];
            $category = $categories[$p['store'] . ':' . $p['cat']];

            $product = Product::updateOrCreate(
                ['slug' => Str::slug($p['name'])],
                [
                    'store_id' => $store->id,
                    'category_id' => $category->id,
                    'name' => $p['name'],
                    'price' => $p['price'],
                    'stock' => $p['stock'],
                    'unit' => $p['unit'],
                    'weight' => $p['weight'],
                    'description' => $p['desc'],
                    'is_active' => true,
                ]
            );

            // Seed Product Image
            $imagePath = 'products/' . $p['store'] . '.svg';
            ProductImage::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'sort_order' => 1,
                ],
                [
                    'path' => $imagePath,
                ]
            );
        }
    }

    /**
     * Seed 16 Tarif Ongkir Pengiriman.
     */
    protected function seedShippingRates(): void
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

    /**
     * Seed 6 Kupon Promo Aktif.
     */
    protected function seedPromos(array $stores): void
    {
        $eats = $stores['eats'] ?? Store::where('slug', 'eats')->first();
        $hampers = $stores['hampers'] ?? Store::where('slug', 'hampers')->first();
        $beton = $stores['beton'] ?? Store::where('slug', 'beton')->first();

        // 1. Promo Global Persen
        Promo::updateOrCreate(
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
        Promo::updateOrCreate(
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
        Promo::updateOrCreate(
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

    /**
     * Seed Pengaturan Web, FAQ (12 items), dan Blog (6 rich posts).
     */
    protected function seedContent(array $stores): void
    {
        $admin = User::role(Role::Admin->value)->first() ?? User::role(Role::SuperAdmin->value)->first();
        $authorId = $admin?->id ?? 1;

        $eats = $stores['eats'] ?? Store::where('slug', 'eats')->first();
        $hampers = $stores['hampers'] ?? Store::where('slug', 'hampers')->first();
        $beton = $stores['beton'] ?? Store::where('slug', 'beton')->first();

        // 1. Web Settings
        $settings = [
            ['key' => 'feature.blog', 'value' => 'true', 'description' => 'Tampilkan menu Blog'],
            ['key' => 'feature.faq', 'value' => 'true', 'description' => 'Tampilkan menu FAQ'],
            ['key' => 'midtrans.is_production', 'value' => 'false', 'description' => 'true = Production, false = Sandbox'],
            ['key' => 'midtrans.merchant_id', 'value' => null, 'is_encrypted' => true, 'description' => 'Midtrans Merchant ID'],
            ['key' => 'midtrans.client_key', 'value' => null, 'is_encrypted' => true, 'description' => 'Midtrans Client Key'],
            ['key' => 'midtrans.server_key', 'value' => null, 'is_encrypted' => true, 'description' => 'Midtrans Server Key'],
        ];
        foreach ($settings as $setting) {
            WebSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        // 2. FAQs (12 entries)
        $faqs = [
            [
                'question' => 'Bagaimana cara melakukan pemesanan di RAMELA?',
                'answer' => 'Pilih salah satu toko pilar (RAMELA EATS, HAMPERS, atau BETON), pilih produk yang Anda inginkan, masukkan ke keranjang belanja, dan lakukan checkout menggunakan metode pembayaran Saldo RAMELA atau Midtrans.',
                'sort_order' => 1,
            ],
            [
                'question' => 'Bagaimana sistem penghitungan ongkos kirim dan berat barang?',
                'answer' => 'Ongkir dihitung berdasarkan tarif per kg kota tujuan atau tarif flat. Untuk tarif per kg, berat pesanan di bawah 1 kg akan tetap dihitung 1 kg minimum, dan kelipatan berat di atasnya akan dibulatkan ke atas (misal 1.2 kg dihitung 2 kg). Jika memilih metode Ambil Sendiri (Pickup), ongkos kirim adalah Rp 0 (Gratis).',
                'sort_order' => 2,
            ],
            [
                'question' => 'Bagaimana cara mengisi ulang (top up) Saldo RAMELA?',
                'answer' => 'Masuk ke menu Saldo / Topup di dashboard akun Anda, masukkan nominal yang diinginkan (minimal Rp 10.000), lalu selesaikan pembayaran melalui Midtrans (QRIS, Virtual Account Bank, GoPay, ShopeePay). Saldo akan bertambah secara otomatis.',
                'sort_order' => 3,
            ],
            [
                'question' => 'Apakah saya bisa melacak pergerakan kurir secara realtime?',
                'answer' => 'Ya, untuk pesanan dengan pengantaran kurir yang berstatus Sedang Dikirim (shipping), Anda dapat melihat peta live tracking rute jalan kurir beserta koordinat GPS dan foto validasi saat kurir mengambil dan mengantar barang.',
                'sort_order' => 4,
            ],
            [
                'question' => 'Bagaimana jika pesanan saya dibatalkan?',
                'answer' => 'Jika pesanan dibatalkan (oleh Anda atau admin), dana pembayaran akan dikembalikan secara penuh (refund otomatis) ke Saldo RAMELA Anda, dan kuota kupon promo yang digunakan akan dikembalikan.',
                'sort_order' => 5,
            ],
            [
                'question' => 'Apakah produk RAMELA EATS diantar dalam kondisi hangat dan higienis?',
                'answer' => 'Pasti. Setiap pesanan makanan RAMELA EATS dimasak fresh dan dikemas menggunakan kemasan food grade bersegel dengan tas termal khusus kurir untuk menjaga suhu dan kualitas rasa makanan hingga sampai ke tangan Anda.',
                'sort_order' => 6,
            ],
            [
                'question' => 'Apakah hampers RAMELA bisa disesuaikan kartu ucapannya (custom card)?',
                'answer' => 'Bisa. Pada saat checkout, Anda dapat menuliskan pesan atau ucapan khusus di kolom catatan. Tim florist dan hampers kami akan menyertakan greeting card eksklusif sesuai pesan Anda.',
                'sort_order' => 7,
            ],
            [
                'question' => 'Bagaimana prosedur pemesanan Beton Ready Mix RAMELA BETON?',
                'answer' => 'Pemesanan beton ready mix disarankan dilakukan minimal H-1 sebelum jadwal pengecoran. Armada truck mixer (molen) RAMELA akan mengantarkan beton segar tepat waktu sesuai jadwal proyek yang disepakati.',
                'sort_order' => 8,
            ],
            [
                'question' => 'Apakah RAMELA BETON melayani uji kuat tekan silinder/kubus beton di laboratorium?',
                'answer' => 'Ya, seluruh campuran beton ready mix RAMELA diproduksi melalui batching plant terkomputerisasi dengan sertifikat mutu pengujian kuat tekan laboratorium independen.',
                'sort_order' => 9,
            ],
            [
                'question' => 'Apakah saya bisa menggunakan beberapa kode promo sekaligus dalam satu transaksi?',
                'answer' => 'Dalam satu transaksi hanya dapat digunakan satu kode kupon promo aktif dengan diskon terbaik yang memenuhi syarat minimum belanja.',
                'sort_order' => 10,
            ],
            [
                'question' => 'Berapa lama estimasi pengiriman pesanan kurir RAMELA?',
                'answer' => 'Untuk pesanan area Kota Semarang berkisar 30-90 menit untuk makanan (Eats) dan sameday/nextday untuk hampers dan material beton sesuai jenis armada pengangkutan.',
                'sort_order' => 11,
            ],
            [
                'question' => 'Bagaimana cara menghubungi customer care RAMELA jika butuh bantuan?',
                'answer' => 'Anda dapat menghubungi layanan bantuan melalui WhatsApp resmi RAMELA di nomor 0812-1111-0000 atau mengirim email ke support@ramela.test setiap hari pukul 08.00 - 21.00 WIB.',
                'sort_order' => 12,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => $faq['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        // 3. Blogs (6 rich posts)
        $blogs = [
            [
                'title' => 'Rahasia Kelezatan Nasi Liwet Solo dan Rempah Otentik RAMELA Eats',
                'excerpt' => 'Menelusuri warisan resep turun temurun nasi liwet gurih berpadu opor ayam kampung dan areh santan kental yang memanjakan lidah.',
                'content' => "Nasi Liwet telah lama menjadi ikon kuliner Jawa Tengah yang tak tergantikan. Kunci kelezatan nasi liwet terletak pada proses penanakan beras pulen dengan kaldu ayam kampung murni, santan kelapa tua pilihan, daun salam segar, dan serai wangi.\n\nDi dapur RAMELA Eats, kami memastikan setiap porsi dimasak dengan standar sanitasi tinggi tanpa mengurangi cita rasa tradisional. Sayur labu siam dengan cabai rawit utuh menghadirkan rasa pedas gurih yang pas ketika disantap bersama suwiran daging ayam empuk.",
                'store_id' => $eats?->id,
                'published_at' => now()->subDays(12),
            ],
            [
                'title' => 'Panduan Memilih Hampers Lebaran dan Idul Fitri yang Berkesan untuk Keluarga & Kolega',
                'excerpt' => 'Tips jitu memilih bingkisan hari raya: kombinasi kue kering premium, kurma pilihan, serta packaging eksklusif yang memancarkan ketulusan.',
                'content' => "Momen hari raya adalah waktu terbaik untuk mempererat tali silaturahmi. Memberikan bingkisan bukan sekadar tradisi, melainkan wujud apresiasi dan doa baik.\n\nDalam memilih hampers, perhatikan ketahanan produk makanan seperti kue kering dengan butter wisman, kurma ajwa kemasan higienis, dan kartu ucapan personal. RAMELA Hampers menghadirkan pilihan box beludru dan akrilik transparan berkesan mewah yang siap diantar langsung ke penerima.",
                'store_id' => $hampers?->id,
                'published_at' => now()->subDays(9),
            ],
            [
                'title' => 'Mengenal Mutu Beton Cor: K-225 vs K-300 untuk Konstruksi Rumah dan Ruko Kokoh',
                'excerpt' => 'Pelajari perbedaan kuat tekan karakteristik beton cor agar struktur bangunan Anda tahan gempa, awet puluhan tahun, dan efisien biaya.',
                'content' => "Dalam dunia konstruksi, angka di belakang huruf 'K' menandakan kuat tekan karakteristik beton per sentimeter persegi pada umur 28 hari. Untuk rumah tinggal 2 lantai, mutu K-225 sudah sangat memadai untuk balok dan plat lantai.\n\nNamun untuk bangunan bertingkat 3 atau lantai ruko yang menampung beban rak barang, mutu K-300 adalah standar keamanan yang dianjurkan. RAMELA Beton menyediakan beton siap pakai berkualitas dengan slump terjaga yang memudahkan pemompaan concrete pump.",
                'store_id' => $beton?->id,
                'published_at' => now()->subDays(6),
            ],
            [
                'title' => 'Inovasi Saluran Air U-Ditch Precast: Solusi Cepat Drainase Bebas Banjir',
                'excerpt' => 'Mengapa menggunakan saluran pracetak U-Ditch jauh lebih hemat waktu dan kuat dibanding metode cor manual di lapangan.',
                'content' => "Pekerjaan drainase konvensional seringkali terkendala cuaca hujan dan bekisting yang mudah bocor. Dengan U-Ditch precast dari RAMELA Beton, pemasangan saluran parit perumahan dapat diselesaikan hingga 3 kali lebih cepat dengan mutu beton teruji K-350 kedap air.",
                'store_id' => $beton?->id,
                'published_at' => now()->subDays(4),
            ],
            [
                'title' => 'Tips Merawat Parcel Buah Segar Agar Tetap Fresh dan Bernutrisi Tinggi',
                'excerpt' => 'Cara menyimpan aneka buah impor dan lokal dari parcel bingkisan agar kesegaran dan vitaminnya terjaga maksimal.',
                'content' => "Mendapatkan kiriman parcel buah segar adalah berkah kesehatan. Pisahkan buah yang menghasilkan gas etilen tinggi seperti apel dan pisang dari buah lain agar tidak cepat matang berlebih. Simpan buah anggur dan pear di chiller lemari pendingin untuk kesegaran optimal hingga 2 minggu.",
                'store_id' => $hampers?->id,
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Peluncuran Fitur Live Tracking Kurir Realtime & Ongkir Otomatis RAMELA',
                'excerpt' => 'Kemudahan baru berbelanja: transparansi pelacakan pergerakan kurir dan hitungan ongkir cerdas per kilogram kini hadir di platform RAMELA.',
                'content' => "Platform RAMELA terus berinovasi memberikan kenyamanan terbaik bagi para pelanggan. Kini Anda dapat memantau pergerakan kurir pengantar makanan dan hampers secara live di peta interaktif, lengkap dengan estimasi waktu dan foto konfirmasi saat barang diserahkan.",
                'store_id' => null,
                'published_at' => now()->subDay(),
            ],
        ];

        foreach ($blogs as $b) {
            Blog::updateOrCreate(
                ['slug' => Str::slug($b['title'])],
                [
                    'author_id' => $authorId,
                    'store_id' => $b['store_id'],
                    'title' => $b['title'],
                    'excerpt' => $b['excerpt'],
                    'content' => $b['content'],
                    'is_published' => true,
                    'published_at' => $b['published_at'],
                ]
            );
        }
    }
}

