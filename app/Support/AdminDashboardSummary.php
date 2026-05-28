<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Support\Carbon;

class AdminDashboardSummary
{
    public function forAdmin(Employee $admin): array
    {
        $today = Carbon::today();
        $todayDate = $today->toDateString();
        $companyId = $admin->company_id;

        $totalEmployees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        $attendedToday = Attendance::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->where('date', $todayDate)
            ->whereNotNull('clock_in')
            ->count();

        $presentToday = Attendance::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->where('date', $todayDate)
            ->whereNotNull('clock_in')
            ->where('is_late', false)
            ->count();

        $lateToday = Attendance::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->where('date', $todayDate)
            ->where('is_late', true)
            ->count();

        $lateThisMonth = Attendance::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
            ->where('is_late', true)
            ->count();

        $pendingStatuses = ['pending', 'in_review'];
        $pendingLeave = LeaveRequest::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', $pendingStatuses)
            ->count();
        $pendingOvertime = OvertimeRequest::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', $pendingStatuses)
            ->count();
        $pendingAttendance = AttendanceRequest::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', $pendingStatuses)
            ->count();

        $contractWindowEnd = $today->copy()->addDays(30);

        return [
            'attendance' => [
                'total_employees' => $totalEmployees,
                'present_today' => $presentToday,
                'late_today' => $lateToday,
                'late_this_month' => $lateThisMonth,
                'absent_today' => max(0, $totalEmployees - $attendedToday),
            ],
            'approvals' => [
                'leave_pending' => $pendingLeave,
                'overtime_pending' => $pendingOvertime,
                'attendance_pending' => $pendingAttendance,
                'total_pending' => $pendingLeave + $pendingOvertime + $pendingAttendance,
            ],
            'hr' => [
                'resigned_this_month' => Employee::where('company_id', $companyId)
                    ->whereBetween('resign_date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
                    ->count(),
                'contracts_expiring_soon' => Employee::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereBetween('contract_end_date', [$todayDate, $contractWindowEnd->toDateString()])
                    ->count(),
                'inactive_employees' => Employee::where('company_id', $companyId)
                    ->where('is_active', false)
                    ->count(),
                'contract_window_days' => 30,
            ],
        ];
    }
}
