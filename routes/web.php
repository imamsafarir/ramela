<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CourierController as AdminCourierController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\TransactionBypassController as AdminTransactionBypassController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Courier\CourierController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\User\TopupController;
use App\Http\Controllers\User\CartController;
use App\Http\Controllers\User\CheckoutController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\OrderController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\StoreController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ContentController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/blog', [ContentController::class, 'blogs'])->name('blogs.index');
Route::get('/blog/{slug}', [ContentController::class, 'blogDetail'])->name('blogs.show');
Route::get('/faq', [ContentController::class, 'faqs'])->name('faqs.index');

// Webhook Midtrans: publik, CSRF dikecualikan di bootstrap/app.php, divalidasi lewat signature.
Route::post('/midtrans/notification', MidtransWebhookController::class)
    ->middleware('throttle:120,1')->name('midtrans.notification');

// --- Tamu ---
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// --- Pengguna & Belanja (Dapat diakses juga oleh Admin & Super Admin) ---
Route::middleware(['auth', 'role:pengguna,super_admin,admin'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/toko/{store:slug}', [StoreController::class, 'show'])->name('store.show');

    Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
    Route::post('/keranjang', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/keranjang/{id}', [CartController::class, 'update'])->whereNumber('id')->name('cart.update');
    Route::delete('/keranjang/{id}', [CartController::class, 'destroy'])->whereNumber('id')->name('cart.destroy');

    Route::get('/checkout/{store:slug}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{store:slug}', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

    Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{invoice}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('/topup', [TopupController::class, 'index'])->name('topup.index');
    Route::post('/topup', [TopupController::class, 'store'])->middleware('throttle:5,1')->name('topup.store');
    Route::post('/topup/{orderId}/sync', [TopupController::class, 'sync'])->middleware('throttle:20,1')->name('topup.sync');
});

// --- Admin ---
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::resource('produk', AdminProductController::class)
        ->parameters(['produk' => 'product'])->except('show')
        ->names(['index' => 'products.index', 'create' => 'products.create', 'store' => 'products.store',
            'edit' => 'products.edit', 'update' => 'products.update', 'destroy' => 'products.destroy']);

    Route::get('/kategori', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/kategori', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::delete('/kategori/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/pesanan', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{invoice}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/pesanan/{invoice}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

    // Kurir & Monitoring Pengiriman
    Route::get('/kurir', [AdminCourierController::class, 'index'])->name('couriers.index');
    Route::post('/kurir/assign', [AdminCourierController::class, 'assign'])->name('couriers.assign');

    // Manajemen Pengguna (Pindahan dari dewa-panel)
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
    Route::put('/users/{user}/password', [AdminUserController::class, 'resetPassword'])->name('users.password');
    Route::post('/users/{user}/saldo', [AdminUserController::class, 'adjustBalance'])->name('users.balance');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    // Pengaturan Website & Payment Gateway (Khusus Super Admin)
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/pengaturan', [AdminSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/pengaturan', [AdminSettingsController::class, 'update'])->name('settings.update');

        // Bypass & Koreksi Transaksi (Khusus Super Admin)
        Route::get('/transaksi', [AdminTransactionBypassController::class, 'index'])->name('transactions.index');
        Route::post('/transaksi/{invoice}/status', [AdminTransactionBypassController::class, 'forceStatus'])->name('transactions.status');
    });

    Route::get('/promo', [\App\Http\Controllers\Admin\PromoController::class, 'index'])->name('promos.index');
    Route::post('/promo', [\App\Http\Controllers\Admin\PromoController::class, 'store'])->name('promos.store');
    Route::patch('/promo/{promo}/toggle', [\App\Http\Controllers\Admin\PromoController::class, 'toggle'])->name('promos.toggle');
    Route::delete('/promo/{promo}', [\App\Http\Controllers\Admin\PromoController::class, 'destroy'])->name('promos.destroy');
    Route::get('/promo/{promo}/log', [\App\Http\Controllers\Admin\PromoController::class, 'log'])->name('promos.log');

    Route::get('/blog', [\App\Http\Controllers\Admin\BlogController::class, 'index'])->name('blogs.index');
    Route::post('/blog', [\App\Http\Controllers\Admin\BlogController::class, 'store'])->name('blogs.store');
    Route::patch('/blog/{blog}/toggle', [\App\Http\Controllers\Admin\BlogController::class, 'toggle'])->name('blogs.toggle');
    Route::delete('/blog/{blog}', [\App\Http\Controllers\Admin\BlogController::class, 'destroy'])->name('blogs.destroy');

    Route::get('/faq', [\App\Http\Controllers\Admin\FaqController::class, 'index'])->name('faqs.index');
    Route::post('/faq', [\App\Http\Controllers\Admin\FaqController::class, 'store'])->name('faqs.store');
    Route::patch('/faq/{faq}/toggle', [\App\Http\Controllers\Admin\FaqController::class, 'toggle'])->name('faqs.toggle');
    Route::delete('/faq/{faq}', [\App\Http\Controllers\Admin\FaqController::class, 'destroy'])->name('faqs.destroy');
});

// --- Kurir Mobile PWA ---
Route::middleware(['auth', 'role:kurir'])->prefix('kurir')->name('courier.')->group(function () {
    Route::get('/', [CourierController::class, 'index'])->name('dashboard');
    Route::post('/tugas/{invoice}/ambil', [CourierController::class, 'claim'])->name('claim');
    Route::post('/pickup', [CourierController::class, 'pickup'])->name('pickup');
    Route::post('/location', [CourierController::class, 'updateLocation'])->name('location');
    Route::post('/dropoff', [CourierController::class, 'dropoff'])->name('dropoff');
});

