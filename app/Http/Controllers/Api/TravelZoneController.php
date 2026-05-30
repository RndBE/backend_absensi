<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TravelZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TravelZoneController extends Controller
{
    /**
     * Estimasi zona perjalanan berdasarkan nama kota tujuan.
     * Geocoding via OpenStreetMap Nominatim (gratis, tanpa API key).
     */
    public function estimateZone(Request $request)
    {
        $request->validate(['city' => 'required|string|max:255']);

        $city = trim($request->city);

        // Geocode kota via Nominatim
        $coords = $this->geocodeCity($city);
        if (! $coords) {
            return response()->json([
                'success' => false,
                'message' => 'Kota tidak ditemukan. Coba gunakan nama kota yang lebih spesifik.',
            ], 422);
        }

        // Koordinat kantor dari Setting
        $officeLat = (float) Setting::getValue('office_latitude', '0');
        $officeLng = (float) Setting::getValue('office_longitude', '0');

        if ($officeLat === 0.0 && $officeLng === 0.0) {
            return response()->json([
                'success' => false,
                'message' => 'Koordinat kantor belum dikonfigurasi.',
            ], 422);
        }

        // Hitung jarak garis lurus (km)
        $distanceKm = (int) round(
            $this->haversineKm($officeLat, $officeLng, $coords['lat'], $coords['lng'])
        );

        // Deteksi zona
        $zone = TravelZone::findByKm($distanceKm);

        return response()->json([
            'success' => true,
            'data'    => [
                'city'        => $city,
                'distance_km' => $distanceKm,
                'lat'         => $coords['lat'],
                'lng'         => $coords['lng'],
                'zone'        => $zone ? [
                    'id'             => $zone->id,
                    'zone'           => $zone->zone,
                    'name'           => $zone->name,
                    'km_range'       => $zone->km_range_label,
                    'meal_allowance' => (float) $zone->meal_allowance,
                ] : null,
            ],
        ]);
    }

    /**
     * Geocode nama kota menggunakan OpenStreetMap Nominatim.
     */
    private function geocodeCity(string $city): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'AbsensiBeacon/1.0 (support@bejogja.com)',
                'Accept-Language' => 'id,en',
            ])->withoutVerifying()->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
                'q'      => "$city, Indonesia",
                'format' => 'json',
                'limit'  => 1,
            ]);

            if (! $response->ok()) return null;

            $results = $response->json();
            if (empty($results)) return null;

            return [
                'lat' => (float) $results[0]['lat'],
                'lng' => (float) $results[0]['lon'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Haversine formula — jarak garis lurus dalam km.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
