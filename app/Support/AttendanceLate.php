<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ScheduleAssignment;
use Illuminate\Support\Carbon;

class AttendanceLate
{
    /**
     * Jam mulai shift efektif untuk karyawan pada tanggal tersebut.
     * Prioritas: override jadwal (schedule_assignments) → libur → template mingguan → work schedule.
     * Mengembalikan null jika libur, shift off, atau tidak ada jadwal.
     *
     * Ini SATU sumber kebenaran yang juga dipakai saat clock-in
     * (App\Http\Controllers\Api\AttendanceController::getShiftStartTime).
     */
    public static function shiftStartTime(Employee $employee, Carbon $date): ?string
    {
        // 1. Override manual di schedule_assignments (paling menang).
        $override = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('date', $date->toDateString())
            ->first();

        if ($override?->shift) {
            return $override->shift->is_off ? null : $override->shift->start_time;
        }

        // 2. Hari libur perusahaan.
        $holiday = Holiday::where('company_id', $employee->company_id)
            ->where('date', $date->toDateString())
            ->exists();

        if ($holiday) {
            return null;
        }

        // 3. Template jadwal mingguan.
        if ($employee->schedule_template_id) {
            $employee->loadMissing('scheduleTemplate.days.shift');
            $shift = $employee->scheduleTemplate?->getShiftForDay($date->dayOfWeekIso);
            if ($shift && ! $shift->is_off) {
                return $shift->start_time;
            }
        }

        // 4. Work schedule tetap.
        if ($employee->work_schedule_id) {
            $employee->loadMissing('workSchedule');
            return $employee->workSchedule?->start_time;
        }

        return null;
    }

    /**
     * Hitung ulang is_late untuk absensi yang sudah ada (punya clock_in) berdasarkan
     * jadwal terkini. Dipakai saat shift/jadwal diubah setelah karyawan clock-in,
     * mis. tukeran shift yang baru diinput belakangan.
     *
     * @return bool true jika nilai is_late berubah.
     */
    public static function recalculate(Employee $employee, Carbon|string $date): bool
    {
        $dateObj = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateStr = $dateObj->toDateString();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $dateStr)
            ->first();

        // Tidak ada absensi atau belum clock-in → tidak ada yang dihitung.
        if (! $attendance || ! $attendance->clock_in) {
            return false;
        }

        // Jangan sentuh record yang ditolak review keamanan (sudah dianggap absen).
        if (($attendance->review_status ?? null) === 'rejected') {
            return false;
        }

        $shiftStart = self::shiftStartTime($employee, $dateObj);

        if (! $shiftStart) {
            // Libur / shift off / tanpa jadwal → tidak dianggap terlambat.
            $isLate = false;
        } else {
            $clockIn = Carbon::parse($dateStr.' '.$attendance->clock_in)->startOfMinute();
            $start = Carbon::parse($dateStr.' '.$shiftStart)->startOfMinute();
            $isLate = $clockIn->gt($start);
        }

        if ((bool) $attendance->is_late !== $isLate) {
            $attendance->update(['is_late' => $isLate]);
            return true;
        }

        return false;
    }
}
