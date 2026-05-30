<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\AttendanceRequest;
use App\Support\AdminDashboardSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(AdminDashboardSummary $dashboardSummary)
    {
        $today = Carbon::today();
        $admin = Employee::find(session('admin_id'));
        $summary = $dashboardSummary->forAdmin($admin);

        $totalEmployees = $summary['attendance']['total_employees'];
        $presentToday = $summary['attendance']['present_today'];
        $lateToday = $summary['attendance']['late_today'];
        $absentToday = $summary['attendance']['absent_today'];
        $pendingLeave = $summary['approvals']['leave_pending'];
        $pendingOvertime = $summary['approvals']['overtime_pending'];
        $pendingAttendance = $summary['approvals']['attendance_pending'];
        $totalPending = $summary['approvals']['total_pending'];
        $lateThisMonth = $summary['attendance']['late_this_month'];
        $resignedThisMonth = $summary['hr']['resigned_this_month'];
        $contractsEndingSoonCount = $summary['hr']['contracts_expiring_soon'];
        $contractWindowEnd = $today->copy()->addDays($summary['hr']['contract_window_days']);

        $contractsEndingSoon = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$today->toDateString(), $contractWindowEnd->toDateString()])
            ->orderBy('contract_end_date')
            ->limit(5)
            ->get(['id', 'employee_code', 'full_name', 'position', 'employment_status', 'contract_end_date']);

        // Recent attendance
        $recentAttendance = Attendance::with('employee:id,full_name,photo,department_id', 'employee.department:id,name')
            ->whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)
            ->orderBy('clock_in', 'desc')
            ->limit(10)
            ->get();

        // Recent leave requests
        $recentLeaves = LeaveRequest::with(['employee:id,full_name,photo', 'leaveType:id,name'])
            ->whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalEmployees', 'presentToday', 'lateToday', 'absentToday',
            'totalPending', 'pendingLeave', 'pendingOvertime', 'pendingAttendance',
            'lateThisMonth', 'resignedThisMonth', 'contractsEndingSoonCount',
            'contractsEndingSoon', 'recentAttendance', 'recentLeaves', 'summary'
        ));
    }
}
