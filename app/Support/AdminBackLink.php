<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tombol "Kembali" di halaman detail yang bisa dibuka dari lebih dari satu tempat.
 *
 * Detail anggaran/LHP/LPJ dibuka dari menu masing-masing, dari halaman Persetujuan, dari
 * dashboard, dan dari surat tugas. Tanpa penanda asal, Kembali selalu ke daftar menu masing-
 * masing sehingga approver yang membuka detail dari Persetujuan terlempar ke halaman lain, dan
 * filter/halaman daftar yang sedang dibuka hilang.
 *
 * Tautan ke halaman detail membawa ?back=<alamat halaman asal lengkap dengan filternya>
 * (here()). Halaman detail memakainya untuk tombol Kembali (url()). Nilai itu hanya diterima
 * kalau berupa alamat relatif di bawah /admin: tidak ada host, skema, atau penelusuran folder,
 * sehingga tidak bisa dipakai sebagai open redirect. Selain itu jatuh ke daftar bawaan.
 */
class AdminBackLink
{
    private const MAX_LENGTH = 600;

    /** Awalan alamat (di bawah /admin) → nama tempat untuk tulisan "Kembali ke ...". */
    private const LABELS = [
        '/approvals' => 'Persetujuan',
        '/dashboard' => 'Dashboard',
        '/assignment-letters' => 'Surat Tugas',
        '/budget-requests' => 'Anggaran',
        '/travel-reports' => 'LHP',
        '/lpj' => 'LPJ',
    ];

    /** Parameter untuk tautan Detail: kembali ke halaman ini (path + filter + halaman). */
    public static function here(?Request $request = null): array
    {
        $request ??= request();

        return ['back' => $request->getRequestUri()];
    }

    /** Teruskan asal yang sama ke halaman saudara (mis. LHP lama ↔ LHP pengganti). */
    public static function forward(?Request $request = null): array
    {
        $request ??= request();
        $back = self::valid($request, $request->query('back'));

        return $back ? ['back' => $back] : [];
    }

    /**
     * Asal dari halaman sebelumnya (Referer) untuk redirect setelah aksi di halaman detail,
     * supaya Kembali tidak hilang setelah mis. menambah pembayaran.
     */
    public static function fromReferer(Request $request): array
    {
        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);
        $back = self::valid($request, $query['back'] ?? null);

        return $back ? ['back' => $back] : [];
    }

    /** Tujuan tombol Kembali: halaman asal (bila sah) atau daftar bawaan. */
    public static function url(Request $request, string $defaultRoute): string
    {
        return self::valid($request, $request->query('back')) ?? route($defaultRoute);
    }

    public static function label(Request $request): string
    {
        $back = self::valid($request, $request->query('back'));
        if (! $back) {
            return 'Kembali';
        }

        $path = substr((string) parse_url($back, PHP_URL_PATH), strlen($request->getBaseUrl().'/admin'));
        foreach (self::LABELS as $prefix => $label) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return 'Kembali ke '.$label;
            }
        }

        return 'Kembali';
    }

    private static function valid(Request $request, mixed $back): ?string
    {
        if (! is_string($back) || $back === '' || strlen($back) > self::MAX_LENGTH) {
            return null;
        }

        // Harus diawali satu "/" lalu /admin/ (bukan "//host"), tanpa spasi, kontrol, atau "\".
        if (! str_starts_with($back, $request->getBaseUrl().'/admin/') || preg_match('/[\x00-\x20\\\\]/', $back)) {
            return null;
        }

        $parts = parse_url($back);
        $path = $parts['path'] ?? '';
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_contains($path, '..') || str_contains($path, '//')) {
            return null;
        }

        return $back;
    }
}
