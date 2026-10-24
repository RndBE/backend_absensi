<?php

namespace App\Services;

use App\Models\Regency;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Jarak kantor → tujuan perjalanan: lokasi dicari lewat OpenStreetMap Nominatim (gratis, tanpa
 * API key), jarak jalan lewat TomTom, dengan garis lurus (Haversine) bila TomTom gagal.
 */
class TravelDistance
{
    /** Koordinat kantor dari pengaturan, atau null bila belum diisi. */
    public static function office(): ?array
    {
        $lat = (float) Setting::getValue('office_latitude', '0');
        $lng = (float) Setting::getValue('office_longitude', '0');

        return $lat === 0.0 && $lng === 0.0 ? null : ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Jarak jalan kantor → ibukota kabupaten/kota. Dihitung sekali lalu disimpan di baris
     * kabupaten/kotanya, tapi hanya bila jarak jalan berhasil, supaya fallback sementara (TomTom
     * down) tidak mengunci kabupaten/kota ke nilai garis lurus selamanya. Null bila lokasinya
     * tidak ketemu.
     *
     * @return array{distance_km: int, lat: ?float, lng: ?float}|null
     */
    public static function forRegency(Regency $regency, array $office): ?array
    {
        if ($regency->distance_km !== null) {
            return ['distance_km' => $regency->distance_km, 'lat' => $regency->lat, 'lng' => $regency->lng];
        }

        $coords = $regency->lat !== null && $regency->lng !== null
            ? ['lat' => $regency->lat, 'lng' => $regency->lng]
            : self::capitalPoint($regency);
        if (! $coords) {
            return null;
        }

        $distance = self::fromOffice($office, $coords['lat'], $coords['lng']);

        $regency->fill(['lat' => $coords['lat'], 'lng' => $coords['lng']]);
        if ($distance['road']) {
            $regency->fill(['distance_km' => $distance['km'], 'distance_source' => 'routing']);
        }
        $regency->save();

        return ['distance_km' => $distance['km'], 'lat' => $coords['lat'], 'lng' => $coords['lng']];
    }

    /**
     * Titik ibukota, bukan titik tengah wilayah: nama kabupaten saja di Nominatim menghasilkan
     * titik tengah batas wilayahnya (Kabupaten Semarang ±17 km dari Ungaran). Pencarian dibatasi
     * ke permukiman supaya yang didapat kota ibukotanya; bila tidak ketemu, pakai wilayahnya.
     */
    private static function capitalPoint(Regency $regency): ?array
    {
        return self::geocode("{$regency->capital}, {$regency->label()}, {$regency->province}", 'settlement')
            ?? self::geocode($regency->fullLabel());
    }

    /**
     * Jarak jalan dari kantor (TomTom), atau garis lurus bila TomTom gagal ('road' => false).
     *
     * @return array{km: int, road: bool}
     */
    public static function fromOffice(array $office, float $lat, float $lng): array
    {
        $roadKm = self::roadKm($office['lat'], $office['lng'], $lat, $lng);

        return [
            'km'   => (int) round($roadKm ?? self::haversineKm($office['lat'], $office['lng'], $lat, $lng)),
            'road' => $roadKm !== null,
        ];
    }

    /**
     * Lokasi teks bebas di Indonesia beserta rincian alamatnya (kabupaten/kota, provinsi).
     * 'area' = hasilnya seluruh wilayah kabupaten/kota (titik tengah batas wilayah), bukan tempat.
     *
     * @return array{key: ?string, name: string, display: string, lat: float, lng: float, address: array, area: bool}|null
     */
    public static function geocode(string $query, ?string $featureType = null): ?array
    {
        return self::search($query, 1, $featureType)[0] ?? null;
    }

    /**
     * Beberapa tempat yang cocok dengan teks, untuk dipilih pengguna (tombol "Cari tempat").
     * Null bila Nominatim gagal dihubungi, [] bila memang tidak ada yang cocok.
     *
     * - key: id OpenStreetMap ("N123" node, "W" way, "R" relation), kunci simpanan jarak per tempat
     * - name: nama tempat; display: alamat lengkap tanpa ", Indonesia"
     * - area: hasilnya seluruh wilayah kabupaten/kota (titik tengah batas wilayah), bukan tempat
     *
     * @return list<array{key: ?string, name: string, display: string, lat: float, lng: float, address: array, area: bool}>|null
     */
    public static function search(string $query, int $limit = 5, ?string $featureType = null): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'AbsensiBeacon/1.0 (support@bejogja.com)',
                'Accept-Language' => 'id,en',
            ])->withoutVerifying()->timeout(8)->get('https://nominatim.openstreetmap.org/search', array_filter([
                'q'              => "$query, Indonesia",
                'format'         => 'json',
                'limit'          => $limit,
                'addressdetails' => 1,
                'countrycodes'   => 'id',
                'featureType'    => $featureType,
            ]));

            if (! $response->ok()) {
                return null;
            }

            return array_map(function (array $result) {
                $display = preg_replace('/,\s*Indonesia$/u', '', (string) ($result['display_name'] ?? ''));
                $type = $result['osm_type'] ?? null;

                return [
                    'key'     => $type && isset($result['osm_id']) ? strtoupper($type[0]).$result['osm_id'] : null,
                    'name'    => trim((string) ($result['name'] ?? '')) ?: trim(explode(',', $display)[0]),
                    'display' => $display,
                    'lat'     => (float) $result['lat'],
                    'lng'     => (float) $result['lon'],
                    'address' => (array) ($result['address'] ?? []),
                    'area'    => $type === 'relation'
                        && in_array($result['addresstype'] ?? null, ['county', 'city', 'state', 'region'], true),
                ];
            }, array_values((array) $response->json()));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Saran tempat saat mengetik, lewat TomTom Search (Nominatim publik melarang pencarian
     * sambil mengetik). Hanya tempat: gedung, jalan, alamat, kelurahan, kecamatan. Hasil yang
     * berupa kabupaten/kota utuh atau lebih luas dibuang, karena sudah ada di saran kabupaten/kota.
     * Null bila key kosong atau TomTom gagal, [] bila tidak ada yang cocok.
     *
     * TomTom menulis kabupaten/kota tanpa awalan ("Semarang" untuk Kota dan Kabupaten Semarang),
     * jadi letaknya disimpan apa adanya di 'where' ("Semarang, Jawa Tengah"), tidak ditebak.
     *
     * @return list<array{key: string, name: string, display: string, where: string, lat: float, lng: float}>|null
     */
    public static function suggestPlaces(string $query, int $limit = 5): ?array
    {
        $key = self::tomtomKey();
        if ($key === '') {
            return null;
        }

        try {
            $response = Http::withoutVerifying()->timeout(6)
                ->get('https://api.tomtom.com/search/2/search/'.rawurlencode($query).'.json', [
                    'key'        => $key,
                    'typeahead'  => 'true',
                    'limit'      => $limit + 3, // cadangan untuk hasil wilayah yang dibuang
                    'countrySet' => 'ID',
                    'language'   => 'id-ID',
                ]);

            if (! $response->ok()) {
                return null;
            }

            $wide = ['Country', 'CountrySubdivision', 'CountrySecondarySubdivision', 'Municipality'];
            $places = [];
            foreach ((array) $response->json('results') as $result) {
                if (($result['type'] ?? null) === 'Geography' && in_array($result['entityType'] ?? null, $wide, true)) {
                    continue;
                }
                $address = (array) ($result['address'] ?? []);
                $display = (string) ($address['freeformAddress'] ?? '');
                $name = trim((string) ($result['poi']['name']
                    ?? $address['municipalitySecondarySubdivision']
                    ?? $address['municipalitySubdivision']
                    ?? $address['streetName']
                    ?? explode(',', $display)[0]));
                if ($name === '' || ! isset($result['position']['lat'], $result['position']['lon'])) {
                    continue;
                }

                $places[] = [
                    'key'     => 'T'.($result['id'] ?? md5($name.$display)),
                    'name'    => $name,
                    'display' => $display,
                    'where'   => implode(', ', array_filter([
                        $address['municipality'] ?? null,
                        $address['countrySubdivisionName'] ?? $address['countrySubdivision'] ?? null,
                    ])),
                    'lat'     => (float) $result['position']['lat'],
                    'lng'     => (float) $result['position']['lon'],
                ];
                if (count($places) === $limit) {
                    break;
                }
            }

            return $places;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Key TomTom dari konfigurasi sistem (.env); fallback ke Setting lama bila ada. */
    private static function tomtomKey(): string
    {
        $key = trim((string) config('services.tomtom.key'));

        return $key !== '' ? $key : trim((string) Setting::getValue('tomtom_api_key', ''));
    }

    /**
     * Jarak jalan (km) via TomTom Routing API — rute tercepat (mempertimbangkan
     * tol & lalu lintas), mirip Google. Mengembalikan null bila key kosong atau
     * request gagal, agar pemanggil bisa fallback ke Haversine.
     */
    public static function roadKm(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        $key = self::tomtomKey();
        if ($key === '') {
            return null;
        }

        try {
            // TomTom memakai urutan lat,lng dan format "asal:tujuan" di path.
            $locations = "$lat1,$lng1:$lat2,$lng2";
            $response = Http::withoutVerifying()->timeout(8)
                ->get("https://api.tomtom.com/routing/1/calculateRoute/{$locations}/json", [
                    'key'        => $key,
                    'routeType'  => 'fastest',
                    'travelMode' => 'car',
                ]);

            if (! $response->ok()) {
                return null;
            }

            $meters = $response->json('routes.0.summary.lengthInMeters');

            return $meters !== null ? $meters / 1000 : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Haversine formula — jarak garis lurus dalam km.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
