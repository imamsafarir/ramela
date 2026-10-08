<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Dapatkan API Key Google Maps dari Pengaturan Web atau .env
     */
    public function getGoogleApiKey(): ?string
    {
        $dbKey = $this->settings->get('google.maps_api_key');
        if (filled($dbKey)) {
            return trim($dbKey);
        }

        $envKey = config('services.google.maps_key');
        if (filled($envKey)) {
            return trim($envKey);
        }

        return null;
    }

    /**
     * Reverse geocoding: Ubah koordinat (lat, lng) menjadi nama jalan dan komponen alamat.
     * Mengutamakan Google Geocoding API, fallback otomatis ke OpenStreetMap Nominatim.
     */
    public function reverse(float $lat, float $lng): array
    {
        $apiKey = $this->getGoogleApiKey();

        if ($apiKey) {
            $googleResult = $this->reverseFromGoogle($lat, $lng, $apiKey);
            if ($googleResult !== null) {
                return $googleResult;
            }
        }

        // Fallback ke OpenStreetMap Nominatim
        return $this->reverseFromNominatim($lat, $lng);
    }

    /**
     * Pencarian alamat / nama jalan (Autocomplete / Search).
     */
    public function search(string $query): array
    {
        $apiKey = $this->getGoogleApiKey();

        if ($apiKey) {
            $googleResults = $this->searchFromGoogle($query, $apiKey);
            if (!empty($googleResults)) {
                return $googleResults;
            }
        }

        // Fallback ke Nominatim
        return $this->searchFromNominatim($query);
    }

    /**
     * Ambil nama jalan & detail alamat dari Google Maps Geocoding API
     */
    protected function reverseFromGoogle(float $lat, float $lng, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $apiKey,
                'language' => 'id',
            ]);

            if (!$response->successful()) {
                Log::warning('Google Geocoding HTTP error: ' . $response->status());
                return null;
            }

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
                Log::info('Google Geocoding non-OK status: ' . ($data['status'] ?? 'unknown'));
                return null;
            }

            $first = $data['results'][0];
            $components = $first['address_components'] ?? [];

            $streetNumber = null;
            $route = null;
            $sublocality = null; // Kelurahan / Desa
            $district = null;    // Kecamatan
            $city = null;        // Kota / Kabupaten
            $province = null;
            $postalCode = null;

            foreach ($components as $comp) {
                $types = $comp['types'] ?? [];
                if (in_array('street_number', $types)) {
                    $streetNumber = $comp['long_name'];
                }
                if (in_array('route', $types)) {
                    $route = $comp['long_name'];
                }
                if (in_array('sublocality_level_1', $types) || in_array('sublocality', $types) || in_array('administrative_area_level_4', $types)) {
                    $sublocality = $sublocality ?: $comp['long_name'];
                }
                if (in_array('administrative_area_level_3', $types) || in_array('locality', $types)) {
                    $district = $district ?: $comp['long_name'];
                }
                if (in_array('administrative_area_level_2', $types)) {
                    $city = $city ?: $comp['long_name'];
                }
                if (in_array('administrative_area_level_1', $types)) {
                    $province = $comp['long_name'];
                }
                if (in_array('postal_code', $types)) {
                    $postalCode = $comp['long_name'];
                }
            }

            // Susun nama jalan
            $streetName = $route;
            if ($route && $streetNumber) {
                $streetName = "{$route} No. {$streetNumber}";
            }

            $formatted = $first['formatted_address'] ?? '';
            // Bersihkan akhiran ", Indonesia" jika ada untuk ringkas
            $formattedClean = preg_replace('/, Indonesia$/i', '', $formatted);

            // Format kecamatan & kelurahan
            $districtFormatted = '';
            if ($district && $sublocality) {
                $districtFormatted = "{$district}, {$sublocality}";
            } elseif ($district) {
                $districtFormatted = $district;
            } elseif ($sublocality) {
                $districtFormatted = $sublocality;
            }

            return [
                'success' => true,
                'source' => 'google',
                'street_name' => $streetName ?: $formattedClean,
                'formatted_address' => $formattedClean,
                'district' => $districtFormatted,
                'sublocality' => $sublocality ?? '',
                'city' => $city ?? '',
                'province' => $province ?? '',
                'postal_code' => $postalCode ?? '',
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        } catch (\Throwable $e) {
            Log::warning('Google Geocoding exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback: Ambil nama jalan dari OpenStreetMap Nominatim
     */
    protected function reverseFromNominatim(float $lat, float $lng): array
    {
        $cacheKey = 'geocode.osm.' . round($lat, 5) . '.' . round($lng, 5);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($lat, $lng) {
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'User-Agent' => 'RAMELA-E-Commerce/1.0 (info@ramela.local)',
                    ])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'lat' => $lat,
                        'lon' => $lng,
                        'format' => 'jsonv2',
                        'zoom' => 18,
                        'addressdetails' => 1,
                        'accept-language' => 'id',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $addr = $data['address'] ?? [];

                    $road = $addr['road']
                        ?? $addr['pedestrian']
                        ?? $addr['footway']
                        ?? $addr['residential']
                        ?? $addr['neighbourhood']
                        ?? $addr['suburb']
                        ?? null;

                    $houseNumber = $addr['house_number'] ?? null;

                    $streetName = $road;
                    if ($road && $houseNumber) {
                        $streetName = "{$road} No. {$houseNumber}";
                    }

                    $suburb = $addr['suburb'] ?? $addr['village'] ?? $addr['quarter'] ?? $addr['hamlet'] ?? '';
                    $district = $addr['city_district'] ?? $addr['county'] ?? $addr['municipality'] ?? '';
                    $city = $addr['city'] ?? $addr['town'] ?? $addr['state_district'] ?? '';
                    $province = $addr['state'] ?? '';
                    $postcode = $addr['postcode'] ?? '';

                    $districtFormatted = '';
                    if ($district && $suburb) {
                        $districtFormatted = "{$district}, {$suburb}";
                    } elseif ($district) {
                        $districtFormatted = $district;
                    } elseif ($suburb) {
                        $districtFormatted = $suburb;
                    }

                    // Format alamat rapi
                    $parts = array_filter([$streetName, $suburb, $district, $city, $province, $postcode]);
                    $cleanFormatted = !empty($parts) ? implode(', ', $parts) : ($data['display_name'] ?? '');
                    $cleanFormatted = preg_replace('/, Indonesia$/i', '', $cleanFormatted);

                    return [
                        'success' => true,
                        'source' => 'osm',
                        'street_name' => $streetName ?: ($cleanFormatted ?: "Titik ({$lat}, {$lng})"),
                        'formatted_address' => $cleanFormatted ?: "Koordinat {$lat}, {$lng}",
                        'district' => $districtFormatted,
                        'sublocality' => $suburb,
                        'city' => $city,
                        'province' => $province,
                        'postal_code' => $postcode,
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Nominatim reverse exception: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'source' => 'fallback',
                'street_name' => "Koordinat {$lat}, {$lng}",
                'formatted_address' => "Titik Koordinat: {$lat}, {$lng}",
                'district' => '',
                'sublocality' => '',
                'city' => '',
                'province' => '',
                'postal_code' => '',
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        });
    }

    /**
     * Pencarian alamat menggunakan Google Geocoding
     */
    protected function searchFromGoogle(string $query, string $apiKey): array
    {
        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $query,
                'key' => $apiKey,
                'language' => 'id',
                'components' => 'country:id',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'OK' && !empty($data['results'])) {
                    $results = [];
                    foreach (array_slice($data['results'], 0, 5) as $res) {
                        $loc = $res['geometry']['location'] ?? [];
                        $cleanAddr = preg_replace('/, Indonesia$/i', '', $res['formatted_address'] ?? '');
                        $results[] = [
                            'title' => explode(',', $cleanAddr)[0] ?? $cleanAddr,
                            'address' => $cleanAddr,
                            'latitude' => (float) ($loc['lat'] ?? 0),
                            'longitude' => (float) ($loc['lng'] ?? 0),
                            'source' => 'google',
                        ];
                    }
                    return $results;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Google Search exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Pencarian alamat menggunakan OpenStreetMap Nominatim
     */
    protected function searchFromNominatim(string $query): array
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'RAMELA-E-Commerce/1.0 (info@ramela.local)',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 5,
                    'countrycodes' => 'id',
                    'addressdetails' => 1,
                    'accept-language' => 'id',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = [];
                foreach ($data as $item) {
                    $cleanName = preg_replace('/, Indonesia$/i', '', $item['display_name'] ?? '');
                    $results[] = [
                        'title' => explode(',', $cleanName)[0] ?? $cleanName,
                        'address' => $cleanName,
                        'latitude' => (float) $item['lat'],
                        'longitude' => (float) $item['lon'],
                        'source' => 'osm',
                    ];
                }
                return $results;
            }
        } catch (\Throwable $e) {
            Log::warning('Nominatim search exception: ' . $e->getMessage());
        }

        return [];
    }
}

