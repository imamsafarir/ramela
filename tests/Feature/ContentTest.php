<?php

use App\Enums\Role;
use App\Models\Blog;
use App\Models\Faq;
use App\Models\Store;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }

    $this->store = Store::create(['slug' => 'eats', 'name' => 'RAMELA EATS'])->refresh();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->refresh();

    $this->customer = User::factory()->create();
    $this->customer->assignRole('pengguna');
    $this->customer->refresh();
});

test('halaman landing page publik menampilkan toko, artikel blog terbaru, dan faq', function () {
    Blog::create([
        'author_id' => $this->admin->id,
        'title' => 'Resep Rahasia Ramela',
        'slug' => 'resep-rahasia-ramela',
        'content' => 'Konten lengkap resep rahasia...',
        'is_published' => true,
        'published_at' => now(),
    ]);

    Faq::create([
        'question' => 'Bagaimana cara pesan?',
        'answer' => 'Cukup pilih produk dan checkout dengan saldo.',
        'is_active' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('stores', 1)
            ->has('recentBlogs', 1)
            ->has('faqs', 1));
});

test('halaman publik blog dan detail blog dengan seo meta', function () {
    $blog = Blog::create([
        'author_id' => $this->admin->id,
        'store_id' => $this->store->id,
        'title' => 'Tips Memilih Menu Enak',
        'slug' => 'tips-memilih-menu-enak',
        'excerpt' => 'Ringkasan tips memilih kuliner.',
        'content' => 'Isi artikel lengkap dan mendalam.',
        'meta_title' => 'Tips Kuliner Terbaik',
        'meta_description' => 'Deskripsi SEO artikel kuliner.',
        'is_published' => true,
        'published_at' => now(),
    ]);

    // Artikel draft tidak boleh tampil di halaman publik
    Blog::create([
        'author_id' => $this->admin->id,
        'title' => 'Draft Artikel',
        'slug' => 'draft-artikel',
        'content' => 'Masih draft',
        'is_published' => false,
    ]);

    $this->get('/blog')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Blogs')
            ->has('blogs.data', 1));

    $this->get('/blog/tips-memilih-menu-enak')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/BlogDetail')
            ->where('blog.title', 'Tips Memilih Menu Enak')
            ->where('blog.meta_title', 'Tips Kuliner Terbaik'));

    // Buka draft langsung harus 404
    $this->get('/blog/draft-artikel')->assertNotFound();
});

test('halaman publik faq menampilkan pertanyaan yang aktif saja', function () {
    Faq::create(['question' => 'Tanya 1', 'answer' => 'Jawab 1', 'is_active' => true]);
    Faq::create(['question' => 'Tanya 2', 'answer' => 'Jawab 2', 'is_active' => false]);

    $this->get('/faq')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Faqs')
            ->has('faqs', 1));
});

test('toggle fitur blog dan faq di web_settings memblokir akses publik saat dimatikan', function () {
    $settings = app(SettingsService::class);
    $settings->set('feature.blog', 'false');
    $settings->set('feature.faq', 'false');

    $this->get('/blog')->assertNotFound();
    $this->get('/faq')->assertNotFound();

    // Pulihkan fitur
    $settings->set('feature.blog', 'true');
    $settings->set('feature.faq', 'true');

    $this->get('/blog')->assertOk();
    $this->get('/faq')->assertOk();
});

test('admin dapat mengelola artikel blog (tambah, toggle publikasi, hapus)', function () {
    Storage::fake('public');

    // Pengguna biasa dilarang masuk
    $this->actingAs($this->customer)->get('/admin/blog')->assertNotFound();

    // Admin buka index blog
    $this->actingAs($this->admin)->get('/admin/blog')->assertOk();

    // Admin buat artikel
    $this->actingAs($this->admin)->post('/admin/blog', [
        'title' => 'Panduan Konstruksi Beton Ramela',
        'content' => 'Beton cor berkualitas tinggi untuk pondasi rumah Anda.',
        'excerpt' => 'Panduan lengkap beton cor.',
        'thumbnail' => UploadedFile::fake()->image('thumb.jpg'),
        'is_published' => true,
    ])->assertSessionHasNoErrors();

    $blog = Blog::where('slug', 'panduan-konstruksi-beton-ramela')->first();
    expect($blog)->not->toBeNull()
        ->and($blog->is_published)->toBeTrue()
        ->and($blog->author_id)->toBe($this->admin->id);

    // Toggle publikasi
    $this->actingAs($this->admin)->patch("/admin/blog/{$blog->id}/toggle");
    expect($blog->fresh()->is_published)->toBeFalse();

    // Hapus artikel
    $this->actingAs($this->admin)->delete("/admin/blog/{$blog->id}");
    expect($blog->fresh()->trashed())->toBeTrue();
});

test('admin dapat mengelola FAQ (tambah, toggle aktif, hapus)', function () {
    $this->actingAs($this->customer)->get('/admin/faq')->assertNotFound();

    $this->actingAs($this->admin)->post('/admin/faq', [
        'question' => 'Apakah kurir memiliki live tracking?',
        'answer' => 'Ya, kurir menggunakan GPS real-time selama pengantaran.',
        'sort_order' => 1,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $faq = Faq::where('question', 'Apakah kurir memiliki live tracking?')->first();
    expect($faq)->not->toBeNull();

    // Toggle status
    $this->actingAs($this->admin)->patch("/admin/faq/{$faq->id}/toggle");
    expect($faq->fresh()->is_active)->toBeFalse();

    // Hapus
    $this->actingAs($this->admin)->delete("/admin/faq/{$faq->id}");
    expect(Faq::find($faq->id))->toBeNull();
});
