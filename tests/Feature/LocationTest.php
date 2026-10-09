<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('reverse geocode endpoint memvalidasi lat dan lng', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/location/reverse')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lat', 'lng']);

    $this->actingAs($user)
        ->getJson('/api/location/reverse?lat=100&lng=200')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lat', 'lng']);
});

test('reverse geocode berhasil mengambil nama jalan lewat google saat api key tersedia', function () {
    $user = User::factory()->create();
    $settings = app(SettingsService::class);
    $settings->set('google.maps_api_key', 'fake-google-key', encrypted: true);

    Http::fake([
        'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
            'status' => 'OK',
            'results' => [
                [
                    'formatted_address' => 'Jl. Pandanaran No.58, Mugassari, Semarang Selatan, Kota Semarang, Jawa Tengah 50249, Indonesia',
                    'address_components' => [
                        ['long_name' => '58', 'short_name' => '58', 'types' => ['street_number']],
                        ['long_name' => 'Jl. Pandanaran', 'short_name' => 'Jl. Pandanaran', 'types' => ['route']],
                        ['long_name' => 'Mugassari', 'short_name' => 'Mugassari', 'types' => ['sublocality_level_1']],
                        ['long_name' => 'Semarang Selatan', 'short_name' => 'Semarang Sel.', 'types' => ['administrative_area_level_3']],
                        ['long_name' => 'Kota Semarang', 'short_name' => 'Kota Semarang', 'types' => ['administrative_area_level_2']],
                        ['long_name' => '50249', 'short_name' => '50249', 'types' => ['postal_code']],
                    ],
                ],
            ],
        ], 200),
    ]);

    $res = $this->actingAs($user)
        ->getJson('/api/location/reverse?lat=-6.989720&lng=110.421930')
        ->assertOk()
        ->json();

    expect($res['success'])->toBeTrue()
        ->and($res['source'])->toBe('google')
        ->and($res['street_name'])->toBe('Jl. Pandanaran No. 58')
        ->and($res['postal_code'])->toBe('50249')
        ->and($res['city'])->toBe('Kota Semarang');
});

test('reverse geocode otomatis fallback ke openstreetmap saat google api key kosong atau gagal', function () {
    $user = User::factory()->create();
    $settings = app(SettingsService::class);
    $settings->set('google.maps_api_key', '');

    Http::fake([
        'https://nominatim.openstreetmap.org/reverse*' => Http::response([
            'display_name' => 'Jalan Pemuda, Sekayu, Semarang Tengah, Kota Semarang, Jawa Tengah, 50132, Indonesia',
            'address' => [
                'road' => 'Jalan Pemuda',
                'house_number' => '142',
                'suburb' => 'Sekayu',
                'city_district' => 'Semarang Tengah',
                'city' => 'Kota Semarang',
                'postcode' => '50132',
            ],
        ], 200),
    ]);

    $res = $this->actingAs($user)
        ->getJson('/api/location/reverse?lat=-6.973050&lng=110.428510')
        ->assertOk()
        ->json();

    expect($res['success'])->toBeTrue()
        ->and($res['source'])->toBe('osm')
        ->and($res['street_name'])->toBe('Jalan Pemuda No. 142')
        ->and($res['postal_code'])->toBe('50132');
});

test('pencarian alamat mengembalikan daftar lokasi dengan kecamatan dan kode pos', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://nominatim.openstreetmap.org/search*' => Http::response([
            [
                'display_name' => 'Jalan Pandanaran, Mugassari, Semarang Selatan, Kota Semarang, 50241, Indonesia',
                'lat' => '-6.989720',
                'lon' => '110.421930',
                'address' => [
                    'road' => 'Jalan Pandanaran',
                    'suburb' => 'Mugassari',
                    'city_district' => 'Semarang Selatan',
                    'city' => 'Kota Semarang',
                    'postcode' => '50241',
                ],
            ],
        ], 200),
    ]);

    $res = $this->actingAs($user)
        ->getJson('/api/location/search?q=Pandanaran')
        ->assertOk()
        ->json();

    expect($res['success'])->toBeTrue()
        ->and(count($res['results']))->toBeGreaterThanOrEqual(1)
        ->and($res['results'][0]['latitude'])->toBe(-6.98972)
        ->and($res['results'][0]['longitude'])->toBe(110.42193)
        ->and($res['results'][0]['street_name'])->toBe('Jalan Pandanaran')
        ->and($res['results'][0]['district'])->toBe('Kec. Semarang Selatan, Kel. Mugassari')
        ->and($res['results'][0]['postal_code'])->toBe('50241');
});

