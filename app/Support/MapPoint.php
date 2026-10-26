<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Titik dari teks yang ditempel di kota tujuan: koordinat ("-6.9903, 110.4231") atau link
 * Google Maps, termasuk link pendek dari tombol Bagikan (maps.app.goo.gl) yang diikuti
 * pengalihannya, hanya ke domain Google.
 */
class MapPoint
{
    private const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl', 'g.co'];

    /** Titik dari koordinat atau link, atau null bila tidak dikenali. */
    public static function fromText(string $text): ?array
    {
        $text = trim($text);
        if ($point = self::parse($text)) {
            return $point;
        }

        $url = self::firstUrl($text);
        if ($url && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::SHORT_HOSTS, true)) {
            $expanded = self::expand($url);

            return $expanded ? self::parse($expanded) : null;
        }

        return null;
    }

    /**
     * Titik yang tertulis langsung di teks. Titik pin tempat (!3d..!4d..) didahulukan dari
     * pusat tampilan peta (@lat,lng), karena pusat tampilan bisa bergeser dari tempatnya.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function parse(string $text): ?array
    {
        $text = urldecode($text);
        $patterns = [
            '/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',
            '/[?&](?:q|query|ll|destination|daddr|center)=(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/',
            '/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/',
            '/^\s*(-?\d{1,2}\.\d+)\s*[,;\s]\s*(-?\d{1,3}\.\d+)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if (abs($lat) <= 90 && abs($lng) <= 180) {
                    return ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        return null;
    }

    private static function firstUrl(string $text): ?string
    {
        return preg_match('~https?://\S+~i', $text, $m) ? $m[0] : null;
    }

    private static function isGoogle(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, self::SHORT_HOSTS, true) || (bool) preg_match('/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host);
    }

    /** Mengikuti pengalihan link pendek (maks 5 kali) tanpa keluar dari domain Google. */
    private static function expand(string $url): ?string
    {
        for ($hop = 0; $hop < 5; $hop++) {
            try {
                $response = Http::withoutRedirecting()->timeout(6)
                    ->withHeaders(['User-Agent' => 'AbsensiBeacon/1.0 (support@bejogja.com)'])
                    ->get($url);
            } catch (\Throwable) {
                return null;
            }

            $location = $response->header('Location');
            if ($location === '') {
                return $url;
            }
            if (! str_starts_with($location, 'http')) {
                $location = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).'/'.ltrim($location, '/');
            }
            if (! self::isGoogle($location)) {
                return null;
            }
            if (self::parse($location)) {
                return $location;
            }
            $url = $location;
        }

        return $url;
    }
}
