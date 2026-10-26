<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CityDistance;
use App\Models\Regency;
use App\Models\TravelZone;
use App\Services\TravelDistance;
use App\Support\RegencyMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TravelZoneController extends Controller
{
    private bool $regenciesReady = false;

    /**
     * Estimasi zona perjalanan dari kota tujuan. Jarak selalu jarak jalan TomTom dari kantor, ke:
     * - ibukota kabupaten/kota, bila yang dipilih/diketik nama kabupaten/kota ("Kendal",
     *   "Kab. Kendal", "Kendal, Jawa Tengah" sama semua), dihitung sekali per kabupaten/kota;
     * - titik tempat itu sendiri, bila yang diketik nama tempat atau alamat ("Ungaran", "Balai
     *   Besar ..."), dihitung sekali per teks;
     * - titik yang dipilih di peta (lat/lng), dihitung sekali per titik (dibulatkan ±10 m).
     *
     * distance_source di jawaban (regency|place|map|text) disimpan di pengajuan anggaran.
     */
    public function estimateZone(Request $request)
    {
        $request->validate([
            'city'        => 'nullable|required_without_all:regency_id,place_token,lat|string|max:255',
            'regency_id'  => 'nullable|integer',
            'place_token' => 'nullable|string',
            // Titik peta di Indonesia.
            'lat'         => 'nullable|numeric|between:-11.5,6.5|required_with:lng',
            'lng'         => 'nullable|numeric|between:94,141.5|required_with:lat',
            'point_label' => 'nullable|string|max:200',
        ]);

        $city = trim((string) $request->city);
        $cityKey = CityDistance::normalizeKey($city);
        // Server yang belum menjalankan migrasi kabupaten/kota tetap bekerja seperti sebelumnya.
        $this->regenciesReady = Schema::hasTable('regencies') && Schema::hasColumn('city_distances', 'regency_id');

        // 0a. Titik yang dipilih sendiri di peta (atau dari link/koordinat yang ditempel).
        if ($request->filled('lat')) {
            return $this->mapPointResponse((float) $request->lat, (float) $request->lng, trim((string) $request->point_label));
        }

        // 0b. Tempat yang dipilih dari saran atau tombol "Cari tempat": jarak ke titik tempat itu.
        if ($request->filled('place_token')) {
            return $this->chosenPlaceResponse((string) $request->place_token);
        }

        // 1. Kabupaten/kota dari daftar, atau dikenali dari teksnya (awalan, salah ketik, provinsi).
        if ($request->filled('regency_id')) {
            $regency = $this->regenciesReady ? Regency::find($request->regency_id) : null;
            if (! $regency) {
                return $this->fail('Kabupaten/kota tidak ditemukan. Pilih dari daftar.');
            }

            return $this->regencyResponse($regency);
        }

        $regency = $this->regenciesReady ? RegencyMatcher::match($city) : null;
        if ($regency) {
            return $this->regencyResponse($regency);
        }

        // 2. Teks yang pernah dicari (nama tempat, alamat) atau dikoreksi admin: tanpa API.
        $cached = CityDistance::where('city_key', $cityKey)->first();
        if ($cached && $cached->source === 'alias' && $cached->regency_id) {
            $regency = Regency::find($cached->regency_id);
            if ($regency) {
                return $this->regencyResponse($regency);
            }
        } elseif ($cached && $cached->distance_km !== null) {
            $regency = $cached->regency_id ? Regency::find($cached->regency_id) : null;

            return $this->zoneResponse($cached->city_label, $cached->distance_km, $cached->lat, $cached->lng, $regency, $this->placeTarget($cached->city_label, $regency), 'text');
        }

        $office = TravelDistance::office();
        if (! $office) {
            return $this->fail('Koordinat kantor belum dikonfigurasi.');
        }

        // 3. Teks baru: cari lokasinya. Bila hasilnya seluruh wilayah kabupaten/kota (bukan tempat),
        //    jarak ke ibukotanya; selain itu jarak ke titik tempat itu sendiri.
        $place = TravelDistance::geocode($city);
        if (! $place) {
            return $this->fail('Kota tidak ditemukan. Pilih kabupaten/kota dari daftar.');
        }

        $regency = $this->regenciesReady ? RegencyMatcher::fromAddress($place['address']) : null;

        if ($regency && $place['area']) {
            CityDistance::updateOrCreate(['city_key' => $cityKey], [
                'city_label'  => $city,
                'regency_id'  => $regency->id,
                'distance_km' => null,
                'lat'         => null,
                'lng'         => null,
                'source'      => 'alias',
            ]);

            return $this->regencyResponse($regency, $office);
        }

        return $this->placeResponse($city, $cityKey, $place, $office, $regency, 'text');
    }

    /**
     * Titik yang dipilih sendiri di peta. Tidak bisa diverifikasi server, jadi pengajuan
     * menyimpannya sebagai 'map' dan approver melihatnya di peta.
     */
    private function mapPointResponse(float $lat, float $lng, string $label)
    {
        $lat = round($lat, 6);
        $lng = round($lng, 6);
        $name = $label !== '' ? $label : sprintf('%.5f, %.5f', $lat, $lng);
        $target = "titik peta: $name";
        // Titik yang hampir sama (±10 m) memakai jarak yang sama, supaya kuota TomTom hemat.
        $cityKey = sprintf('point:%.4f,%.4f', $lat, $lng);

        $cached = CityDistance::where('city_key', $cityKey)->first();
        if ($cached && $cached->distance_km !== null) {
            return $this->zoneResponse($name, $cached->distance_km, $lat, $lng, null, $target, 'map');
        }

        $office = TravelDistance::office();
        if (! $office) {
            return $this->fail('Koordinat kantor belum dikonfigurasi.');
        }

        return $this->placeResponse($name, $cityKey, ['lat' => $lat, 'lng' => $lng], $office, null, 'map', $target);
    }

    /**
     * Tempat pilihan pengguna dari hasil "Cari tempat". Titiknya dari token yang ditandatangani
     * server, dan jaraknya disimpan per tempat (id OpenStreetMap), bukan per teks.
     */
    private function chosenPlaceResponse(string $token)
    {
        $place = PlaceController::readToken($token);
        if (! $place) {
            return $this->fail('Pilihan tempat tidak dikenali. Cari ulang tempatnya.');
        }

        $cityKey = CityDistance::normalizeKey('place:'.$place['key']);
        $regency = $this->regenciesReady && $place['regency_id'] ? Regency::find($place['regency_id']) : null;

        // Saran TomTom tidak menebak kabupaten/kota; letaknya ditulis seperti dari TomTom.
        $target = $regency || empty($place['where'])
            ? $this->placeTarget($place['name'], $regency)
            : "{$place['name']} ({$place['where']})";

        $cached = CityDistance::where('city_key', $cityKey)->first();
        if ($cached && $cached->distance_km !== null) {
            return $this->zoneResponse($place['name'], $cached->distance_km, $cached->lat, $cached->lng, $regency, $target, 'place');
        }

        $office = TravelDistance::office();
        if (! $office) {
            return $this->fail('Koordinat kantor belum dikonfigurasi.');
        }

        return $this->placeResponse($place['name'], $cityKey, ['lat' => (float) $place['lat'], 'lng' => (float) $place['lng']], $office, $regency, 'place', $target);
    }

    /** Jarak ke ibukota kabupaten/kota, disimpan di kabupaten/kotanya setelah dihitung pertama kali. */
    private function regencyResponse(Regency $regency, ?array $office = null)
    {
        if ($regency->distance_km === null) {
            $office ??= TravelDistance::office();
            if (! $office) {
                return $this->fail('Koordinat kantor belum dikonfigurasi.');
            }
        }

        $distance = TravelDistance::forRegency($regency, $office ?? []);
        if (! $distance) {
            return $this->fail('Lokasi kabupaten/kota belum bisa dihitung. Coba lagi nanti.');
        }

        return $this->zoneResponse($regency->fullLabel(), $distance['distance_km'], $distance['lat'], $distance['lng'], $regency, $regency->distanceTarget(), 'regency');
    }

    /**
     * Nama tempat atau alamat: jarak ke titik tempat itu sendiri, disimpan per teks bila jarak
     * jalan berhasil. Kabupaten/kota tempatnya ikut disimpan sebagai keterangan.
     */
    private function placeResponse(string $city, string $cityKey, array $place, array $office, ?Regency $regency, string $source, ?string $target = null)
    {
        $distance = TravelDistance::fromOffice($office, $place['lat'], $place['lng']);

        if ($distance['road']) {
            CityDistance::updateOrCreate(['city_key' => $cityKey], [
                'city_label'  => $city,
                ...($this->regenciesReady ? ['regency_id' => $regency?->id] : []),
                'distance_km' => $distance['km'],
                'lat'         => $place['lat'],
                'lng'         => $place['lng'],
                'source'      => 'routing',
            ]);
        }

        return $this->zoneResponse($city, $distance['km'], $place['lat'], $place['lng'], $regency, $target ?? $this->placeTarget($city, $regency), $source);
    }

    /** "Ungaran (Kabupaten Semarang)", atau teksnya saja bila kabupaten/kotanya tidak dikenali. */
    private function placeTarget(string $city, ?Regency $regency): string
    {
        return $regency ? "$city ({$regency->label()})" : $city;
    }

    private function fail(string $message)
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }

    /**
     * Bentuk respons jarak + zona yang cocok, beserta kabupaten/kota dan titik yang dipakai
     * menghitung jarak.
     */
    private function zoneResponse(string $city, int $distanceKm, ?float $lat, ?float $lng, ?Regency $regency, string $distanceTo, string $source)
    {
        $zone = TravelZone::findByKm($distanceKm);

        return response()->json([
            'success' => true,
            'data'    => [
                'city'            => $city,
                'distance_km'     => $distanceKm,
                'distance_to'     => $distanceTo,
                'distance_source' => $source,
                'lat'         => $lat !== null ? (float) $lat : null,
                'lng'         => $lng !== null ? (float) $lng : null,
                'regency'     => $regency?->toSummary(),
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
}
