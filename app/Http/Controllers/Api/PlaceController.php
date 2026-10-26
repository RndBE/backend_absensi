<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Regency;
use App\Services\TravelDistance;
use App\Support\MapPoint;
use App\Support\RegencyMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat spesifik untuk kota tujuan, dipilih pengguna sebelum jarak jalan dihitung:
 * - suggest(): saran yang langsung muncul saat mengetik, dari TomTom Search;
 * - index(): tombol "Cari tempat", dari Nominatim, sekali per tekan tombol (server Nominatim
 *   publik melarang pencarian sambil mengetik). Jadi cadangan bila TomTom tidak menemukan;
 * - reverse() dan point(): pilih titik di peta, dan titik dari link/koordinat yang ditempel.
 */
class PlaceController extends Controller
{
    public const LIMIT = 5;

    public function index(Request $request)
    {
        $request->validate(['q' => 'required|string|min:3|max:150']);

        $query = trim($request->q);
        // Aturan pakai Nominatim meminta hasil pencarian disimpan, bukan dicari ulang.
        $cacheKey = 'places:'.md5(mb_strtolower($query));
        $places = Cache::get($cacheKey);
        if ($places === null) {
            $places = TravelDistance::search($query, self::LIMIT);
            if ($places === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pencarian tempat sedang tidak bisa dipakai. Coba lagi sebentar.',
                ], 503);
            }
            Cache::put($cacheKey, $places, now()->addDay());
        }

        $regencies = Schema::hasTable('regencies') ? Regency::all() : null;

        $data = collect($places)
            ->unique('display')
            ->map(function (array $place) use ($regencies) {
                $regency = $regencies ? RegencyMatcher::fromAddress($place['address'], $regencies) : null;

                return [
                    'name'        => $place['name'],
                    'address'     => $place['display'],
                    'regency'     => $regency?->toSummary(),
                    // Seluruh wilayah kabupaten/kota: jarak ke ibukotanya, sama dengan memilih dari daftar.
                    'regency_id'  => $place['area'] && $regency ? $regency->id : null,
                    // Titik tempat ditandatangani server, supaya jarak dihitung ke tempat yang benar-benar
                    // ditemukan, bukan koordinat kiriman app.
                    'place_token' => self::token($place, $regency),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Saran tempat yang langsung muncul saat mengetik, dari TomTom Search. Dibatasi per pengguna
     * di route, dan hasil ketikan yang sama disimpan sehari supaya kuota TomTom hemat. Bila TomTom
     * tidak bisa dipakai (key kosong, gagal), daftarnya kosong: saran kabupaten/kota dan tombol
     * "Cari tempat" tetap bisa dipakai.
     */
    public function suggest(Request $request)
    {
        $request->validate(['q' => 'required|string|min:3|max:150']);

        $query = trim($request->q);
        $cacheKey = 'places:suggest:'.md5(mb_strtolower($query));
        $places = Cache::get($cacheKey);
        if ($places === null) {
            $places = TravelDistance::suggestPlaces($query, self::LIMIT);
            if ($places === null) {
                return response()->json(['success' => true, 'data' => []]);
            }
            Cache::put($cacheKey, $places, now()->addDay());
        }

        $data = collect($places)
            ->unique(fn (array $place) => $place['name'].'|'.$place['display'])
            ->map(fn (array $place) => [
                'name'        => $place['name'],
                'address'     => $place['display'],
                'where'       => $place['where'],
                'regency'     => null,
                'regency_id'  => null,
                'place_token' => self::token($place, null),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Alamat perkiraan titik pin di peta (TomTom), disimpan 30 hari per titik (±10 m).
     * Tanpa alamat (TomTom gagal) app menampilkan koordinatnya saja.
     */
    public function reverse(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-11.5,6.5',
            'lng' => 'required|numeric|between:94,141.5',
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $cacheKey = sprintf('places:reverse:%.4f,%.4f', $lat, $lng);
        $address = Cache::get($cacheKey);
        if ($address === null) {
            $address = TravelDistance::reverse($lat, $lng);
            if ($address !== null) {
                Cache::put($cacheKey, $address, now()->addDays(30));
            }
        }

        return response()->json(['success' => true, 'data' => $address]);
    }

    /**
     * Titik dari link Google Maps atau koordinat yang ditempel di kota tujuan.
     */
    public function point(Request $request)
    {
        $request->validate(['text' => 'required|string|max:2000']);

        $point = MapPoint::fromText((string) $request->text);
        if (! $point) {
            return response()->json(['success' => false, 'message' => 'Link atau koordinat tidak dikenali.'], 422);
        }
        if ($point['lat'] < -11.5 || $point['lat'] > 6.5 || $point['lng'] < 94 || $point['lng'] > 141.5) {
            return response()->json(['success' => false, 'message' => 'Titik itu di luar Indonesia.'], 422);
        }

        return response()->json(['success' => true, 'data' => $point]);
    }

    private static function token(array $place, ?Regency $regency): string
    {
        return Crypt::encryptString(json_encode([
            'key'        => $place['key'] ?? md5($place['display']),
            'name'       => $place['name'],
            'where'      => $place['where'] ?? null,
            'lat'        => $place['lat'],
            'lng'        => $place['lng'],
            'regency_id' => $regency?->id,
        ]));
    }

    /**
     * Isi token tempat, atau null bila token rusak/dipalsukan.
     *
     * @return array{key: string, name: string, where: ?string, lat: float, lng: float, regency_id: ?int}|null
     */
    public static function readToken(string $token): ?array
    {
        try {
            $place = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($place) && isset($place['key'], $place['name'], $place['lat'], $place['lng']) ? $place : null;
    }
}
