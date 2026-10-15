<?php

namespace App\Support;

use App\Models\Employee;

/**
 * Level jabatan karyawan 1-4 (L1 Direksi, L2 Manajer, L3 Leader/SPV, L4 Staff).
 *
 * Level KPI yang menang. Bila kosong dipakai `job_level` 1-4, yang untuk karyawan aktif
 * sejajar dengan L1-L4. Dipakai untuk menentukan siapa boleh melihat presensi siapa.
 */
class OrgLevel
{
    /** Level tertinggi (angka terbesar) yang masih boleh melihat presensi tim. */
    public const MAX_VIEWER_LEVEL = 3;

    /** Level karyawan yang levelnya tidak diketahui: dianggap staf. */
    public const UNKNOWN_AS = 4;

    public static function of(Employee $employee): ?int
    {
        if (preg_match('/^L([1-4])$/i', (string) $employee->kpiLevel?->code, $m)) {
            return (int) $m[1];
        }

        $jobLevel = (int) $employee->job_level;

        return $jobLevel >= 1 && $jobLevel <= 4 ? $jobLevel : null;
    }

    /**
     * Level yang dipakai saat karyawan MELIHAT tim, atau null bila tidak boleh melihat.
     * Role `manager` tanpa level tetap dianggap L2, seperti sebelum ada aturan level.
     */
    public static function viewerLevel(Employee $employee): ?int
    {
        $level = self::of($employee) ?? ($employee->role === 'manager' ? 2 : null);

        return $level !== null && $level <= self::MAX_VIEWER_LEVEL ? $level : null;
    }
}
