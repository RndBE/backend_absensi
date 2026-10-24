<?php

namespace App\Support;

use App\Models\Regency;
use Illuminate\Support\Collection;

/**
 * Memetakan kota tujuan yang diketik bebas ke kabupaten/kota, dan mencari kabupaten/kota untuk
 * isian otomatis. Menangani awalan dan salah ketik umum ("Kab. Kendal", "KebupatenKendal"),
 * provinsi di belakang ("Kendal, Jawa Tengah", "kendal jawa tengah"), nama populer ("Solo",
 * "Jogja"), dan memilih Kota bila nama polos dipakai dua wilayah ("Semarang" = Kota Semarang).
 */
class RegencyMatcher
{
    /** Nama populer yang bukan nama resmi kabupaten/kota. */
    private const ALIASES = [
        'solo'         => 3372, // Kota Surakarta
        'jogja'        => 3471, // Kota Yogyakarta
        'yogya'        => 3471,
        'jogjakarta'   => 3471,
        'djogja'       => 3471,
        // Nama lama kabupaten yang sudah berganti nama, dan singkatan populer.
        'toba samosir'           => 1206, // Toba
        'mahakam hulu'           => 6411, // Mahakam Ulu
        'siau tagulandang biaro' => 7108, // Kepulauan Siau Tagulandang Biaro
        'sitaro'                 => 7108,
        'mamuju utara'           => 7605, // Pasangkayu
        'maluku tenggara barat'  => 8101, // Kepulauan Tanimbar
    ];

    /** Singkatan dan nama panjang provinsi yang sering dipakai. */
    private const PROVINCE_ALIASES = [
        'daerah istimewa yogyakarta'    => 'di yogyakarta',
        'diy'                           => 'di yogyakarta',
        'yogyakarta'                    => 'di yogyakarta',
        'daerah khusus ibukota jakarta' => 'dki jakarta',
        'jakarta'                       => 'dki jakarta',
        'jateng'                        => 'jawa tengah',
        'jatim'                         => 'jawa timur',
        'jabar'                         => 'jawa barat',
        'ntb'                           => 'nusa tenggara barat',
        'ntt'                           => 'nusa tenggara timur',
        'sumut'                         => 'sumatera utara',
        'sumbar'                        => 'sumatera barat',
        'sumsel'                        => 'sumatera selatan',
        'kalbar'                        => 'kalimantan barat',
        'kalteng'                       => 'kalimantan tengah',
        'kalsel'                        => 'kalimantan selatan',
        'kaltim'                        => 'kalimantan timur',
        'kaltara'                       => 'kalimantan utara',
        'sulut'                         => 'sulawesi utara',
        'sulteng'                       => 'sulawesi tengah',
        'sulsel'                        => 'sulawesi selatan',
        'sultra'                        => 'sulawesi tenggara',
        'sulbar'                        => 'sulawesi barat',
        'babel'                         => 'kepulauan bangka belitung',
        'kepri'                         => 'kepulauan riau',
    ];

    private const KOTA_PREFIXES = ['kota', 'kotamadya', 'kotamadia', 'kodya', 'kot'];

    /**
     * Kabupaten/kota yang dimaksud teks ini, atau null bila tidak dikenali atau ambigu.
     */
    public static function match(string $text, ?Collection $regencies = null): ?Regency
    {
        $regencies ??= Regency::all();
        $readings = self::readings($text);

        foreach ($readings as [$type, $name, $province]) {
            $hit = self::pick(self::exact($regencies, $name), $type, $province);
            if ($hit) {
                return $hit;
            }
        }

        foreach ($readings as [$type, $name, $province]) {
            $hit = self::pick(self::fuzzy($regencies, $name), $type, $province);
            if ($hit) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * Kabupaten/kota dari rincian alamat hasil geocoding (Nominatim `address`): kota dulu bila
     * memang "Kota ...", lalu kabupaten (county), dengan provinsi dari `state`.
     */
    public static function fromAddress(array $address, ?Collection $regencies = null): ?Regency
    {
        $regencies ??= Regency::all();
        $state = (string) ($address['state'] ?? '');
        $city = trim((string) ($address['city'] ?? ''));
        $county = trim((string) ($address['county'] ?? ''));

        $candidates = [];
        if ($city !== '' && str_starts_with(self::key($city), 'kota ')) {
            $candidates[] = $city;
        }
        if ($county !== '') {
            $candidates[] = str_starts_with(self::key($county), 'kabupaten ') ? $county : "Kabupaten $county";
        }
        if ($city !== '' && ! str_starts_with(self::key($city), 'kota ')) {
            $candidates[] = "Kota $city";
        }

        foreach ($candidates as $candidate) {
            $hit = self::match("$candidate, $state", $regencies);
            if ($hit) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * Kabupaten/kota yang cocok dengan ketikan untuk isian otomatis, yang paling cocok dulu.
     *
     * @return Collection<int, Regency>
     */
    public static function search(string $query, int $limit = 8, ?Collection $regencies = null): Collection
    {
        $regencies ??= Regency::all();

        // Awalan berspasi (mis. "kab kend") lebih spesifik daripada teks utuh; awalan menempel dan
        // provinsi di belakang tanpa koma baru dicoba bila bacaan sebelumnya tidak menemukan apa pun.
        $readings = self::readings($query);
        $spacedPrefix = count($readings) > 1 && ! $readings[1][3] && str_contains($readings[0][1], ' ');
        $choices = $spacedPrefix ? [$readings[1], $readings[0], ...array_slice($readings, 2)] : $readings;

        foreach ($choices as [$type, $name, $province]) {
            $found = self::rank($regencies, $type, $name, $province)->take($limit)->values();
            if ($found->isNotEmpty()) {
                return $found;
            }
        }

        return collect();
    }

    /** @return Collection<int, Regency> */
    private static function rank(Collection $regencies, ?string $type, string $name, ?string $province): Collection
    {
        if (mb_strlen($name) < 2) {
            return collect();
        }

        $aliasId = self::ALIASES[$name] ?? null;

        return $regencies
            ->map(function (Regency $regency) use ($name, $aliasId) {
                $key = self::key($regency->name);
                $score = match (true) {
                    $regency->id === $aliasId => 0,
                    $key === $name => 0,
                    str_starts_with($key, $name) => 1,
                    str_contains(" $key", " $name") => 2,
                    str_contains($key, $name) => 3,
                    mb_strlen($name) >= 4 && levenshtein($name, mb_substr($key, 0, mb_strlen($name))) <= 1 => 4,
                    default => null,
                };

                return $score === null ? null : [$regency, $score];
            })
            ->filter()
            ->sortBy([
                fn ($a, $b) => $a[1] <=> $b[1],
                // Jenis yang diketik ("kab"/"kota") dan provinsi yang disebut didahulukan.
                fn ($a, $b) => ($type !== null && $b[0]->type === $type) <=> ($type !== null && $a[0]->type === $type),
                fn ($a, $b) => ($province !== null && self::sameProvince($b[0], $province)) <=> ($province !== null && self::sameProvince($a[0], $province)),
                fn ($a, $b) => strcmp($a[0]->name, $b[0]->name),
                // Nama sama ("Semarang"): kota dulu, seperti pembacaan nama polos.
                fn ($a, $b) => ($b[0]->type === 'kota') <=> ($a[0]->type === 'kota'),
            ])
            ->map(fn ($pair) => $pair[0]);
    }

    /** Huruf kecil, tanda baca jadi spasi, spasi dirapikan. */
    public static function key(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $text = preg_replace('/\bindonesia\b/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Cara membaca teks, dari yang paling harfiah: [jenis|null, nama, provinsi|null, provinsi
     * dipotong dari belakang?]. Teks utuh dicoba dulu supaya "Kota Jambi" atau "Kota Yogyakarta"
     * tidak terbaca sebagai provinsi Jambi atau DI Yogyakarta.
     *
     * @return list<array{0: ?string, 1: string, 2: ?string, 3: bool}>
     */
    private static function readings(string $text): array
    {
        $parts = explode(',', $text);
        $place = self::key(array_shift($parts));
        $province = self::province(implode(' ', $parts));

        $readings = array_map(fn ($r) => [...$r, $province, false], self::prefixReadings($place));

        // Provinsi di belakang tanpa koma: "kendal jawa tengah".
        if ($province === null) {
            foreach (self::provinceKeys() as $candidate) {
                $rest = trim(mb_substr($place, 0, -mb_strlen($candidate) - 1));
                if (str_ends_with($place, " $candidate") && $rest !== '') {
                    $stripped = self::province($candidate);
                    foreach (self::prefixReadings($rest) as $reading) {
                        $readings[] = [...$reading, $stripped, true];
                    }
                    break;
                }
            }
        }

        return $readings;
    }

    /**
     * Teks utuh, lalu dengan awalan kabupaten/kota dipisah (termasuk salah ketik dan yang menempel).
     *
     * @return list<array{0: ?string, 1: string}>
     */
    private static function prefixReadings(string $place): array
    {
        $readings = [[null, $place]];

        $tokens = explode(' ', $place, 2);
        if (count($tokens) === 2) {
            [$first, $rest] = $tokens;
            if (self::isKabupatenWord($first) || in_array($first, ['kab', 'kb'], true)) {
                $readings[] = ['kabupaten', $rest];
            } elseif (in_array($first, self::KOTA_PREFIXES, true)) {
                $readings[] = ['kota', $rest];
            }

            return $readings;
        }

        // Awalan menempel: "kebupatenkendal", "kabkendal", "kotasemarang".
        for ($length = 7; $length <= 11 && $length < mb_strlen($place); $length++) {
            if (self::isKabupatenWord(mb_substr($place, 0, $length))) {
                $readings[] = ['kabupaten', mb_substr($place, $length)];
            }
        }
        foreach (['kab', ...self::KOTA_PREFIXES] as $prefix) {
            if (str_starts_with($place, $prefix) && mb_strlen($place) > mb_strlen($prefix) + 2) {
                $readings[] = [$prefix === 'kab' ? 'kabupaten' : 'kota', mb_substr($place, mb_strlen($prefix))];
            }
        }

        return $readings;
    }

    private static function isKabupatenWord(string $word): bool
    {
        return mb_strlen($word) >= 7 && levenshtein($word, 'kabupaten') <= 2;
    }

    /** Provinsi yang disebut, dalam bentuk kunci (mis. "jawa tengah"), atau null. */
    private static function province(string $text): ?string
    {
        $key = self::key($text);
        if ($key === '') {
            return null;
        }
        $key = self::PROVINCE_ALIASES[$key] ?? $key;

        foreach (self::provinceKeys() as $candidate) {
            if ($candidate === $key || (mb_strlen($key) >= 6 && levenshtein($candidate, $key) <= 2)) {
                return self::PROVINCE_ALIASES[$candidate] ?? $candidate;
            }
        }

        return null;
    }

    /** Nama provinsi resmi dan singkatannya, yang terpanjang dulu supaya akhiran tidak salah potong. */
    private static function provinceKeys(): array
    {
        static $keys = null;

        if ($keys === null) {
            $official = array_map(fn (array $row) => self::key($row[3]), require database_path('data/regencies.php'));
            $keys = array_values(array_unique([...$official, ...array_keys(self::PROVINCE_ALIASES)]));
            usort($keys, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        }

        return $keys;
    }

    private static function sameProvince(Regency $regency, string $province): bool
    {
        return self::key($regency->province) === $province;
    }

    /** @return Collection<int, Regency> */
    private static function exact(Collection $regencies, string $name): Collection
    {
        if (isset(self::ALIASES[$name])) {
            return $regencies->where('id', self::ALIASES[$name])->values();
        }

        return $regencies->filter(fn (Regency $r) => self::key($r->name) === $name)->values();
    }

    /** Salah ketik ringan: makin panjang namanya, makin banyak huruf yang boleh meleset. */
    private static function fuzzy(Collection $regencies, string $name): Collection
    {
        $allowed = match (true) {
            mb_strlen($name) >= 8 => 2,
            mb_strlen($name) >= 5 => 1,
            default => 0,
        };
        if ($allowed === 0) {
            return collect();
        }

        $scored = $regencies
            ->map(fn (Regency $r) => [$r, levenshtein($name, self::key($r->name))])
            ->filter(fn ($pair) => $pair[1] <= $allowed);
        $best = $scored->min(fn ($pair) => $pair[1]);

        return $scored->filter(fn ($pair) => $pair[1] === $best)->map(fn ($pair) => $pair[0])->values();
    }

    /**
     * Satu kabupaten/kota dari kandidat: disaring jenis dan provinsi yang disebut (bila ada yang
     * cocok), lalu Kota diutamakan untuk nama polos yang dipakai dua wilayah. Null bila tetap ambigu.
     */
    private static function pick(Collection $candidates, ?string $type, ?string $province): ?Regency
    {
        if ($type !== null) {
            $sameType = $candidates->where('type', $type);
            $candidates = $sameType->isNotEmpty() ? $sameType : $candidates;
        }
        if ($province !== null) {
            $sameProvince = $candidates->filter(fn (Regency $r) => self::sameProvince($r, $province));
            $candidates = $sameProvince->isNotEmpty() ? $sameProvince : $candidates;
        }
        if ($candidates->count() > 1) {
            $cities = $candidates->where('type', 'kota');
            $candidates = $cities->count() === 1 ? $cities : $candidates;
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
