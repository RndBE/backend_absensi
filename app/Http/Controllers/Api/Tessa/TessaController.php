<?php

namespace App\Http\Controllers\Api\Tessa;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Lpj;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API untuk AI kantor "Tessa".
 *
 * Semua endpoint di sini dilindungi middleware `tessa.api` (API key statis).
 * Tessa TIDAK punya akses payroll/slip gaji, dan field gaji/PII sensitif tidak
 * pernah dikembalikan.
 */
class TessaController extends Controller
{
    /** Status pengajuan yang valid untuk filter. */
    private const STATUSES = ['pending', 'in_review', 'approved', 'rejected'];

    // =====================================================================
    // Meta
    // =====================================================================

    public function ping(Request $request)
    {
        return response()->json([
            'success' => true,
            'service' => 'Tessa API',
            'company_scope' => $this->companyId($request) ?? 'all',
            'capabilities' => [
                'read' => [
                    'employees', 'attendance', 'attendance-recap',
                    'leaves', 'overtimes', 'attendance-requests',
                    'budget-requests', 'travel-reports', 'lpj',
                    'approvals-summary', 'company', 'announcements',
                ],
                'actions' => ['send-notification'],
                'forbidden' => ['payroll', 'payslip', 'salary'],
            ],
        ]);
    }

    // =====================================================================
    // Karyawan & profil (tanpa gaji/PII sensitif)
    // =====================================================================

    public function employees(Request $request)
    {
        $query = Employee::query()
            ->with('department:id,name')
            ->when($this->companyId($request), fn ($q, $id) => $q->where('company_id', $id))
            ->when($request->boolean('only_active', true), fn ($q) => $q->where('is_active', true))
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name');

        return $this->respondPaginated($query, $request, fn (Employee $e) => $this->employeeBrief($e));
    }

    public function employee(Request $request, $id)
    {
        $employee = Employee::query()
            ->with(['department:id,name', 'company:id,name', 'manager:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->where('company_id', $cid))
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->employeeBrief($employee), [
                'job_level' => $employee->job_level,
                'employment_status' => $employee->employment_status,
                'join_date' => $employee->join_date?->format('Y-m-d'),
                'masa_kerja' => $employee->masa_kerja,
                'company' => $employee->company?->name,
                'manager' => $employee->manager?->full_name,
                'gender' => $employee->gender,
            ]),
        ]);
    }

    // =====================================================================
    // Presensi & jadwal
    // =====================================================================

    public function attendance(Request $request)
    {
        [$from, $to] = $this->dateRange($request, 'today');

        $query = Attendance::query()
            ->with('employee:id,full_name,employee_code,department_id', 'employee.department:id,name')
            ->whereBetween('date', [$from, $to])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('employee_id'), fn ($q, $eid) => $q->where('employee_id', $eid))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('late_only'), fn ($q) => $q->where('is_late', true))
            ->orderByDesc('date')->orderBy('clock_in');

        return $this->respondPaginated($query, $request, fn (Attendance $a) => [
            'id' => $a->id,
            'date' => $a->date?->format('Y-m-d'),
            'employee' => $a->employee?->full_name,
            'employee_id' => $a->employee_id,
            'department' => $a->employee?->department?->name,
            'clock_in' => $a->clock_in,
            'clock_out' => $a->clock_out,
            'status' => $a->status,
            'is_late' => (bool) $a->is_late,
            'is_remote' => (bool) $a->is_remote,
        ]);
    }

    public function attendanceRecap(Request $request)
    {
        $date = $request->query('date') ? Carbon::parse($request->query('date'))->toDateString() : today()->toDateString();
        $companyId = $this->companyId($request);

        $activeEmployees = Employee::query()
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $base = Attendance::query()
            ->whereDate('date', $date)
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)));

        $present = (clone $base)->whereNotNull('clock_in')->distinct('employee_id')->count('employee_id');
        $late = (clone $base)->where('is_late', true)->count();
        $remote = (clone $base)->where('is_remote', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'active_employees' => $activeEmployees,
                'present' => $present,
                'absent' => max($activeEmployees - $present, 0),
                'late' => $late,
                'remote' => $remote,
            ],
        ]);
    }

    // =====================================================================
    // Cuti, lembur & pengajuan
    // =====================================================================

    public function leaves(Request $request)
    {
        $query = LeaveRequest::query()
            ->with(['employee:id,full_name', 'leaveType:id,name', 'approvalLogs.approver:id,full_name', 'approvalLogs.actedBy:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('employee_id'), fn ($q, $eid) => $q->where('employee_id', $eid))
            ->when($this->status($request), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        return $this->respondPaginated($query, $request, fn (LeaveRequest $r) => [
            'id' => $r->id,
            'employee' => $r->employee?->full_name,
            'type' => $r->leaveType?->name,
            'start_date' => $r->start_date?->format('Y-m-d'),
            'end_date' => $r->end_date?->format('Y-m-d'),
            'total_days' => $r->total_days,
            'reason' => $r->reason,
            'status' => $r->status,
            'current_step' => $r->current_step,
            'approvals' => $this->approvals($r),
        ]);
    }

    public function overtimes(Request $request)
    {
        $query = OvertimeRequest::query()
            ->with(['employee:id,full_name', 'approvalLogs.approver:id,full_name', 'approvalLogs.actedBy:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('employee_id'), fn ($q, $eid) => $q->where('employee_id', $eid))
            ->when($this->status($request), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        return $this->respondPaginated($query, $request, fn (OvertimeRequest $r) => [
            'id' => $r->id,
            'employee' => $r->employee?->full_name,
            'date' => $r->date?->format('Y-m-d'),
            'overtime_type' => $r->overtime_type,
            'total_duration' => $r->total_duration,
            'reason' => $r->reason,
            'status' => $r->status,
            'current_step' => $r->current_step,
            'approvals' => $this->approvals($r),
        ]);
    }

    public function attendanceRequests(Request $request)
    {
        $query = AttendanceRequest::query()
            ->with(['employee:id,full_name', 'approvalLogs.approver:id,full_name', 'approvalLogs.actedBy:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('employee_id'), fn ($q, $eid) => $q->where('employee_id', $eid))
            ->when($this->status($request), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        return $this->respondPaginated($query, $request, fn (AttendanceRequest $r) => [
            'id' => $r->id,
            'employee' => $r->employee?->full_name,
            'date' => $r->date?->format('Y-m-d'),
            'clock_in' => $r->clock_in,
            'clock_out' => $r->clock_out,
            'reason' => $r->reason,
            'status' => $r->status,
            'current_step' => $r->current_step,
            'approvals' => $this->approvals($r),
        ]);
    }

    public function budgetRequests(Request $request)
    {
        $query = BudgetRequest::query()
            ->with(['employee:id,full_name', 'approvalLogs.approver:id,full_name', 'approvalLogs.actedBy:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('employee_id'), fn ($q, $eid) => $q->where('employee_id', $eid))
            ->when($this->status($request), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        return $this->respondPaginated($query, $request, fn (BudgetRequest $r) => [
            'id' => $r->id,
            'employee' => $r->employee?->full_name,
            'type' => $r->type,
            'title' => $r->title,
            'total_amount' => $r->total_amount,
            'status' => $r->status,
            'current_step' => $r->current_step,
            'approvals' => $this->approvals($r),
        ]);
    }

    public function travelReports(Request $request)
    {
        $query = TravelReport::query()
            ->with(['employee:id,full_name', 'approvalLogs.approver:id,full_name', 'approvalLogs.actedBy:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('employee_id'), fn ($q, $eid) => $q->where('employee_id', $eid))
            ->when($this->status($request), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        return $this->respondPaginated($query, $request, fn (TravelReport $r) => [
            'id' => $r->id,
            'employee' => $r->employee?->full_name,
            'destination_city' => $r->destination_city,
            'departure_date' => $r->departure_date?->format('Y-m-d'),
            'return_date' => $r->return_date?->format('Y-m-d'),
            'purpose' => $r->purpose,
            'status' => $r->status,
            'current_step' => $r->current_step,
            'approvals' => $this->approvals($r),
        ]);
    }

    public function lpj(Request $request)
    {
        $query = Lpj::query()
            ->with(['employee:id,full_name', 'approvalLogs.approver:id,full_name', 'approvalLogs.actedBy:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('employee_id'), fn ($q, $eid) => $q->where('employee_id', $eid))
            ->when($this->status($request), fn ($q, $s) => $q->where('status', $s))
            ->latest();

        return $this->respondPaginated($query, $request, fn (Lpj $r) => [
            'id' => $r->id,
            'employee' => $r->employee?->full_name,
            'nomor_lpj' => $r->nomor_lpj,
            'total_anggaran' => $r->total_anggaran,
            'total_realisasi' => $r->total_realisasi,
            'sisa' => $r->sisa,
            'status' => $r->status,
            'current_step' => $r->current_step,
            'approvals' => $this->approvals($r),
        ]);
    }

    public function approvalsSummary(Request $request)
    {
        $companyId = $this->companyId($request);
        $scoped = fn ($class) => $class::query()
            ->whereIn('status', ['pending', 'in_review'])
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => [
                    'leaves' => $scoped(LeaveRequest::class),
                    'overtimes' => $scoped(OvertimeRequest::class),
                    'attendance_requests' => $scoped(AttendanceRequest::class),
                    'budget_requests' => $scoped(BudgetRequest::class),
                    'travel_reports' => $scoped(TravelReport::class),
                    'lpj' => $scoped(Lpj::class),
                ],
            ],
        ]);
    }

    // =====================================================================
    // Perusahaan & pengumuman
    // =====================================================================

    public function company(Request $request)
    {
        $companyId = $this->companyId($request);
        $companies = Company::query()
            ->when($companyId, fn ($q) => $q->where('id', $companyId))
            ->get(['id', 'name', 'address', 'phone', 'email']);

        return response()->json(['success' => true, 'data' => $companies]);
    }

    public function announcements(Request $request)
    {
        $query = Notification::query()
            ->with('employee:id,full_name')
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest();

        return $this->respondPaginated($query, $request, fn (Notification $n) => [
            'id' => $n->id,
            'employee' => $n->employee?->full_name,
            'title' => $n->title,
            'message' => $n->message,
            'type' => $n->type,
            'is_read' => (bool) $n->is_read,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);
    }

    // =====================================================================
    // Aksi: kirim notifikasi / pengingat
    // =====================================================================

    public function sendNotification(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:1000',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'all' => 'nullable|boolean',
            'push' => 'nullable|boolean',
        ]);

        $companyId = $this->companyId($request);

        $targets = Employee::query()
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($validated['employee_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->when($validated['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->get();

        // Wajib menentukan target: salah satu dari employee_id / department_id / all=true.
        if (empty($validated['employee_id']) && empty($validated['department_id']) && ! ($validated['all'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Tentukan target: employee_id, department_id, atau all=true.',
            ], 422);
        }

        if ($targets->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada karyawan yang cocok.'], 404);
        }

        $push = $validated['push'] ?? false;
        $sent = 0;

        foreach ($targets as $employee) {
            Notification::create([
                'employee_id' => $employee->id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => 'info',
            ]);

            if ($push) {
                FcmService::sendToEmployee($employee, $validated['title'], $validated['message'], [
                    'type' => 'info',
                    'source' => 'tessa',
                ]);
            }

            $sent++;
        }

        return response()->json([
            'success' => true,
            'message' => "Notifikasi terkirim ke {$sent} karyawan.",
            'sent' => $sent,
        ]);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function companyId(Request $request): ?int
    {
        $id = $request->attributes->get('tessa_company_id');

        return $id ? (int) $id : null;
    }

    private function status(Request $request): ?string
    {
        $status = $request->query('status');

        return in_array($status, self::STATUSES, true) ? $status : null;
    }

    /** @return array{0: string, 1: string} [from, to] tanggal Y-m-d */
    private function dateRange(Request $request, string $default = 'today'): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if (! $from && ! $to) {
            $today = today()->toDateString();

            return [$today, $today];
        }

        $from = $from ? Carbon::parse($from)->toDateString() : Carbon::parse($to)->toDateString();
        $to = $to ? Carbon::parse($to)->toDateString() : $from;

        return [$from, $to];
    }

    private function employeeBrief(Employee $e): array
    {
        return [
            'id' => $e->id,
            'employee_code' => $e->employee_code,
            'full_name' => $e->full_name,
            'email' => $e->email,
            'phone' => $e->phone,
            'position' => $e->position,
            'department' => $e->department?->name,
            'is_active' => (bool) $e->is_active,
            'photo' => $e->photo ? asset('storage/'.$e->photo) : null,
        ];
    }

    /** Ringkasan log approval termasuk atribusi "via" superadmin. */
    private function approvals($model): array
    {
        return $model->approvalLogs
            ->sortBy('step_order')
            ->map(fn ($log) => [
                'step' => $log->step_order,
                'action' => $log->action,
                'approver' => $log->approver?->full_name,
                'via' => $log->via_label,
                'notes' => $log->notes,
                'at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function respondPaginated($query, Request $request, callable $map, int $default = 50, int $max = 200)
    {
        $perPage = (int) $request->query('limit', $default);
        $perPage = $perPage > 0 ? min($perPage, $max) : $default;

        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
