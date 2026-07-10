<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendanceOpenShift
{
    public static function isOvernight(Employee $employee, CarbonInterface $date): bool
    {
        $shift = self::shiftForDate($employee, $date);

        if ($shift) {
            return ! $shift->is_off && self::timeRangeIsOvernight(
                (string) $shift->start_time,
                (string) $shift->end_time,
                (bool) $shift->is_overnight
            );
        }

        if ($employee->work_schedule_id && Schema::hasTable('work_schedules')) {
            $employee->loadMissing('workSchedule');

            if ($employee->workSchedule) {
                return self::timeRangeIsOvernight(
                    (string) $employee->workSchedule->start_time,
                    (string) $employee->workSchedule->end_time
                );
            }
        }

        return false;
    }

    private static function shiftForDate(Employee $employee, CarbonInterface $date): ?Shift
    {
        if (Schema::hasTable('schedule_assignments')) {
            $assignment = ScheduleAssignment::with('shift')
                ->where('employee_id', $employee->id)
                ->where('date', $date->toDateString())
                ->first();

            if ($assignment?->shift) {
                return $assignment->shift;
            }
        }

        if (
            Schema::hasTable('schedule_templates')
            && Schema::hasTable('schedule_template_days')
        ) {
            // Template yang berlaku pada tanggal itu (riwayat), bukan yang terpasang sekarang.
            return $employee->scheduleTemplateOn($date)?->getShiftForDay($date->dayOfWeekIso);
        }

        return null;
    }

    private static function timeRangeIsOvernight(?string $startTime, ?string $endTime, bool $explicit = false): bool
    {
        if ($explicit) {
            return true;
        }

        if (! $startTime || ! $endTime) {
            return false;
        }

        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        return $end->lte($start);
    }
}
