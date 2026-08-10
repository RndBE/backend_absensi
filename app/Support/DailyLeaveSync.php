<?php

namespace App\Support;

use App\Models\LeaveRequest;
use Illuminate\Support\Str;

/**
 * Pemetaan jenis izin HRIS -> `type` yang dikenal DailyCloseApp (`cuti` / `sakit`).
 *
 * Berbeda dari {@see LeaveDayCategory} yang memakai pencocokan longgar untuk keperluan
 * internal (rekap presensi, potongan payroll), di sini pemetaannya adalah DAFTAR PUTIH
 * dan gagal tertutup: nama yang tidak terdaftar TIDAK dikirim ke Daily.
 *
 * Alasannya asimetris. Salah kategori di internal paling-paling salah label di layar.
 * Salah kirim ke Daily berarti karyawan hilang dari daftar "belum lapor" — orang itu
 * berhenti ditagih laporan harian dan tidak ada yang akan melaporkannya, karena
 * kondisinya menguntungkan yang bersangkutan. Jadi kalau ragu, jangan kirim.
 *
 * Kontrak: docs/api-internal-sinkron-cuti.md di repo DailyCloseApp.
 */
class DailyLeaveSync
{
    public const TYPE_CUTI = 'cuti';
    public const TYPE_SAKIT = 'sakit';

    /**
     * Kunci = nama leave_types yang dinormalisasi (lowercase + trim).
     *
     * Dipetakan lewat NAMA, bukan leave_type_id: id di tabel tidak berurutan (id 2
     * sudah hilang) dan bisa berubah kalau tabelnya di-seed ulang.
     *
     * Yang sengaja TIDAK ada di sini dan tidak boleh ditambahkan:
     * - "Izin Datang Terlambat" dan "Izin Pulang Cepat" — izin parsial, orangnya masuk
     * - "Work From Home" — tetap bekerja, cuma beda tempat
     * Ketiganya tetap wajib mengisi laporan harian.
     */
    private const TYPE_MAP = [
        'sakit' => self::TYPE_SAKIT,
        'cuti tahunan' => self::TYPE_CUTI,
        'cuti melahirkan' => self::TYPE_CUTI,
    ];

    /**
     * `type` untuk Daily, atau null kalau jenis ini tidak boleh dikirim.
     */
    public static function typeFor(?LeaveRequest $leave): ?string
    {
        $name = self::normalize($leave?->leaveType?->name);

        if ($name === '') {
            return null;
        }

        return self::TYPE_MAP[$name] ?? null;
    }

    public static function shouldSync(?LeaveRequest $leave): bool
    {
        return self::typeFor($leave) !== null;
    }

    /**
     * Nama leave type yang belum dipetakan. Dipakai untuk memberi peringatan di log:
     * jenis baru yang ditambahkan HR akan diam-diam tidak tersinkron, dan itu harus
     * bisa ditemukan tanpa menunggu ada yang mengeluh.
     */
    public static function isUnmapped(?LeaveRequest $leave): bool
    {
        $name = self::normalize($leave?->leaveType?->name);

        return $name !== '' && ! isset(self::TYPE_MAP[$name]);
    }

    private static function normalize(?string $name): string
    {
        return Str::lower(trim((string) $name));
    }
}
