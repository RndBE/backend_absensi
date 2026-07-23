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
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(AdminDashboardSummary $dashboardSummary)
    {
        $today = Carbon::today();
        $admin = Employee::find(session('admin_id'));
        $dept = \App\Support\AdminDataScope::departmentId($admin); // manager → hanya departemennya
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
        $dashboardDetails = $this->dashboardDetails($admin, $today, $dept);

        $contractsEndingSoon = Employee::where('company_id', $admin->company_id)
            ->when($dept, fn ($q, $d) => $q->where('department_id', $d))
            ->where('is_active', true)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$today->toDateString(), $contractWindowEnd->toDateString()])
            ->orderBy('contract_end_date')
            ->limit(5)
            ->get(['id', 'employee_code', 'full_name', 'position', 'employment_status', 'contract_end_date']);

        // Recent attendance
        $recentAttendance = Attendance::with('employee:id,full_name,photo,department_id', 'employee.department:id,name')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id)
                ->when($dept, fn ($e, $d) => $e->where('department_id', $d)))
            ->where('date', $today)
            ->orderBy('clock_in', 'desc')
            ->limit(10)
            ->get();

        $recentRequests = $this->getRecentRequests($admin->company_id);

        return view('admin.dashboard', compact(
            'totalEmployees', 'presentToday', 'lateToday', 'absentToday',
            'totalPending', 'pendingLeave', 'pendingOvertime', 'pendingAttendance',
            'lateThisMonth', 'resignedThisMonth', 'contractsEndingSoonCount',
            'contractsEndingSoon', 'recentAttendance', 'recentRequests', 'summary',
            'dashboardDetails'
        ));
    }

    private function dashboardDetails(Employee $admin, Carbon $today, ?int $departmentId): array
    {
        $pendingLeave = $this->pendingLeaveDetails($admin, $departmentId, $today);
        $pendingOvertime = $this->pendingOvertimeDetails($admin, $departmentId);
        $pendingAttendance = $this->pendingAttendanceDetails($admin, $departmentId, $today);

        return [
            'total_employees' => [
                'title' => 'Total Karyawan',
                'subtitle' => 'Karyawan aktif dalam scope dashboard.',
                'items' => $this->activeEmployeeDetails($admin, $departmentId)->all(),
            ],
            'present_today' => [
                'title' => 'Hadir Hari Ini',
                'subtitle' => 'Karyawan yang sudah clock in dan tidak terlambat.',
                'items' => $this->attendanceDetails($admin, $departmentId, $today, 'present_today')->all(),
            ],
            'late_today' => [
                'title' => 'Terlambat Hari Ini',
                'subtitle' => 'Karyawan yang tercatat terlambat hari ini.',
                'items' => $this->attendanceDetails($admin, $departmentId, $today, 'late_today')->all(),
            ],
            'absent_today' => [
                'title' => 'Tidak Hadir',
                'subtitle' => 'Karyawan aktif yang belum memiliki clock in hari ini.',
                'items' => $this->absentEmployeeDetails($admin, $departmentId, $today)->all(),
            ],
            'total_pending' => [
                'title' => 'Menunggu Persetujuan',
                'subtitle' => 'Gabungan cuti hari ini, lembur pending, dan koreksi presensi hari ini.',
                'items' => collect($pendingLeave)->merge($pendingOvertime)->merge($pendingAttendance)
                    ->sortByDesc('sort_at')
                    ->values()
                    ->map(fn ($item) => collect($item)->except('sort_at')->all())
                    ->all(),
            ],
            'late_this_month' => [
                'title' => 'Terlambat Bulan Ini',
                'subtitle' => 'Seluruh kejadian terlambat pada bulan berjalan.',
                'items' => $this->attendanceDetails($admin, $departmentId, $today, 'late_month')->all(),
            ],
            'pending_leave' => [
                'title' => 'Cuti Pending',
                'subtitle' => 'Pengajuan cuti pending/diproses yang berlangsung hari ini.',
                'items' => $pendingLeave->map(fn ($item) => collect($item)->except('sort_at')->all())->all(),
            ],
            'pending_overtime' => [
                'title' => 'Lembur Pending',
                'subtitle' => 'Pengajuan lembur yang masih pending/diproses.',
                'items' => $pendingOvertime->map(fn ($item) => collect($item)->except('sort_at')->all())->all(),
            ],
            'pending_attendance' => [
                'title' => 'Presensi Pending',
                'subtitle' => 'Pengajuan koreksi presensi pending/diproses untuk hari ini.',
                'items' => $pendingAttendance->map(fn ($item) => collect($item)->except('sort_at')->all())->all(),
            ],
            'resigned_this_month' => [
                'title' => 'Resign Bulan Ini',
                'subtitle' => 'Karyawan dengan tanggal resign pada bulan berjalan.',
                'items' => $this->resignedEmployeeDetails($admin, $departmentId, $today)->all(),
            ],
        ];
    }

    private function activeEmployeeDetails(Employee $admin, ?int $departmentId): Collection
    {
        return $this->scopedEmployeeQuery($admin, $departmentId)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get($this->employeeColumns())
            ->map(fn (Employee $employee) => $this->employeeDetail($employee, 'Aktif', 'Karyawan aktif'));
    }

    private function absentEmployeeDetails(Employee $admin, ?int $departmentId, Carbon $today): Collection
    {
        $attendedEmployeeIds = $this->attendanceBaseQuery($admin, $departmentId, $today)
            ->whereNotNull('clock_in')
            ->pluck('employee_id');

        return $this->scopedEmployeeQuery($admin, $departmentId)
            ->where('is_active', true)
            ->whereNotIn('id', $attendedEmployeeIds)
            ->orderBy('full_name')
            ->get($this->employeeColumns())
            ->map(fn (Employee $employee) => $this->employeeDetail($employee, 'Belum hadir', 'Belum ada clock in hari ini'));
    }

    private function resignedEmployeeDetails(Employee $admin, ?int $departmentId, Carbon $today): Collection
    {
        return $this->scopedEmployeeQuery($admin, $departmentId)
            ->whereBetween('resign_date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
            ->orderBy('resign_date')
            ->get($this->employeeColumns())
            ->map(fn (Employee $employee) => $this->employeeDetail(
                $employee,
                'Resign',
                'Tanggal resign '.$this->formatDate($employee->resign_date)
            ));
    }

    private function attendanceDetails(Employee $admin, ?int $departmentId, Carbon $today, string $type): Collection
    {
        $query = $this->attendanceBaseQuery($admin, $departmentId, $today, $type !== 'late_month')
            ->with($this->employeeEagerLoad());

        if ($type === 'present_today') {
            $query->whereNotNull('clock_in')->where('is_late', false);
        } elseif ($type === 'late_today') {
            $query->where('is_late', true);
        } elseif ($type === 'late_month') {
            $query->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
                ->where('is_late', true);
        }

        return $query
            ->orderByDesc('date')
            ->orderBy('clock_in')
            ->get()
            ->map(function (Attendance $attendance) use ($type) {
                $date = $attendance->date ? $this->formatDate($attendance->date) : '-';
                $time = 'Masuk '.($attendance->clock_in ?: '-').' | Pulang '.($attendance->clock_out ?: '-');
                $detail = $type === 'late_month' ? "{$date} - {$time}" : $time;

                return $this->employeeDetail(
                    $attendance->employee,
                    $attendance->is_late ? 'Terlambat' : 'Hadir',
                    $detail
                );
            });
    }

    private function pendingLeaveDetails(Employee $admin, ?int $departmentId, Carbon $today): Collection
    {
        return LeaveRequest::with(array_merge($this->employeeEagerLoad(), ['leaveType:id,name']))
            ->whereHas('employee', fn ($q) => $this->applyEmployeeScope($q, $admin, $departmentId))
            ->whereIn('status', ['pending', 'in_review'])
            ->when(
                Schema::hasColumn('leave_requests', 'start_date') && Schema::hasColumn('leave_requests', 'end_date'),
                fn ($q) => $q->whereDate('start_date', '<=', $today->toDateString())
                    ->whereDate('end_date', '>=', $today->toDateString())
            )
            ->latest()
            ->get()
            ->map(fn (LeaveRequest $request) => $this->requestDetail(
                $request->employee,
                'Cuti',
                ($request->leaveType->name ?? 'Cuti').' | '.$this->formatDate($request->start_date).' - '.$this->formatDate($request->end_date),
                route('admin.approvals.index', ['tab' => 'leave']),
                $request->created_at
            ));
    }

    private function pendingOvertimeDetails(Employee $admin, ?int $departmentId): Collection
    {
        return OvertimeRequest::with($this->employeeEagerLoad())
            ->whereHas('employee', fn ($q) => $this->applyEmployeeScope($q, $admin, $departmentId))
            ->whereIn('status', ['pending', 'in_review'])
            ->latest()
            ->get()
            ->map(fn (OvertimeRequest $request) => $this->requestDetail(
                $request->employee,
                'Lembur',
                $this->formatDate($request->date).' | '.$request->total_duration_formatted,
                route('admin.approvals.index', ['tab' => 'overtime']),
                $request->created_at
            ));
    }

    private function pendingAttendanceDetails(Employee $admin, ?int $departmentId, Carbon $today): Collection
    {
        return AttendanceRequest::with($this->employeeEagerLoad())
            ->whereHas('employee', fn ($q) => $this->applyEmployeeScope($q, $admin, $departmentId))
            ->whereIn('status', ['pending', 'in_review'])
            ->when(
                Schema::hasColumn('attendance_requests', 'date'),
                fn ($q) => $q->whereDate('date', $today->toDateString())
            )
            ->latest()
            ->get()
            ->map(fn (AttendanceRequest $request) => $this->requestDetail(
                $request->employee,
                'Koreksi Presensi',
                $this->formatDate($request->date).' | '.$this->attendanceRequestType($request),
                route('admin.approvals.index', ['tab' => 'attendance']),
                $request->created_at
            ));
    }

    private function requestDetail(?Employee $employee, string $badge, string $detail, string $url, $createdAt): array
    {
        return array_merge($this->employeeDetail($employee, $badge, $detail, $url), [
            'sort_at' => Carbon::parse($createdAt)->timestamp,
        ]);
    }

    private function employeeDetail(?Employee $employee, string $badge, string $detail, ?string $url = null): array
    {
        $name = $employee?->full_name ?: '-';

        return [
            'name' => $name,
            'initial' => strtoupper(substr($name, 0, 1)),
            'code' => $employee?->employee_code ?: '-',
            'department' => $employee?->relationLoaded('department') ? ($employee->department->name ?? '-') : '-',
            'position' => $employee?->position ?: '-',
            'badge' => $badge,
            'detail' => $detail,
            'url' => $url ?: ($employee ? route('admin.employees.show', $employee->id) : null),
        ];
    }

    private function scopedEmployeeQuery(Employee $admin, ?int $departmentId)
    {
        $query = Employee::query();

        $this->applyEmployeeScope($query, $admin, $departmentId);

        if (Schema::hasTable('departments') && Schema::hasColumn('employees', 'department_id')) {
            $query->with('department:id,name');
        }

        return $query;
    }

    private function attendanceBaseQuery(Employee $admin, ?int $departmentId, Carbon $today, bool $onlyToday = true)
    {
        $query = Attendance::whereHas('employee', fn ($q) => $this->applyEmployeeScope($q, $admin, $departmentId));

        if ($onlyToday) {
            $query->where('date', $today->toDateString());
        }

        if (Schema::hasColumn('attendances', 'review_status')) {
            $query->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', 'approved'));
        }

        return $query;
    }

    private function applyEmployeeScope($query, Employee $admin, ?int $departmentId): void
    {
        $query->where('company_id', $admin->company_id);

        if ($departmentId && Schema::hasColumn('employees', 'department_id')) {
            $query->where('department_id', $departmentId);
        }
    }

    private function employeeEagerLoad(): array
    {
        $columns = implode(',', $this->employeeColumns());

        return [
            'employee:'.$columns,
            ...(
                Schema::hasTable('departments') && Schema::hasColumn('employees', 'department_id')
                    ? ['employee.department:id,name']
                    : []
            ),
        ];
    }

    private function employeeColumns(): array
    {
        return collect([
            'id',
            'employee_code',
            'company_id',
            'department_id',
            'full_name',
            'position',
            'employment_status',
            'join_date',
            'resign_date',
            'contract_end_date',
        ])->filter(fn ($column) => Schema::hasColumn('employees', $column))->values()->all();
    }

    private function formatDate($date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '-';
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
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId)
                ->when(\App\Support\AdminDataScope::departmentId(\App\Models\Employee::find(session('admin_id'))), fn ($e, $d) => $e->where('department_id', $d)))
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
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId)
                ->when(\App\Support\AdminDataScope::departmentId(\App\Models\Employee::find(session('admin_id'))), fn ($e, $d) => $e->where('department_id', $d)))
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
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId)
                ->when(\App\Support\AdminDataScope::departmentId(\App\Models\Employee::find(session('admin_id'))), fn ($e, $d) => $e->where('department_id', $d)))
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
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId)
                ->when(\App\Support\AdminDataScope::departmentId(\App\Models\Employee::find(session('admin_id'))), fn ($e, $d) => $e->where('department_id', $d)))
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
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId)
                ->when(\App\Support\AdminDataScope::departmentId(\App\Models\Employee::find(session('admin_id'))), fn ($e, $d) => $e->where('department_id', $d)))
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
