<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user();
        $today = Carbon::today();
        $employee->load(['department', 'company', 'workSchedule']);

        // Today's attendance
        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

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
                    'photo' => $employee->photo ? asset('storage/' . $employee->photo) : null,
                ],
                'work_schedule' => $employee->workSchedule ? [
                    'name' => $employee->workSchedule->name,
                    'work_days' => $employee->workSchedule->work_days,
                    'start_time' => $employee->workSchedule->start_time,
                    'end_time' => $employee->workSchedule->end_time,
                ] : null,
                'today_attendance' => $todayAttendance ? [
                    'clock_in' => $todayAttendance->clock_in,
                    'clock_out' => $todayAttendance->clock_out,
                    'status' => $todayAttendance->status,
                    'is_late' => $todayAttendance->is_late,
                    'is_remote' => $todayAttendance->is_remote,
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
}
