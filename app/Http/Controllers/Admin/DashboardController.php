<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use App\Support\AdminDashboardSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)
            ->orderBy('clock_in', 'desc')
            ->limit(10)
            ->get();

        $recentRequests = $this->getRecentRequests($admin->company_id);

        return view('admin.dashboard', compact(
            'totalEmployees', 'presentToday', 'lateToday', 'absentToday',
            'totalPending', 'pendingLeave', 'pendingOvertime', 'pendingAttendance',
            'lateThisMonth', 'resignedThisMonth', 'contractsEndingSoonCount',
            'contractsEndingSoon', 'recentAttendance', 'recentRequests', 'summary'
        ));
    }

    private function getRecentRequests(int $companyId): Collection
    {
        return collect()
            ->merge($this->recentLeaveRequests($companyId))
            ->merge($this->recentOvertimeRequests($companyId))
            ->merge($this->recentAttendanceRequests($companyId))
            ->merge($this->recentBudgetRequests($companyId))
            ->merge($this->recentTravelReports($companyId))
            ->sortByDesc('created_at')
            ->take(5)
            ->values();
    }

    private function recentLeaveRequests(int $companyId): Collection
    {
        return LeaveRequest::with(['employee:id,full_name,photo,company_id', 'leaveType:id,name'])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($request) => $this->mapRecentRequest(
                $request,
                'Cuti',
                $request->leaveType->name ?? 'Cuti',
                $request->start_date,
                route('admin.leaves.show', $request->id)
            ));
    }

    private function recentOvertimeRequests(int $companyId): Collection
    {
        return OvertimeRequest::with('employee:id,full_name,photo,company_id')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($request) => $this->mapRecentRequest(
                $request,
                'Lembur',
                $request->overtime_type === 'holiday' ? 'Hari libur' : 'Hari kerja',
                $request->date,
                route('admin.approvals.index', ['tab' => 'overtime'])
            ));
    }

    private function recentAttendanceRequests(int $companyId): Collection
    {
        return AttendanceRequest::with('employee:id,full_name,photo,company_id')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($request) => $this->mapRecentRequest(
                $request,
                'Koreksi Presensi',
                $this->attendanceRequestType($request),
                $request->date,
                route('admin.approvals.index', ['tab' => 'attendance'])
            ));
    }

    private function recentBudgetRequests(int $companyId): Collection
    {
        return BudgetRequest::with('employee:id,full_name,photo,company_id')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($request) => $this->mapRecentRequest(
                $request,
                $request->type === 'reimbursement' ? 'Reimbursement' : 'Budget',
                $request->title,
                $request->created_at,
                route('admin.budget-requests.show', $request->id)
            ));
    }

    private function recentTravelReports(int $companyId): Collection
    {
        return TravelReport::with('employee:id,full_name,photo,company_id')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($request) => $this->mapRecentRequest(
                $request,
                'Laporan Perjalanan',
                $request->destination_city,
                $request->departure_date,
                route('admin.travel-reports.show', $request->id)
            ));
    }

    private function mapRecentRequest($request, string $category, string $type, $date, string $url): array
    {
        $employeeName = $request->employee->full_name ?? '-';

        return [
            'employee_name' => $employeeName,
            'employee_initial' => strtoupper(substr($employeeName, 0, 1)),
            'category' => $category,
            'type' => $type,
            'date' => Carbon::parse($date),
            'status' => $request->status,
            'created_at' => Carbon::parse($request->created_at),
            'url' => $url,
        ];
    }

    private function attendanceRequestType(AttendanceRequest $request): string
    {
        if ($request->clock_in && $request->clock_out) {
            return 'Clock in/out';
        }

        return $request->clock_in ? 'Clock in' : 'Clock out';
    }
}
