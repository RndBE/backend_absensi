<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\Carbon;

class PayrollBpjs
{
    /** Apakah karyawan keluar (resign / hari kerja terakhir) di bulan periode ini. */
    public static function isResignedInMonth(?Employee $employee, Carbon $periodStart): bool
    {
        $exitRaw = $employee?->last_working_date ?: $employee?->resign_date;

        return $exitRaw && Carbon::parse($exitRaw)->isSameMonth($periodStart);
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
