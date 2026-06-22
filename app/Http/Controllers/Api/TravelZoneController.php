<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CityDistance;
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
        $cityKey = CityDistance::normalizeKey($city);

        // 1. Sudah pernah dihitung / dikoreksi admin → pakai langsung (instan, tanpa API)
        $cached = CityDistance::where('city_key', $cityKey)->first();
        if ($cached) {
            return $this->zoneResponse($cached->city_label, $cached->distance_km, $cached->lat, $cached->lng);
        }

        // 2. Geocode kota via Nominatim
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

        // 3. Jarak jalan via TomTom; fallback ke garis lurus (Haversine) bila gagal
        $roadKm = $this->roadDistanceKm($officeLat, $officeLng, $coords['lat'], $coords['lng']);
        $distanceKm = (int) round(
            $roadKm ?? $this->haversineKm($officeLat, $officeLng, $coords['lat'], $coords['lng'])
        );

        // 4. Simpan permanen HANYA jika berhasil jarak jalan, agar fallback sementara
        //    (TomTom down) tidak mengunci kota ke nilai garis lurus selamanya.
        if ($roadKm !== null) {
            CityDistance::create([
                'city_key'    => $cityKey,
                'city_label'  => $city,
                'distance_km' => $distanceKm,
                'lat'         => $coords['lat'],
                'lng'         => $coords['lng'],
                'source'      => 'routing',
            ]);
        }

        return $this->zoneResponse($city, $distanceKm, $coords['lat'], $coords['lng']);
    }

    /**
     * Bentuk respons jarak + zona yang cocok.
     */
    private function zoneResponse(string $city, int $distanceKm, ?float $lat, ?float $lng)
    {
        $zone = TravelZone::findByKm($distanceKm);

        return response()->json([
            'success' => true,
            'data'    => [
                'city'        => $city,
                'distance_km' => $distanceKm,
                'lat'         => $lat !== null ? (float) $lat : null,
                'lng'         => $lng !== null ? (float) $lng : null,
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
     * Jarak jalan (km) via TomTom Routing API — rute tercepat (mempertimbangkan
     * tol & lalu lintas), mirip Google. Mengembalikan null bila key kosong atau
     * request gagal, agar pemanggil bisa fallback ke Haversine.
     */
    private function roadDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        // Key dari konfigurasi sistem (.env); fallback ke Setting lama bila ada.
        $key = trim((string) config('services.tomtom.key'));
        if ($key === '') {
            $key = trim((string) Setting::getValue('tomtom_api_key', ''));
        }
        if ($key === '') return null;

        try {
            // TomTom memakai urutan lat,lng dan format "asal:tujuan" di path.
            $locations = "$lat1,$lng1:$lat2,$lng2";
            $response = Http::withoutVerifying()->timeout(8)
                ->get("https://api.tomtom.com/routing/1/calculateRoute/{$locations}/json", [
                    'key'        => $key,
                    'routeType'  => 'fastest',
                    'travelMode' => 'car',
                ]);

            if (! $response->ok()) return null;

            $meters = $response->json('routes.0.summary.lengthInMeters');

            return $meters !== null ? $meters / 1000 : null;
        } catch (\Throwable) {
            return null;
        }
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
