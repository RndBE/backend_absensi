<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $contractWarningEnd = $today->copy()->addDays(30);
        $admin = Employee::find(session('admin_id'));

        // Stats
        $totalEmployees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->count();
        $presentToday = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)->whereNotNull('clock_in')->count();
        $lateToday = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)->where('is_late', true)->count();
        $absentToday = $totalEmployees - $presentToday;
        $lateThisMonth = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('is_late', true)
            ->count();

        // Pending approvals
        $pendingStatuses = ['pending', 'in_review'];
        $pendingLeave = LeaveRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereIn('status', $pendingStatuses)
            ->count();
        $pendingOvertime = OvertimeRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereIn('status', $pendingStatuses)
            ->count();
        $pendingAttendance = AttendanceRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereIn('status', $pendingStatuses)
            ->count();
        $totalPending = $pendingLeave + $pendingOvertime + $pendingAttendance;
        $resignedThisMonth = Employee::where('company_id', $admin->company_id)
            ->whereBetween('resign_date', [$startOfMonth, $endOfMonth])
            ->count();
        $contractsEndingSoon = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->whereIn('employment_status', ['contract', 'intern', 'probation'])
            ->whereBetween('contract_end_date', [$today, $contractWarningEnd])
            ->orderBy('contract_end_date')
            ->limit(6)
            ->get(['id', 'full_name', 'employee_code', 'position', 'employment_status', 'contract_end_date']);
        $contractsEndingSoonCount = $contractsEndingSoon->count();

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
            'lateThisMonth', 'resignedThisMonth', 'contractsEndingSoon',
            'contractsEndingSoonCount', 'recentAttendance', 'recentLeaves'
        ));
    }
}
