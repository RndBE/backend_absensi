<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
use App\Support\AttendanceOpenShift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user();
        $today = Carbon::today();
        $dayOfWeek = (int) $today->dayOfWeekIso; // 1=Mon ... 7=Sun

        $employee->load(['department', 'company', 'workSchedule', 'scheduleTemplate.days.shift']);

        // Today's attendance
        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();
        $activeAttendance = $todayAttendance;

        if (! $activeAttendance) {
            $yesterday = $today->copy()->subDay();
            $openYesterdayAttendance = Attendance::where('employee_id', $employee->id)
                ->where('date', $yesterday->toDateString())
                ->whereNotNull('clock_in')
                ->whereNull('clock_out')
                ->first();

            if ($openYesterdayAttendance && AttendanceOpenShift::isOvernight($employee, $yesterday)) {
                $activeAttendance = $openYesterdayAttendance;
            }
        }

        // === Resolve today's shift (new system first, fallback to legacy) ===
        $workScheduleData = null;

        // 1. Manual per-day assignment (ScheduleAssignment)
        $manualAssignment = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('date', $today)
            ->with('shift')
            ->first();

        if ($manualAssignment && $manualAssignment->shift) {
            $shift = $manualAssignment->shift;
            if (! $shift->is_off) {
                $workScheduleData = [
                    'name' => $shift->name,
                    'work_days' => null,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ];
            } else {
                // Explicitly a day-off
                $workScheduleData = [
                    'name' => 'Libur',
                    'work_days' => null,
                    'start_time' => null,
                    'end_time' => null,
                ];
            }
        }

        // 2. Template-based schedule (ScheduleTemplate)
        if (! $workScheduleData && $employee->scheduleTemplate) {
            $shift = $employee->scheduleTemplate->getShiftForDay($dayOfWeek);
            if ($shift) {
                if (! $shift->is_off) {
                    $workScheduleData = [
                        'name' => $employee->scheduleTemplate->name.' – '.$shift->name,
                        'work_days' => null,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                    ];
                } else {
                    $workScheduleData = [
                        'name' => 'Libur',
                        'work_days' => null,
                        'start_time' => null,
                        'end_time' => null,
                    ];
                }
            }
        }

        // 3. Fallback: legacy WorkSchedule
        if (! $workScheduleData && $employee->workSchedule) {
            $workScheduleData = [
                'name' => $employee->workSchedule->name,
                'work_days' => $employee->workSchedule->work_days,
                'start_time' => $employee->workSchedule->start_time,
                'end_time' => $employee->workSchedule->end_time,
            ];
        }

        // Team members (same department)
        $teamMembers = Employee::where('department_id', $employee->department_id)
            ->where('id', '!=', $employee->id)
            ->where('is_active', true)
            ->select('id', 'full_name', 'photo', 'position')
            ->get();

        // Absent today (same company)
        $presentIds = Attendance::where('date', $today)
            ->whereNotNull('clock_in')
            ->pluck('employee_id');

        $absentToday = Employee::where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->whereNotIn('id', $presentIds)
            ->select('id', 'full_name', 'photo', 'department_id')
            ->with('department:id,name')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                    'position' => $employee->position,
                    'department' => $employee->department?->name,
                    'company' => $employee->company?->name,
                    'photo' => $employee->photo ? asset('storage/'.$employee->photo) : null,
                    'role' => $employee->role,
                ],
                'work_schedule' => $workScheduleData,
                'today_attendance' => $activeAttendance ? [
                    'clock_in' => $activeAttendance->clock_in,
                    'clock_out' => $activeAttendance->clock_out,
                    'status' => $activeAttendance->status,
                    'is_late' => $activeAttendance->is_late,
                    'is_remote' => $activeAttendance->is_remote,
                ] : null,
                'attendance_settings' => [
                    'office_latitude' => (float) Setting::getValue('office_latitude', '0'),
                    'office_longitude' => (float) Setting::getValue('office_longitude', '0'),
                    'office_radius_meters' => (int) Setting::getValue('office_radius_meters', '100'),
                    'require_photo' => Setting::getValue('require_photo', '1') === '1',
                    'allow_remote_clockin' => Setting::getValue('allow_remote_clockin', '0') === '1',
                ],
                'team_members' => $teamMembers,
                'absent_today' => $absentToday,
            ],
        ]);
    }

    public function companyInfo(Request $request)
    {
        $employee = $request->user();
        $company = $employee->company;

        if (! $company) {
            $company = Company::first();
        }

        return response()->json([
            'success' => true,
            'data' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
                'address' => $company->address,
                'phone' => $company->phone,
                'email' => $company->email,
                'npwp' => $company->npwp,
            ] : null,
        ]);
    }
}
