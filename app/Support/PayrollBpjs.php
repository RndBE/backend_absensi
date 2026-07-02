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

        // Tanpa nomor registrasi → program terkait 0.
        if (! filled($payroll->bpjs_kesehatan)) {
            $bpjs = self::zero($bpjs, ['kesehatan']);
        }
        if (! filled($payroll->bpjs_ketenagakerjaan)) {
            $bpjs = self::zero($bpjs, ['jht', 'jkk', 'jkm', 'jp']);
        }

        return self::refreshTotals($bpjs);
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
