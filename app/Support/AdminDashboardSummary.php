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

        // Manager: seluruh angka dibatasi ke departemennya. Role lain: se-perusahaan.
        $departmentId = AdminDataScope::departmentId($admin);
        $scopeEmployee = fn ($q) => $q->where('company_id', $companyId)
            ->when($departmentId, fn ($e, $d) => $e->where('department_id', $d));

        $totalEmployees = Employee::where('company_id', $companyId)->when($departmentId, fn ($q, $d) => $q->where('department_id', $d))
            ->where('is_active', true)
            ->count();

        $attendedToday = Attendance::whereHas('employee', $scopeEmployee)
            ->where('date', $todayDate)
            ->whereNotNull('clock_in')
            ->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->count();

        $presentToday = Attendance::whereHas('employee', $scopeEmployee)
            ->where('date', $todayDate)
            ->whereNotNull('clock_in')
            ->where('is_late', false)
            ->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->count();

        $lateToday = Attendance::whereHas('employee', $scopeEmployee)
            ->where('date', $todayDate)
            ->where('is_late', true)
            ->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->count();

        $lateThisMonth = Attendance::whereHas('employee', $scopeEmployee)
            ->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
            ->where('is_late', true)
            ->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->count();

        $pendingStatuses = ['pending', 'in_review'];
        $pendingLeave = LeaveRequest::whereHas('employee', $scopeEmployee)
            ->whereIn('status', $pendingStatuses)
            ->count();
        $pendingOvertime = OvertimeRequest::whereHas('employee', $scopeEmployee)
            ->whereIn('status', $pendingStatuses)
            ->count();
        $pendingAttendance = AttendanceRequest::whereHas('employee', $scopeEmployee)
            ->whereIn('status', $pendingStatuses)
            ->count();

        $contractWindowEnd = $today->copy()->addDays(60);

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
                'resigned_this_month' => Employee::where('company_id', $companyId)->when($departmentId, fn ($q, $d) => $q->where('department_id', $d))
                    ->whereBetween('resign_date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
                    ->count(),
                'contracts_expiring_soon' => Employee::where('company_id', $companyId)->when($departmentId, fn ($q, $d) => $q->where('department_id', $d))
                    ->where('is_active', true)
                    ->whereNotNull('contract_end_date')
                    ->whereBetween('contract_end_date', [$todayDate, $contractWindowEnd->toDateString()])
                    ->count(),
                'inactive_employees' => Employee::where('company_id', $companyId)->when($departmentId, fn ($q, $d) => $q->where('department_id', $d))
                    ->where('is_active', false)
                    ->count(),
                'contract_window_days' => 60,
            ],
        ];
    }
}
