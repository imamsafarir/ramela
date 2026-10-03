# RAMELA - Platform Ekosistem Terpadu
> **Ramela Eats • Ramela Hampers • Ramela Beton**  
> Dilengkapi Layanan Dompet Digital (Midtrans), Pelacakan Kurir Real-Time GPS, Progressive Web App (PWA), dan Sistem Manajemen Multi-Role.

---

## 📋 Daftar Isi
1. [Fitur Utama](#-fitur-utama)
2. [Prasyarat Server & Sistem](#-prasyarat-server--sistem)
3. [Panduan Instalasi Baru di aaPanel](#-panduan-instalasi-baru-di-aapanel)
4. [Bypass & Konfigurasi Cloudflare Tunnel](#-bypass--konfigurasi-cloudflare-tunnel)
5. [Panduan Update Rutin Melalui Terminal (Git)](#-panduan-update-rutin-melalui-terminal-git)
6. [Progressive Web App (PWA)](#-progressive-web-app-pwa)
7. [Akun Default Demo (Hasil Seeder)](#-akun-default-demo)
8. [Troubleshooting & Solusi Masalah Umum](#-troubleshooting--solusi-masalah-umum)

---

## 🚀 Fitur Utama
- **3 Unit Bisnis Terintegrasi:** Ramela Eats (Kuliner), Ramela Hampers (Bingkisan), dan Ramela Beton (Material & Konstruksi) dalam satu platform transaksi.
- **Dompet Digital & Top-up:** Saldo terpusat dengan gateway pembayaran Midtrans (Snap, QRIS, Virtual Account, GoPay) serta idempoten callback.
- **Pelacakan Kurir Real-Time:** 
  - Validasi foto pickup saat pengambilan barang oleh kurir.
  - Geolokasi GPS kurir real-time diperbarui ke server dan dipantau di peta interaktif pelanggan (*Leaflet & OpenStreetMap*).
  - Validasi foto dropoff saat paket tiba di tujuan.
- **Multi-Role User:**
  - `Super Admin`: Kendali mutlak, kelola role, reset sandi, koreksi saldo manual, pengaturan sistem, bypass transaksi.
  - `Admin`: Kelola katalog produk, pesanan, kurir, artikel blog, dan FAQ.
  - `Kurir`: Antarmuka khusus kurir dengan bottom navigation, GPS tracking, dan upload bukti antar.
  - `Pelanggan`: Transaksi belanja, mutasi saldo, pelacakan order live, dan profile management.
- **PWA Siap Pasang di Semua Perangkat:** Android, iOS, Windows, Mac, Tablet. Langsung diarahkan ke halaman login (`/login`) saat dibuka dari homescreen.

---

## 💻 Prasyarat Server & Sistem
Jika dipasang pada server Linux (Ubuntu 20.04/22.04/24.04, Debian 11/12, atau AlmaLinux) dengan **aaPanel**:
- **Nginx** 1.22+
- **PHP** 8.2 atau 8.3
  - Ekstensi PHP wajib: `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `gd`, `zip`, `openssl`, `bcmath`, `xml`
- **MySQL** 8.0+ atau **MariaDB** 10.6+
- **Composer** v2+
- **Node.js** v18+ atau v20+ LTS & **NPM**
- **Git**
- **Cloudflared** *(opsional, jika menggunakan Cloudflare Tunnel untuk aaPanel lokal tanpa IP Publik)*

---

## 🛠 Panduan Instalasi Baru di aaPanel

### 1. Buat Website di aaPanel
1. Masuk ke dashboard aaPanel Anda.
2. Buka menu **Website** > klik tombol **Add site**.
3. Masukkan nama domain (misal: `ramela.domainanda.com` atau domain lokal).
4. Pilih **Database**: Buat database baru (catat nama DB, username, dan password).
5. Pilih **PHP Version**: `PHP-82`.
6. Klik **Submit**.

### 2. Konfigurasi Nginx di aaPanel
Buka menu pengaturan situs yang baru dibuat di aaPanel:
1. **Site directory**:
   - `Site directory`: `/www/wwwroot/ramela` (atau nama folder yang Anda pilih).
   - `Running directory`: Ubah menjadi **`/public`** lalu klik **Save**.
2. **URL rewrite**:
   - Pilih template preset **`laravel5`** atau tempelkan konfigurasi berikut:
     ```nginx
     location / {
         try_files $uri $uri/ /index.php?$query_string;
     }
     ```
   - Klik **Save**.
3. **Pengaturan PHP di aaPanel (Penting)**:
   - Buka menu **App Store** di aaPanel > cari **PHP-8.2** > klik **Setting**.
   - Masuk ke tab **Disabled functions**: Pastikan fungsi `putenv`, `proc_open`, dan `pcntl_signal` **dihapus** dari daftar disabled functions agar Composer dan Artisan berjalan normal.
   - Masuk ke tab **Install extensions**: Pastikan `fileinfo` dan `gd` terpasang.

---

### 3. Eksekusi Perintah Terminal (First Time Deployment)

Buka menu **Terminal** di aaPanel atau SSH ke server Anda, lalu jalankan perintah berikut secara berurutan:

```bash
# 1. Pindah ke direktori web aaPanel
cd /www/wwwroot

# 2. Clone repositori dari GitHub
git clone https://github.com/USERNAME_ANDA/ramela.git ramela

# 3. Masuk ke direktori proyek
cd ramela

# 4. Salin file environment
cp .env.example .env
```

#### Edit file `.env`:
Gunakan editor `nano .env` di terminal atau edit langsung melalui menu **Files** di aaPanel:
```ini
APP_NAME="RAMELA"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ramela.domainanda.com

# Wajib TRUE jika menggunakan Cloudflare Tunnel / SSL Proxy
FORCE_HTTPS=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_aapanel
DB_USERNAME=username_database_aapanel
DB_PASSWORD=password_database_aapanel

# Midtrans (Jika sudah ada akun Midtrans Production / Sandbox)
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
```

#### Lanjutkan instalasi backend & frontend di Terminal:
```bash
# 5. Install dependensi PHP backend
composer install --no-dev --optimize-autoloader

# 6. Generate enkripsi application key
php artisan key:generate

# 7. Hubungkan storage publik untuk upload foto/kurir
php artisan storage:link

# 8. Jalankan migrasi tabel database dan data awal (seeder)
php artisan migrate --force --seed

# 9. Install dependensi JavaScript dan build aset frontend
npm install
npm run build

# 10. Berikan hak akses folder ke user Nginx aaPanel (www)
chown -R www:www /www/wwwroot/ramela
chmod -R 755 /www/wwwroot/ramela
chmod -R 775 /www/wwwroot/ramela/storage /www/wwwroot/ramela/bootstrap/cache

# 11. Optimalkan cache Laravel untuk kecepatan maksimal
php artisan optimize
```

Selesai! Aplikasi RAMELA sudah dapat diakses melalui browser Anda.

---

## 🌐 Bypass & Konfigurasi Cloudflare Tunnel

Jika aaPanel Anda berada di server lokal (tanpa IP publik statis / di balik CGNAT ISP), Anda dapat menggunakan **Cloudflare Tunnel (`cloudflared`)** untuk mengekspos aplikasi ke internet secara aman dengan SSL gratis.

> **Laravel RAMELA sudah otomatis mendukung Cloudflare Tunnel out-of-the-box!**  
> Konfigurasi `trustProxies(at: '*')` dan `URL::forceScheme('https')` sudah aktif, sehingga tidak akan terjadi error mixed-content, infinite redirect, ataupun cookie CSRF mismatch.

### Langkah Setup Cloudflare Tunnel di Server aaPanel (Linux):

#### 1. Pasang cloudflared
```bash
# Untuk Ubuntu / Debian
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb
```

#### 2. Login & Buat Tunnel
```bash
# Login akun Cloudflare (buka tautan yang muncul di browser)
cloudflared tunnel login

# Buat tunnel baru bernama "ramela-tunnel"
cloudflared tunnel create ramela-tunnel
```
*(Catat UUID Tunnel dan lokasi file kredensial JSON yang dihasilkan, misalnya `/root/.cloudflared/<TUNNEL_ID>.json`)*.

#### 3. Buat File Konfigurasi Tunnel
Buat file `~/.cloudflared/config.yml`:
```yaml
tunnel: <TUNNEL_ID_ANDA>
credentials-file: /root/.cloudflared/<TUNNEL_ID_ANDA>.json

ingress:
  # Arahkan hostname publik ke port web server Nginx aaPanel lokal
  - hostname: ramela.domainanda.com
    service: http://127.0.0.1:80
    originRequest:
      noTLSVerify: true
  - service: http_status:404
```

#### 4. Arahkan DNS Cloudflare ke Tunnel
```bash
cloudflared tunnel route dns ramela-tunnel ramela.domainanda.com
```

#### 5. Daftarkan sebagai Layanan Otomatis (Systemd Daemon)
```bash
# Pasang sebagai system service
cloudflared service install

# Jalankan dan aktifkan saat reboot server
systemctl start cloudflared
systemctl enable cloudflared
```

Sekarang Anda dapat membuka `https://ramela.domainanda.com` dari perangkat mana pun di dunia.

---

## 🔄 Panduan Update Rutin Melalui Terminal (Git)

Jika Anda melakukan perubahan kode di lokal dan telah mem-push commit baru ke GitHub, lakukan langkah berikut di terminal aaPanel untuk memperbarui server produksi:

### ⚡ Perintah Cepat 1-Baris (Copy & Paste):
```bash
cd /www/wwwroot/ramela && git pull origin main && composer install --no-dev --optimize-autoloader && php artisan migrate --force && npm run build && php artisan optimize:clear && php artisan optimize && chown -R www:www storage bootstrap/cache
```

---

### 📝 Penjelasan Perintah Langkah demi Langkah:

```bash
# 1. Masuk ke folder proyek
cd /www/wwwroot/ramela

# 2. Tarik kode terbaru dari repositori GitHub
git pull origin main

# 3. Perbarui paket dependensi PHP (jika ada composer.json baru)
composer install --no-dev --optimize-autoloader

# 4. Terapkan pembaruan database secara aman tanpa konfirmasi interaktif
php artisan migrate --force

# 5. Build ulang aset Vue & CSS jika ada perubahan tampilan
npm install
npm run build

# 6. Bersihkan cache konfigurasi lama
php artisan optimize:clear

# 7. Bangun ulang cache konfigurasi, route, dan blade view
php artisan optimize

# 8. Pastikan izin folder storage tetap dapat ditulis oleh web server
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## 📱 Progressive Web App (PWA)

Aplikasi RAMELA telah dilengkapi konfigurasi PWA tingkat produksi:
- **Direct Login Launch**: Ketika aplikasi dibuka melalui shortcut atau icon di layar utama, aplikasi langsung membuka halaman login (`/login`) sesuai alur operasional.
- **Offline Resilient**: Dilengkapi Service Worker (`/sw.js`) dengan halaman fallback koneksi terputus dan caching aset statis (CSS/JS/Icons).
- **Banner Install Interaktif**: Otomatis memunculkan banner *"Pasang Aplikasi RAMELA"* pada browser yang mendukung (Chrome, Edge, Samsung Internet, Android).

### Cara Install di Perangkat:
1. **Android (Google Chrome / Edge):**
   - Buka `https://domain-ramela-anda.com`.
   - Ketuk tombol **Install Sekarang** pada banner yang muncul di bagian bawah, atau ketuk menu titik tiga (⋮) > **Pasang Aplikasi** / **Tambahkan ke Layar Utama**.
2. **iOS / iPadOS (Safari):**
   - Buka website di Safari.
   - Ketuk tombol **Share** (ikon kotak dengan panah ke atas di bagian bawah layar).
   - Geser ke bawah lalu pilih **Tambah ke Layar Utama** (*Add to Home Screen*).
3. **Windows / Mac (Google Chrome / Microsoft Edge):**
   - Buka website di browser.
   - Klik ikon **Install** di ujung kanan address bar browser (omnibox) atau klik tombol pada banner.

---

## 🔑 Akun Default Demo

Database seeder (`php artisan db:seed`) menyediakan akun default untuk semua tingkatan hak akses:

| Role | Username | Password | Keterangan |
|---|---|---|---|
| **Super Admin** | `superadmin` | `password` | Akses manajemen penuh, kelola role, bypass, saldo |
| **Admin** | `admin` | `password` | Akses operasional toko, pesanan, kurir, konten |
| **Kurir** | `kurir` | `password` | Akses panel kurir, pickup foto, GPS live tracking |
| **Pelanggan** | `user` | `password` | Akses katalog belanja, dompet digital, pelacakan |

---

## ❓ Troubleshooting & Solusi Masalah Umum

### 1. Error `500 Server Error` atau `Permission Denied`
Biasanya disebabkan oleh izin kepemilikan folder setelah menjalankan perintah sebagai user `root`.
**Solusi:**
```bash
chown -R www:www /www/wwwroot/ramela/storage /www/wwwroot/ramela/bootstrap/cache
chmod -R 775 /www/wwwroot/ramela/storage /www/wwwroot/ramela/bootstrap/cache
```

### 2. Tampilan Berantakan / Vite Build Error
Jika aset CSS/JS belum di-compile untuk server:
**Solusi:**
```bash
npm install
npm run build
php artisan optimize:clear
```

### 3. Mixed Content Warning (Aset HTTP diakses via HTTPS)
Terjadi jika aplikasi berada di balik Cloudflare Tunnel atau SSL Proxy.
**Solusi:**
Pastikan di `.env`:
```ini
FORCE_HTTPS=true
APP_URL=https://ramela.domainanda.com
```
Lalu jalankan:
```bash
php artisan optimize:clear
php artisan optimize
```

### 4. Database Error saat Migration (`Specified key was too long`)
Jika menggunakan versi MySQL lama (< 5.7.7):
Pastikan menggunakan MySQL 8.0+ atau MariaDB 10.6+ di aaPanel.

### 5. Memeriksa Log Error Laravel
Jika terjadi kendala saat request:
```bash
tail -n 100 /www/wwwroot/ramela/storage/logs/laravel.log
```

---

<p align="center">
  <b>RAMELA Ecosystem Platform</b> • Dikembangkan dengan Laravel 12, Inertia.js, Vue 3, & Tailwind CSS.
</p>
