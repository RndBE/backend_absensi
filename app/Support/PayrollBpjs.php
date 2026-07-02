<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeePayroll;
use Carbon\Carbon;

class PayrollBpjs
{
    /** Tanggal batas registrasi BPJS. Join setelah tanggal ini → BPJS mulai bulan depan. */
    public const REGISTRATION_CUTOFF_DAY = 20;

    /** Apakah karyawan keluar (resign / hari kerja terakhir) di bulan periode ini. */
    public static function isResignedInMonth(?Employee $employee, Carbon $periodStart): bool
    {
        $exitRaw = $employee?->last_working_date ?: $employee?->resign_date;

        return $exitRaw && Carbon::parse($exitRaw)->isSameMonth($periodStart);
    }

    /** Karyawan baru yang join SETELAH tanggal cutoff di bulan ini (BPJS belum jalan bulan ini). */
    public static function isJoinedAfterCutoff(?Employee $employee, Carbon $periodStart): bool
    {
        $joinDate = $employee?->join_date ? Carbon::parse($employee->join_date) : null;

        return $joinDate
            && $joinDate->isSameMonth($periodStart)
            && $joinDate->day > self::REGISTRATION_CUTOFF_DAY;
    }

    /**
     * Terapkan SEMUA aturan kelayakan BPJS ke array hasil BpjsCalculator, agar tampilan
     * benefit di slip sama persis dengan komponen payroll. Dipakai bersama oleh
     * perhitungan payroll (generateDetails) dan tampilan benefit (buildBpjsData).
     *
     * Urutan: join setelah cutoff → semua 0; resign → JHT/JKK/JKM 0; tanpa nomor
     * registrasi → program terkait 0.
     */
    public static function applyEligibility(array $bpjs, EmployeePayroll $payroll, Carbon $periodStart): array
    {
        $employee = $payroll->employee;

        // Karyawan baru join setelah cutoff → semua BPJS 0 (mulai dihitung bulan depan).
        if (self::isJoinedAfterCutoff($employee, $periodStart)) {
            return self::refreshTotals(self::zero($bpjs, ['kesehatan', 'jht', 'jkk', 'jkm', 'jp']));
        }

        // Resign di bulan ini → JHT/JKK/JKM 0 (Kesehatan & JP tetap).
        $bpjs = self::dropKetenagakerjaanForResign($bpjs, $employee, $periodStart);

        // Tanpa nomor registrasi (termasuk placeholder "-") → program terkait 0.
        if (! self::hasRegistrationNumber($payroll->bpjs_kesehatan)) {
            $bpjs = self::zero($bpjs, ['kesehatan']);
        }
        if (! self::hasRegistrationNumber($payroll->bpjs_ketenagakerjaan)) {
            $bpjs = self::zero($bpjs, ['jht', 'jkk', 'jkm', 'jp']);
        }

        return self::refreshTotals($bpjs);
    }

    /**
     * Rakit baris benefit BPJS (ditanggung perusahaan) dari hasil applyEligibility.
     * Baris rate/basis hanya muncul bila iuran terkait aktif bulan ini; khusus resign,
     * JHT/JKK/JKM tetap ditampilkan sebagai Rp 0. Dipakai bersama oleh semua buildBpjsData
     * (Admin/Employee/Api/Job) agar tampilan benefit konsisten.
     */
    public static function benefitItems(array $bpjs, bool $resigned = false): array
    {
        $company = fn (string $k) => (float) ($bpjs[$k]['company'] ?? 0);
        $items = [];

        // Rate/basis Kesehatan hanya tampil bila iuran Kesehatan memang aktif.
        if ($company('kesehatan') > 0) {
            $items[] = ['label' => 'Rate BPJS Kesehatan', 'amount' => $bpjs['kesehatan']['basis'], 'is_basis' => true];
        }

        // Rate/basis Ketenagakerjaan tampil bila ada iuran, atau saat resign (baris Rp 0).
        $tkHasContrib = ($company('jht') + $company('jkk') + $company('jkm') + $company('jp') > 0) || $resigned;
        if ($tkHasContrib) {
            $items[] = ['label' => 'Rate BPJS Ketenagakerjaan', 'amount' => $bpjs['jht']['basis'], 'is_basis' => true];
        }

        if ($company('jkk') > 0 || $resigned) {
            $items[] = ['label' => 'JKK (Jaminan Kecelakaan Kerja)', 'amount' => $bpjs['jkk']['company'], 'is_basis' => false];
        }
        if ($company('jkm') > 0 || $resigned) {
            $items[] = ['label' => 'JKM (Jaminan Kematian)', 'amount' => $bpjs['jkm']['company'], 'is_basis' => false];
        }
        if ($company('jht') > 0 || $resigned) {
            $items[] = ['label' => 'JHT Perusahaan (Jaminan Hari Tua)', 'amount' => $bpjs['jht']['company'], 'is_basis' => false];
        }
        if ($company('jp') > 0) {
            $items[] = ['label' => 'JP Perusahaan (Jaminan Pensiun)', 'amount' => $bpjs['jp']['company'], 'is_basis' => false];
        }
        if ($company('kesehatan') > 0) {
            $items[] = ['label' => 'BPJS Kesehatan Perusahaan', 'amount' => $bpjs['kesehatan']['company'], 'is_basis' => false];
        }

        return $items;
    }

    /**
     * Nomor registrasi BPJS dianggap ADA hanya jika ada karakter selain spasi/tanda hubung.
     * Placeholder seperti "-", "--", atau kosong berarti belum terdaftar → program di-nol-kan.
     */
    public static function hasRegistrationNumber(mixed $value): bool
    {
        return trim((string) $value, " \t\n\r\0\x0B-") !== '';
    }

    private static function zero(array $bpjs, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($bpjs[$key])) {
                $bpjs[$key]['company'] = 0;
                $bpjs[$key]['employee'] = 0;
            }
        }

        return $bpjs;
    }

    private static function refreshTotals(array $bpjs): array
    {
        $keys = ['kesehatan', 'jht', 'jkk', 'jkm', 'jp'];
        $bpjs['company_total'] = collect($keys)->sum(fn ($k) => (float) ($bpjs[$k]['company'] ?? 0));
        $bpjs['employee_total'] = collect($keys)->sum(fn ($k) => (float) ($bpjs[$k]['employee'] ?? 0));
        $bpjs['grand_total'] = $bpjs['company_total'] + $bpjs['employee_total'];

        return $bpjs;
    }

    /**
     * Untuk karyawan yang KELUAR (resign) di bulan periode, nol-kan JHT/JKK/JKM
     * (Ketenagakerjaan selain JP). BPJS Kesehatan & JP tetap. Nilainya tetap 0 (bukan
     * dihapus) agar bisa ditampilkan sebagai baris Rp 0 di benefit.
     */
    public static function dropKetenagakerjaanForResign(array $bpjs, ?Employee $employee, Carbon $periodStart): array
    {
        if (self::isResignedInMonth($employee, $periodStart)) {
            foreach (['jht', 'jkk', 'jkm'] as $key) {
                if (isset($bpjs[$key])) {
                    $bpjs[$key]['company'] = 0;
                    $bpjs[$key]['employee'] = 0;
                }
            }
        }

        return $bpjs;
    }
}
