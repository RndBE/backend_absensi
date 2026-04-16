<?php

namespace App\Services;

use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use Illuminate\Support\Carbon;

class AutoOvertimeService
{
    /**
     * Generate lembur otomatis untuk satu ScheduleAssignment.
     *
     * Dipanggil saat:
     * 1. Command harian: overtime:auto-generate
     * 2. (Opsional) hook setelah admin assign jadwal ke karyawan
     *
     * @param ScheduleAssignment $assignment
     * @return OvertimeRequest|null  Record yang dibuat, atau null jika tidak perlu
     */
    public static function generateForAssignment(ScheduleAssignment $assignment): ?OvertimeRequest
    {
        $assignment->loadMissing('shift');
        $shift = $assignment->shift;

        if (!$shift || !$shift->auto_overtime) {
            return null;
        }

        $overtimeMinutes = $shift->getOvertimeMinutes();

        if ($overtimeMinutes <= 0) {
            return null;
        }

        $empId  = $assignment->employee_id;
        $date   = Carbon::parse($assignment->date)->format('Y-m-d');

        // Hindari duplikasi: cek apakah sudah ada OT auto di tanggal ini
        $exists = OvertimeRequest::where('employee_id', $empId)
            ->where('date', $date)
            ->where('overtime_type', 'workday')
            ->exists();

        if ($exists) {
            return null;
        }

        $shiftDurationHours = round($shift->getShiftDurationMinutes() / 60, 0);
        $shiftName          = $shift->name;

        return OvertimeRequest::create([
            'employee_id'         => $empId,
            'date'                => $date,
            'overtime_type'       => 'workday',
            // Dihitung sebagai post-shift (lembur sesudah jam kerja standar)
            'pre_shift_duration'  => 0,
            'pre_shift_break'     => 0,
            'post_shift_duration' => $overtimeMinutes,
            'post_shift_break'    => 0,
            'break_duration'      => 0,
            'total_duration'      => $overtimeMinutes,
            'reason'              => "Lembur otomatis — shift {$shiftName} ({$shiftDurationHours} jam), jam kerja standar {$shift->work_hours} jam.",
            // Langsung approved, tidak perlu melalui alur approval
            'status'              => 'approved',
            'current_step'        => 0,
        ]);
    }

    /**
     * Generate lembur otomatis untuk semua jadwal di tanggal tertentu.
     *
     * @param Carbon $date
     * @return array{generated: int, skipped: int}
     */
    public static function generateForDate(Carbon $date): array
    {
        $generated = 0;
        $skipped   = 0;

        // Ambil semua ScheduleAssignment di tanggal ini yang shiftnya auto_overtime
        $assignments = ScheduleAssignment::where('date', $date->format('Y-m-d'))
            ->whereHas('shift', fn($q) => $q->where('auto_overtime', true)->where('work_hours', '>', 0))
            ->with('shift')
            ->get();

        foreach ($assignments as $assignment) {
            $result = self::generateForAssignment($assignment);

            if ($result) {
                $generated++;
            } else {
                $skipped++;
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }
}
