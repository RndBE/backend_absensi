<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Clock in / clock out lewat portal web karyawan.
 *
 * Portal dan aplikasi memakai controller yang sama (Api\AttendanceController), jadi aturan radius,
 * selfie, dan verifikasi wajahnya sama. Bedanya di perangkat: browser tidak pernah melaporkan
 * lokasi palsu (tidak ada padanan is_mock_location dari aplikasi), lokasinya bisa diganti lewat
 * DevTools, dan kamera laptop bisa diganti kamera virtual. Karena itu admin bisa menutup jalur ini
 * dan mengarahkan karyawan mengunduh aplikasi.
 *
 * Bawaannya tetap terbuka supaya deploy tidak mengubah perilaku; admin yang menutupnya.
 */
class WebClockin
{
    public const KEY_ENABLED = 'allow_web_clockin';

    public const KEY_ANDROID_URL = 'app_download_url_android';

    public const KEY_IOS_URL = 'app_download_url_ios';

    /** Berkas APK di Google Drive. Admin bisa menggantinya di Pengaturan Presensi. */
    public const DEFAULT_ANDROID_URL = 'https://drive.google.com/file/d/191_fnI_gC6mYvQu0H6MzHfdnww4foRXC/view?usp=sharing';

    /** Halaman HRIS Beacon di App Store. */
    public const DEFAULT_IOS_URL = 'https://apps.apple.com/id/app/hris-beacon/id6779173262';

    /** Penanda di request yang diteruskan portal ke Api\AttendanceController. */
    private const REQUEST_ATTRIBUTE = 'attendance_source';

    public static function enabled(): bool
    {
        return Setting::getValue(self::KEY_ENABLED, '1') === '1';
    }

    /**
     * Tautan unduh aplikasi. Isian kosong berarti tombolnya tidak ditampilkan.
     *
     * @return array{android: ?string, ios: ?string}
     */
    public static function downloadLinks(): array
    {
        return [
            'android' => self::httpUrl(Setting::getValue(self::KEY_ANDROID_URL, self::DEFAULT_ANDROID_URL)),
            'ios' => self::httpUrl(Setting::getValue(self::KEY_IOS_URL, self::DEFAULT_IOS_URL)),
        ];
    }

    public static function closedMessage(): string
    {
        return 'Clock in/out lewat portal web sudah ditutup. Silakan presensi lewat aplikasi HRIS Beacon di HP Anda.';
    }

    public static function markFromPortal(Request $request): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, Attendance::SOURCE_WEB);
    }

    /**
     * Kolom asal presensi untuk ditulis bersama jam clock in/out. Dilewati bila migrasinya belum
     * jalan, supaya kode yang ter-deploy lebih dulu tidak membuat semua presensi gagal.
     *
     * @param  'clock_in'|'clock_out'  $prefix
     */
    public static function sourceAttributes(Request $request, string $prefix): array
    {
        if (! Schema::hasColumn('attendances', $prefix.'_source')) {
            return [];
        }

        return [$prefix.'_source' => $request->attributes->get(self::REQUEST_ATTRIBUTE, Attendance::SOURCE_APP)];
    }

    /**
     * Pemakaian portal untuk presensi dalam N hari terakhir: berapa clock in/out dan oleh berapa
     * karyawan. Null bila kolom asal belum ada (migrasi belum jalan).
     *
     * @return array{days: int, attendances: int, employees: int}|null
     */
    public static function recentUsage(int $days = 30): ?array
    {
        if (! Schema::hasColumn('attendances', 'clock_in_source')) {
            return null;
        }

        $query = Attendance::query()
            ->where('date', '>=', Carbon::today()->subDays($days - 1)->toDateString())
            ->where(fn ($q) => $q->where('clock_in_source', Attendance::SOURCE_WEB)
                ->orWhere('clock_out_source', Attendance::SOURCE_WEB));

        return [
            'days' => $days,
            'attendances' => (clone $query)->count(),
            'employees' => (clone $query)->distinct()->count('employee_id'),
        ];
    }

    private static function httpUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return preg_match('#^https?://#i', $url) ? $url : null;
    }
}
