<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DailyReportPrefillController extends Controller
{
    public function show(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $employee = $request->user();
        $date = $request->date ? Carbon::parse($request->date) : now();
        $dateString = $date->toDateString();

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $dateString)
            ->first();

        $overtime = OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $dateString)
            ->whereIn('status', ['pending', 'approved'])
            ->latest('status')
            ->latest('id')
            ->first();

        $overtimeData = $overtime
            ? $this->formatOvertime($overtime, $employee, $date, $attendance)
            : [
                'overtime_status' => false,
                'overtime_start' => null,
                'overtime_end' => null,
                'overtime_source' => null,
            ];

        return response()->json([
            'success' => true,
            'data' => array_merge([
                'date' => $dateString,
                'work_finished_at' => $this->timeOnly($attendance?->clock_out),
            ], $overtimeData),
        ]);
    }

    private function formatOvertime(
        OvertimeRequest $overtime,
        Employee $employee,
        Carbon $date,
        ?Attendance $attendance
    ): array {
        if ($overtime->overtime_type === 'holiday') {
            return [
                'overtime_status' => true,
                'overtime_start' => $this->timeOnly($overtime->planned_start),
                'overtime_end' => $this->timeOnly($overtime->planned_end),
                'overtime_source' => 'holiday',
            ];
        }

        $shiftStart = $this->getShiftStartTime($employee, $date);
        $shiftEnd = $overtime->shift_end_time ?: $this->getShiftEndTime($employee, $date);
        $start = null;
        $end = null;
        $sources = [];

        if ((int) $overtime->pre_shift_duration > 0) {
            $shiftStartTime = $this->timeOnly($shiftStart);
            $preMinutes = $this->countedOvertimeMinutes($overtime, 'pre_shift');

            if ($shiftStartTime && $preMinutes > 0) {
                $start = $this->addMinutesToTime($shiftStartTime, -$preMinutes);
                $end = $shiftStartTime;
                $sources[] = 'pre_shift';
            }
        }

        if ((int) $overtime->post_shift_duration > 0 || ((int) $overtime->total_duration > 0 && empty($sources))) {
            $shiftEndTime = $this->timeOnly($shiftEnd);
            $postMinutes = $this->countedOvertimeMinutes($overtime, 'post_shift');

            if ($shiftEndTime && $postMinutes > 0) {
                $start ??= $shiftEndTime;
                $end = $this->addMinutesToTime($shiftEndTime, $postMinutes);
                $sources[] = 'post_shift';
            }
        }

        return [
            'overtime_status' => filled($start) && filled($end),
            'overtime_start' => $start,
            'overtime_end' => $end,
            'overtime_source' => implode(',', $sources) ?: null,
        ];
    }

    private function getShiftStartTime(Employee $employee, Carbon $date): ?string
    {
        $assignment = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($assignment?->shift && ! $assignment->shift->is_off) {
            return $assignment->shift->start_time;
        }

        // Template yang berlaku pada tanggal itu (riwayat).
        $shift = $employee->scheduleTemplateOn($date)?->getShiftForDay($date->dayOfWeekIso);
        if ($shift && ! $shift->is_off) {
            return $shift->start_time;
        }

        if ($employee->work_schedule_id) {
            $employee->loadMissing('workSchedule');

            return $employee->workSchedule?->start_time;
        }

        return null;
    }

    private function getShiftEndTime(Employee $employee, Carbon $date): ?string
    {
        $assignment = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($assignment?->shift && ! $assignment->shift->is_off) {
            return $assignment->shift->end_time;
        }

        // Template yang berlaku pada tanggal itu (riwayat).
        $shift = $employee->scheduleTemplateOn($date)?->getShiftForDay($date->dayOfWeekIso);
        if ($shift && ! $shift->is_off) {
            return $shift->end_time;
        }

        if ($employee->work_schedule_id) {
            $employee->loadMissing('workSchedule');

            return $employee->workSchedule?->end_time;
        }

        return null;
    }

    private function timeOnly(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function countedOvertimeMinutes(OvertimeRequest $overtime, string $segment): int
    {
        if ($segment === 'pre_shift') {
            return max(0, (int) $overtime->pre_shift_duration - (int) ($overtime->pre_shift_break ?? 0));
        }

        $postDuration = (int) ($overtime->post_shift_duration ?? 0);
        if ($postDuration > 0) {
            return max(0, $postDuration - (int) ($overtime->post_shift_break ?? 0));
        }

        if (! is_null($overtime->approved_duration)) {
            return max(0, (int) $overtime->approved_duration - (int) ($overtime->approved_break ?? $overtime->break_duration ?? 0));
        }

        return max(0, (int) $overtime->total_duration - (int) ($overtime->break_duration ?? 0));
    }

    private function addMinutesToTime(string $time, int $minutes): string
    {
        return Carbon::createFromFormat('H:i', $time)
            ->addMinutes($minutes)
            ->format('H:i');
    }
}
