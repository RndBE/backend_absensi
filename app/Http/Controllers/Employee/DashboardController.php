<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
use App\Support\AttendanceOpenShift;
use App\Support\AttendanceLateExcuse;
use App\Support\PendingApprovalCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $today = Carbon::today();

        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $today->toDateString())
            ->first();

        if (! $todayAttendance) {
            $yesterday = $today->copy()->subDay();
            $openYesterdayAttendance = Attendance::where('employee_id', $employee->id)
                ->where('date', $yesterday->toDateString())
                ->whereNotNull('clock_in')
                ->whereNull('clock_out')
                ->first();

            if ($openYesterdayAttendance && AttendanceOpenShift::isOvernight($employee, $yesterday)) {
                $todayAttendance = $openYesterdayAttendance;
            }
        }

        $recentAttendances = Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->orderBy('date', 'desc')
            ->limit(8)
            ->get();

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $approvedLeaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where('end_date', '>=', $monthStart->toDateString())
            ->get();
        $lateExcuseDates = AttendanceLateExcuse::lateExcuseDates($approvedLeaves, $monthStart, $monthEnd);

        return view('employee.dashboard', [
            'employee' => $employee,
            'today' => $today,
            'todayAttendance' => $todayAttendance,
            'recentAttendances' => $recentAttendances,
            'lateExcuseDates' => $lateExcuseDates,
            'schedule' => $this->todaySchedule($employee, $today),
            'pendingApprovalCount' => app(PendingApprovalCounter::class)->countForApprover($employee),
            'settings' => [
                'office_latitude' => (float) Setting::getValue('office_latitude', '0'),
                'office_longitude' => (float) Setting::getValue('office_longitude', '0'),
                'office_radius_meters' => (int) Setting::getValue('office_radius_meters', '100'),
                'require_photo' => Setting::getValue('require_photo', '1') === '1',
                'require_gps' => Setting::getValue('require_gps', '1') === '1',
                'allow_remote_clockin' => Setting::getValue('allow_remote_clockin', '0') === '1',
                'face_verification_enabled' => Setting::getValue('face_verification_enabled', '1') === '1',
            ],
        ]);
    }

    private function todaySchedule(Employee $employee, Carbon $date): array
    {
        if (Schema::hasTable('schedule_assignments')) {
            $assignment = ScheduleAssignment::with('shift')
                ->where('employee_id', $employee->id)
                ->where('date', $date->toDateString())
                ->first();

            if ($assignment?->shift) {
                return $this->formatShift($assignment->shift->name, $assignment->shift->start_time, $assignment->shift->end_time, $assignment->shift->is_off);
            }
        }

        if (Schema::hasTable('holidays')) {
            $holiday = Holiday::where('company_id', $employee->company_id)
                ->where('date', $date->toDateString())
                ->first();

            if ($holiday) {
                return [
                    'name' => $holiday->name ?: 'Libur Nasional',
                    'time' => 'Libur Nasional',
                    'is_off' => true,
                ];
            }
        }

        if ($employee->schedule_template_id && Schema::hasTable('schedule_templates')) {
            $employee->loadMissing('scheduleTemplate.days.shift');
            $shift = $employee->scheduleTemplate?->getShiftForDay($date->dayOfWeekIso);

            if ($shift) {
                return $this->formatShift($employee->scheduleTemplate->name.' - '.$shift->name, $shift->start_time, $shift->end_time, $shift->is_off);
            }
        }

        if ($employee->work_schedule_id && Schema::hasTable('work_schedules')) {
            $employee->loadMissing('workSchedule');

            if ($employee->workSchedule) {
                return $this->formatShift($employee->workSchedule->name, $employee->workSchedule->start_time, $employee->workSchedule->end_time, false);
            }
        }

        return [
            'name' => 'Jadwal belum diatur',
            'time' => '-',
            'is_off' => false,
        ];
    }

    private function formatShift(string $name, ?string $startTime, ?string $endTime, bool $isOff): array
    {
        return [
            'name' => $isOff ? 'Libur' : $name,
            'time' => $isOff ? '-' : trim(substr((string) $startTime, 0, 5).' - '.substr((string) $endTime, 0, 5)),
            'is_off' => $isOff,
        ];
    }
}
