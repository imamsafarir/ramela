<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pilar Toko (Tetap 3 Saja)
        $storesData = [
            [
                'slug' => 'eats',
                'name' => 'RAMELA EATS',
                'tagline' => 'Kuliner Nusantara & Cita Rasa Otentik',
                'icon' => 'utensils',
                'sort_order' => 1,
            ],
            [
                'slug' => 'hampers',
                'name' => 'RAMELA HAMPERS',
                'tagline' => 'Bingkisan Eksklusif & Hadiah Istimewa',
                'icon' => 'gift',
                'sort_order' => 2,
            ],
            [
                'slug' => 'beton',
                'name' => 'RAMELA BETON',
                'tagline' => 'Solusi Ready Mix & Precast Beton Kokoh Berkualitas',
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
                    'icon' => $s['icon'],
                    'sort_order' => $s['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        // 2. Kategori Produk (22 Kategori across 3 Stores)
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

        // 3. Produk Katalog (112 items)
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
}
