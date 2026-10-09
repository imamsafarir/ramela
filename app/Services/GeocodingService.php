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
     * Parse komponen alamat dari Google Geocoding API
     */
    protected function parseGoogleAddressComponents(array $components, string $formatted, float $lat, float $lng): array
    {
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
            if (in_array('administrative_area_level_3', $types)) {
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

        $formattedClean = preg_replace('/, Indonesia$/i', '', $formatted);

        // Format kecamatan & kelurahan
        $districtFormatted = '';
        if ($district && $sublocality) {
            $dName = preg_replace('/^(Kecamatan|Kec\.)\s*/i', '', $district);
            $sName = preg_replace('/^(Kelurahan|Kel\.|Desa)\s*/i', '', $sublocality);
            $districtFormatted = "Kec. {$dName}, Kel. {$sName}";
        } elseif ($district) {
            $dName = preg_replace('/^(Kecamatan|Kec\.)\s*/i', '', $district);
            $districtFormatted = "Kec. {$dName}";
        } elseif ($sublocality) {
            $sName = preg_replace('/^(Kelurahan|Kel\.|Desa)\s*/i', '', $sublocality);
            $districtFormatted = "Kel. {$sName}";
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
    }

    /**
     * Parse komponen alamat dari OpenStreetMap Nominatim
     */
    protected function parseNominatimAddress(array $addr, string $displayName, float $lat, float $lng): array
    {
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

        $suburb = $addr['suburb']
            ?? $addr['village']
            ?? $addr['hamlet']
            ?? $addr['neighbourhood']
            ?? $addr['quarter']
            ?? '';

        $district = $addr['subdistrict']
            ?? $addr['city_district']
            ?? $addr['district']
            ?? $addr['county']
            ?? $addr['municipality']
            ?? '';

        $city = $addr['city']
            ?? $addr['town']
            ?? $addr['regency']
            ?? $addr['state_district']
            ?? '';

        $province = $addr['state'] ?? '';
        $postcode = $addr['postcode'] ?? '';

        // Ekstrak kode pos 5 digit dari display_name jika belum terisi di address
        if (empty($postcode) && preg_match('/\b(\d{5})\b/', $displayName, $matches)) {
            $postcode = $matches[1];
        }

        $districtFormatted = '';
        if ($district && $suburb) {
            $dName = preg_replace('/^(Kecamatan|Kec\.)\s*/i', '', $district);
            $sName = preg_replace('/^(Kelurahan|Kel\.|Desa)\s*/i', '', $suburb);
            $districtFormatted = "Kec. {$dName}, Kel. {$sName}";
        } elseif ($district) {
            $dName = preg_replace('/^(Kecamatan|Kec\.)\s*/i', '', $district);
            $districtFormatted = "Kec. {$dName}";
        } elseif ($suburb) {
            $sName = preg_replace('/^(Kelurahan|Kel\.|Desa)\s*/i', '', $suburb);
            $districtFormatted = "Kel. {$sName}";
        }

        $cleanDisplayName = preg_replace('/, Indonesia$/i', '', $displayName);

        // Susun alamat rapi
        $parts = array_filter([$streetName, $suburb, $district, $city, $province, $postcode]);
        $cleanFormatted = !empty($parts) ? implode(', ', $parts) : $cleanDisplayName;
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
            return $this->parseGoogleAddressComponents(
                $first['address_components'] ?? [],
                $first['formatted_address'] ?? '',
                $lat,
                $lng
            );
        } catch (\Throwable $e) {
            Log::warning('Google Geocoding exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback: Ambil nama jalan dari OpenStreetMap Nominatim, diperkuat BigDataCloud
     */
    protected function reverseFromNominatim(float $lat, float $lng): array
    {
        $cacheKey = 'geocode.osm.' . round($lat, 5) . '.' . round($lng, 5);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached && ($cached['source'] ?? '') !== 'fallback' && !empty($cached['district'])) {
            return $cached;
        }

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
                $parsed = $this->parseNominatimAddress(
                    $data['address'] ?? [],
                    $data['display_name'] ?? '',
                    $lat,
                    $lng
                );

                if (!empty($parsed['district'])) {
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $parsed, 86400);
                    return $parsed;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Nominatim reverse exception: ' . $e->getMessage());
        }

        // Cadangan kedua jika Nominatim gagal / tidak memiliki nama kecamatan
        $bdcResult = $this->reverseFromBigDataCloud($lat, $lng);
        if ($bdcResult !== null) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $bdcResult, 86400);
            return $bdcResult;
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
    }

    /**
     * Fallback info wilayah & administrasi via BigDataCloud Client API (gratis, tanpa API key)
     */
    protected function reverseFromBigDataCloud(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://api.bigdatacloud.net/data/reverse-geocode-client', [
                'latitude' => $lat,
                'longitude' => $lng,
                'localityLanguage' => 'id',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $admin = $data['localityInfo']['administrative'] ?? [];

                $district = null;
                $city = $data['city'] ?? null;
                $province = $data['principalSubdivision'] ?? null;
                $sublocality = $data['locality'] ?? null;
                $postcode = $data['postcode'] ?? '';

                foreach ($admin as $a) {
                    $lvl = (int) ($a['adminLevel'] ?? 0);
                    if ($lvl === 4 && empty($province)) {
                        $province = $a['name'];
                    } elseif ($lvl === 5 && empty($city)) {
                        $city = $a['name'];
                    } elseif ($lvl === 6 && empty($district)) {
                        $district = $a['name'];
                    }
                }

                $districtFormatted = '';
                if ($district && $sublocality && $sublocality !== $city) {
                    $dName = preg_replace('/^(Kecamatan|Kec\.)\s*/i', '', $district);
                    $sName = preg_replace('/^(Kelurahan|Kel\.|Desa)\s*/i', '', $sublocality);
                    $districtFormatted = "Kec. {$dName}, Kel. {$sName}";
                } elseif ($district) {
                    $dName = preg_replace('/^(Kecamatan|Kec\.)\s*/i', '', $district);
                    $districtFormatted = "Kec. {$dName}";
                } elseif ($sublocality) {
                    $sName = preg_replace('/^(Kelurahan|Kel\.|Desa)\s*/i', '', $sublocality);
                    $districtFormatted = "Kel. {$sName}";
                }

                $streetName = $districtFormatted ?: "Titik ({$lat}, {$lng})";
                $formatted = implode(', ', array_filter([$districtFormatted, $city, $province, $postcode]));

                return [
                    'success' => true,
                    'source' => 'bdc',
                    'street_name' => $streetName,
                    'formatted_address' => $formatted ?: "Koordinat {$lat}, {$lng}",
                    'district' => $districtFormatted,
                    'sublocality' => $sublocality ?? '',
                    'city' => $city ?? '',
                    'province' => $province ?? '',
                    'postal_code' => $postcode,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('BigDataCloud reverse exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Deteksi lokasi berdasarkan IP (fallback saat GPS perangkat tidak aktif / ditolak).
     */
    public function detectFromIp(?string $ip): array
    {
        // Koordinat default Kota Semarang (-6.989720, 110.421930) jika IP lokal / loopback
        $defaultLat = -6.989720;
        $defaultLng = 110.421930;

        if (empty($ip) || in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || str_starts_with($ip, '172.')) {
            $res = $this->reverse($defaultLat, $defaultLng);
            $res['is_ip_fallback'] = true;
            return $res;
        }

        try {
            $resp = Http::timeout(4)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,lat,lon,city,regionName,zip',
            ]);

            if ($resp->successful() && ($resp->json('status') === 'success')) {
                $lat = (float) $resp->json('lat');
                $lng = (float) $resp->json('lon');
                $res = $this->reverse($lat, $lng);
                $res['is_ip_fallback'] = true;
                return $res;
            }
        } catch (\Throwable $e) {
            Log::warning('IP Geolocation error: ' . $e->getMessage());
        }

        $res = $this->reverse($defaultLat, $defaultLng);
        $res['is_ip_fallback'] = true;
        return $res;
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
                        $lat = (float) ($loc['lat'] ?? 0);
                        $lng = (float) ($loc['lng'] ?? 0);
                        $parsed = $this->parseGoogleAddressComponents(
                            $res['address_components'] ?? [],
                            $res['formatted_address'] ?? '',
                            $lat,
                            $lng
                        );
                        $cleanAddr = $parsed['formatted_address'];

                        $results[] = [
                            'title' => explode(',', $cleanAddr)[0] ?? $cleanAddr,
                            'address' => $cleanAddr,
                            'street_name' => $parsed['street_name'],
                            'district' => $parsed['district'],
                            'sublocality' => $parsed['sublocality'],
                            'city' => $parsed['city'],
                            'province' => $parsed['province'],
                            'postal_code' => $parsed['postal_code'],
                            'latitude' => $lat,
                            'longitude' => $lng,
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
                    $lat = (float) ($item['lat'] ?? 0);
                    $lng = (float) ($item['lon'] ?? 0);
                    $parsed = $this->parseNominatimAddress(
                        $item['address'] ?? [],
                        $item['display_name'] ?? '',
                        $lat,
                        $lng
                    );

                    $cleanName = preg_replace('/, Indonesia$/i', '', $item['display_name'] ?? '');
                    $title = $item['name'] ?? (explode(',', $cleanName)[0] ?? $cleanName);

                    $results[] = [
                        'title' => $title,
                        'address' => $parsed['formatted_address'] ?: $cleanName,
                        'street_name' => $parsed['street_name'],
                        'district' => $parsed['district'],
                        'sublocality' => $parsed['sublocality'],
                        'city' => $parsed['city'],
                        'province' => $parsed['province'],
                        'postal_code' => $parsed['postal_code'],
                        'latitude' => $lat,
                        'longitude' => $lng,
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

