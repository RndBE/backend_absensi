<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * Skor 1–5 untuk indikator kedisiplinan & kehadiran, dihitung dari data absensi periode.
 *
 * CATATAN: rubrik di bawah TIDAK berasal dari dokumen kerangka — dokumen tidak memberi
 * patokan angka untuk indikator ini. Tangganya dibuat mengikuti gaya rubrik `EX-L4-02`
 * (berbasis persentase) dan harus ditinjau HRD sebelum dipakai pada periode bernilai.
 *
 * Indikator ini baru terisi otomatis bila admin menandai `auto_source` = attendance;
 * secara bawaan seeder membiarkannya diisi manual oleh penilai.
 */
class KpiAttendanceScore
{
    /** Ambang bawah rasio hadir-tepat-waktu untuk tiap skor. */
    private const LADDER = [
        5 => 1.00,
        4 => 0.95,
        3 => 0.85,
        2 => 0.70,
    ];

    /** Status yang berarti karyawan tetap bekerja hari itu. */
    private const WORKED_STATUSES = ['present', 'wfh', 'late_excuse', 'early_departure'];

    /** Status sah yang tidak boleh menghukum skor — dikeluarkan dari pembagi. */
    private const EXCUSED_STATUSES = ['leave', 'sick', 'holiday'];

    /**
     * @return array{score:int|null, evidence:string}
     *         score null berarti data tidak cukup; pemanggil memakai nilai bawaan 3
     *         sesuai Bab 11.4 ("ketiadaan data bukan berarti kinerja buruk").
     */
    public function for(Employee $employee, Carbon $start, Carbon $end): array
    {
        $rows = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['status', 'is_late']);

        $scheduled = ScheduledWorkingDays::count($employee, $start, $end);
        $excused = $rows->whereIn('status', self::EXCUSED_STATUSES)->count();
        $alpha = $rows->where('status', 'absent')->count();
        $late = $rows->whereIn('status', self::WORKED_STATUSES)->where('is_late', true)->count();

        $effectiveDays = $scheduled - $excused;

        if ($effectiveDays <= 0) {
            return [
                'score' => null,
                'evidence' => 'Data absensi tidak cukup untuk dihitung (hari kerja efektif 0).',
            ];
        }

        $onTime = max(0, $effectiveDays - $alpha - $late);
        $rate = $onTime / $effectiveDays;

        $score = 1;
        foreach (self::LADDER as $value => $threshold) {
            if ($rate >= $threshold) {
                $score = $value;
                break;
            }
        }

        // Mangkir bukan sekadar mengurangi rasio — satu hari alpha menurunkan satu tingkat.
        // Tanpa ini, karyawan dengan 1 alpha dari 120 hari kerja tetap mendapat skor 5.
        $score = max(1, $score - $alpha);

        return [
            'score' => $score,
            'evidence' => sprintf(
                'Otomatis dari absensi %s–%s: %d hari kerja efektif, %d alpha, %d keterlambatan, hadir tepat waktu %s%%.',
                $start->format('d/m/Y'),
                $end->format('d/m/Y'),
                $effectiveDays,
                $alpha,
                $late,
                number_format($rate * 100, 1, ',', '.')
            ),
        ];
    }
}
