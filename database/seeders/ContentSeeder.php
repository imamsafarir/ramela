<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\Faq;
use App\Models\Store;
use App\Models\User;
use App\Models\WebSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::role('super_admin')->first();
        $authorId = $admin?->id ?? 1;

        $stores = Store::all()->keyBy('slug');
        $eats = $stores->get('eats');
        $hampers = $stores->get('hampers');
        $beton = $stores->get('beton');

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

        // 3. Blogs (7 rich posts)
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
