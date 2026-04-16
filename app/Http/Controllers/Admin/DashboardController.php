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
        $admin = Employee::find(session('admin_id'));

        // Stats
        $totalEmployees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->count();
        $presentToday = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)->whereNotNull('clock_in')->count();
        $lateToday = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)->where('is_late', true)->count();
        $absentToday = $totalEmployees - $presentToday;

        // Pending approvals
        $pendingLeave = LeaveRequest::where('status', 'pending')->count();
        $pendingOvertime = OvertimeRequest::where('status', 'pending')->count();
        $pendingAttendance = AttendanceRequest::where('status', 'pending')->count();
        $totalPending = $pendingLeave + $pendingOvertime + $pendingAttendance;

        // Recent attendance
        $recentAttendance = Attendance::with('employee:id,full_name,photo,department_id', 'employee.department:id,name')
            ->where('date', $today)
            ->orderBy('clock_in', 'desc')
            ->limit(10)
            ->get();

        // Recent leave requests
        $recentLeaves = LeaveRequest::with(['employee:id,full_name,photo', 'leaveType:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalEmployees', 'presentToday', 'lateToday', 'absentToday',
            'totalPending', 'pendingLeave', 'pendingOvertime', 'pendingAttendance',
            'recentAttendance', 'recentLeaves'
        ));
    }
}
