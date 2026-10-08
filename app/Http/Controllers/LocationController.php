<?php

namespace App\Http\Controllers;

use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Reverse geocoding koordinat GPS menjadi nama jalan dan alamat lengkap.
     */
    public function reverse(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $geocoding->reverse((float) $validated['lat'], (float) $validated['lng']);

        return response()->json($result);
    }

    /**
     * Pencarian alamat / nama jalan / lokasi.
     */
    public function search(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $results = $geocoding->search($validated['q']);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }
}

