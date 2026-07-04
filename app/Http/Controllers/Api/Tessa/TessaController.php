<?php

namespace App\Http\Controllers\Api\Tessa;

use App\Http\Controllers\Api\Tessa\Concerns\EnforcesHrisRole;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Lpj;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use App\Models\TravelReport;
use App\Services\FcmService;
use App\Services\TessaScheduleImportParser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * API untuk AI kantor "Tessa".
 *
 * Semua endpoint di sini dilindungi middleware `tessa.api` (API key statis).
 * Tessa TIDAK punya akses payroll/slip gaji, dan field gaji/PII sensitif tidak
 * pernah dikembalikan.
 */
class TessaController extends Controller
{
    use EnforcesHrisRole;

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
                    'leave-balances', 'schedules',
                    'leaves', 'overtimes', 'attendance-requests',
                    'budget-requests', 'travel-reports', 'lpj',
                    'approvals-summary', 'next-approver', 'company', 'announcements', 'shifts',
                ],
                'actions' => ['send-notification', 'assign-schedule', 'import-schedule', 'edit-pending-request'],
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
            // Non-admin hanya boleh melihat dirinya sendiri.
            ->when(! $this->actorIsAdmin(), fn ($q) => $q->whereKey($this->actor()?->id))
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
        // Non-admin hanya boleh melihat profil dirinya sendiri.
        if (! $this->actorIsAdmin() && (int) $id !== (int) $this->actor()?->id) {
            return response()->json(['success' => false, 'message' => 'Anda hanya bisa melihat data diri sendiri.'], 403);
        }

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
            ->when($this->scopedEmployeeId($request), fn ($q, $eid) => $q->where('employee_id', $eid))
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
        $this->requirePermission('attendance.view'); // rekap se-perusahaan: admin saja

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

    public function schedules(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'employee_id' => 'nullable|integer',
        ]);

        [$from, $to] = $this->dateRange($request);
        $employeeId = $this->scopedEmployeeId($request);
        $companyId = $this->companyId($request);

        $items = ScheduleAssignment::query()
            ->with(['employee:id,full_name,employee_code,company_id', 'shift:id,name,start_time,end_time,is_off,is_overnight'])
            ->whereBetween('date', [$from, $to])
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        return response()->json([
            'success' => true,
            'from' => $from,
            'to' => $to,
            'count' => $items->count(),
            'data' => $items->map(fn (ScheduleAssignment $assignment) => [
                'id' => $assignment->id,
                'date' => $assignment->date?->format('Y-m-d'),
                'employee' => [
                    'id' => $assignment->employee?->id,
                    'name' => $assignment->employee?->full_name,
                    'employee_code' => $assignment->employee?->employee_code,
                ],
                'shift' => [
                    'id' => $assignment->shift?->id,
                    'name' => $assignment->shift?->name,
                    'start_time' => $assignment->shift?->start_time,
                    'end_time' => $assignment->shift?->end_time,
                    'is_off' => (bool) $assignment->shift?->is_off,
                    'is_overnight' => (bool) $assignment->shift?->is_overnight,
                ],
                'notes' => $assignment->notes,
            ])->values(),
        ]);
    }

    // =====================================================================
    // Cuti, lembur & pengajuan
    // =====================================================================

    public function leaveBalances(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|integer',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $year = (int) $request->query('year', now()->year);
        $employeeId = $this->scopedEmployeeId($request);
        $companyId = $this->companyId($request);

        $items = LeaveBalance::query()
            ->with(['employee:id,full_name,employee_code,company_id', 'leaveType:id,name'])
            ->where('year', $year)
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderBy('employee_id')
            ->orderBy('leave_type_id')
            ->get();

        return response()->json([
            'success' => true,
            'year' => $year,
            'count' => $items->count(),
            'data' => $items->map(fn (LeaveBalance $balance) => [
                'employee' => [
                    'id' => $balance->employee?->id,
                    'name' => $balance->employee?->full_name,
                    'employee_code' => $balance->employee?->employee_code,
                ],
                'leave_type_id' => $balance->leave_type_id,
                'leave_type' => $balance->leaveType?->name,
                'total_days' => (float) $balance->total_days,
                'carry_over' => (float) $balance->carry_over,
                'used_days' => (float) $balance->used_days,
                'remaining_days' => (float) $balance->remaining_days,
            ])->values(),
        ]);
    }

    public function leaves(Request $request)
    {
        $query = LeaveRequest::query()
            ->with(['employee:id,full_name', 'leaveType:id,name', 'approvalLogs.approver:id,full_name', 'approvalLogs.actedBy:id,full_name'])
            ->when($this->companyId($request), fn ($q, $cid) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $cid)))
            ->when($this->scopedEmployeeId($request), fn ($q, $eid) => $q->where('employee_id', $eid))
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
            ->when($this->scopedEmployeeId($request), fn ($q, $eid) => $q->where('employee_id', $eid))
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
            ->when($this->scopedEmployeeId($request), fn ($q, $eid) => $q->where('employee_id', $eid))
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
            ->when($this->scopedEmployeeId($request), fn ($q, $eid) => $q->where('employee_id', $eid))
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
            ->when($this->scopedEmployeeId($request), fn ($q, $eid) => $q->where('employee_id', $eid))
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
            ->when($this->scopedEmployeeId($request), fn ($q, $eid) => $q->where('employee_id', $eid))
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
        $this->requirePermission('approvals.view'); // ringkasan pending se-perusahaan: admin saja

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

    public function nextApprover(Request $request, string $type, $id)
    {
        $modelClass = $this->approvalModel($type);
        if (! $modelClass) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid.'], 404);
        }

        $item = $modelClass::with('employee:id,full_name,company_id')->find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        if (! $this->actorIsAdmin() && (int) $item->employee_id !== (int) $this->actor()?->id) {
            return response()->json(['success' => false, 'message' => 'Anda hanya bisa melihat approval pengajuan sendiri.'], 403);
        }

        $companyId = $this->companyId($request);
        if ($companyId && $item->employee?->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Pengajuan di luar cakupan perusahaan Tessa.'], 403);
        }

        $status = (string) $item->status;
        $currentStep = (int) ($item->current_step ?? 1);
        $isFinal = in_array($status, ['approved', 'rejected'], true);
        $approver = $isFinal ? null : EmployeeApprover::getApproverAt($item->employee_id, $this->requestTypeFor($type), $currentStep);

        return response()->json([
            'success' => true,
            'type' => $type,
            'id' => $item->id,
            'status' => $status,
            'employee' => $item->employee?->full_name,
            'current_step' => $currentStep,
            'is_final' => $isFinal,
            'approver' => $approver ? [
                'id' => $approver->id,
                'name' => $approver->full_name,
                'phone' => $approver->phone,
            ] : null,
            'message' => $approver
                ? "Pengajuan Anda sedang menunggu persetujuan {$approver->full_name} (step {$currentStep})."
                : ($isFinal ? "Pengajuan sudah final dengan status {$status}." : 'Approver step aktif belum dikonfigurasi.'),
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
            // Non-admin hanya melihat notifikasi miliknya.
            ->when(! $this->actorIsAdmin(), fn ($q) => $q->where('employee_id', $this->actor()?->id))
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
        $this->requirePermission('company.manage'); // broadcast notifikasi: admin saja

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
    // Penjadwalan (shift) — Tessa mengisi jadwal kerja per tanggal
    // =====================================================================

    /** Daftar shift yang tersedia (agar Tessa tahu nama shift yang valid). */
    public function shifts(Request $request)
    {
        $companyId = $this->companyId($request);

        $shifts = Shift::query()
            ->with('company:id,name')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('company_id')->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shifts->map(fn (Shift $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'company' => $s->company?->name,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'is_off' => (bool) $s->is_off,
                'is_overnight' => (bool) $s->is_overnight,
            ])->values(),
        ]);
    }

    /**
     * Aksi: isi jadwal kerja (shift) karyawan per tanggal — mode bulk per baris.
     *
     * Body: { "assignments": [ {employee|employee_code|employee_id, date, shift|shift_id, notes?}, ... ], "dry_run": false }
     *
     * Setiap baris diproses independen; hasil per baris dikembalikan agar Tessa
     * bisa melapor mana yang berhasil/gagal. Memakai updateOrCreate (unique employee+date),
     * jadi mengisi ulang tanggal yang sama akan menimpa shift sebelumnya.
     *
     * Mode preview: kirim "dry_run": true untuk MEMVALIDASI tanpa menyimpan apa pun —
     * API mengembalikan apa yang AKAN terjadi (would_create/would_update + shift saat ini),
     * supaya bisa ditinjau dulu sebelum benar-benar disimpan.
     */
    public function assignSchedules(Request $request)
    {
        $this->requirePermission('schedule.manage'); // isi jadwal karyawan: admin saja

        $validated = $request->validate([
            'assignments' => 'required|array|min:1|max:500',
            'assignments.*.date' => 'required|date',
            'assignments.*.employee_id' => 'nullable|integer',
            'assignments.*.employee_code' => 'nullable|string',
            'assignments.*.employee' => 'nullable|string',
            'assignments.*.shift_id' => 'nullable|integer',
            'assignments.*.shift' => 'nullable|string',
            'assignments.*.notes' => 'nullable|string|max:500',
            'dry_run' => 'nullable|boolean',
        ]);

        $companyId = $this->companyId($request);
        $dryRun = $request->boolean('dry_run');

        return $this->scheduleAssignmentResponse($validated['assignments'], $companyId, $dryRun);
    }

    public function importSchedules(Request $request, TessaScheduleImportParser $parser)
    {
        $this->requirePermission('schedule.manage');

        $request->validate([
            'file' => 'required|file|max:5120',
            'dry_run' => 'nullable|boolean',
        ]);

        try {
            $assignments = $parser->parse($request->file('file'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($assignments === []) {
            return response()->json([
                'success' => false,
                'message' => 'File jadwal tidak berisi baris yang bisa diproses. Pastikan header berisi employee_code/nama, date/tanggal, dan shift.',
                'parsed' => 0,
            ], 422);
        }

        return $this->scheduleAssignmentResponse(
            $assignments,
            $this->companyId($request),
            $request->boolean('dry_run'),
            [
                'parsed' => count($assignments),
                'source' => [
                    'filename' => $request->file('file')->getClientOriginalName(),
                    'extension' => strtolower($request->file('file')->getClientOriginalExtension()),
                ],
            ]
        );
    }

    private function scheduleAssignmentResponse(array $assignments, ?int $companyId, bool $dryRun, array $extra = [])
    {
        $results = [];
        $ok = 0;

        foreach ($assignments as $i => $row) {
            $result = $this->applyScheduleRow((array) $row, $companyId, $dryRun);
            if ($result['success']) {
                $ok++;
            }
            $results[] = ['index' => $i] + $result;
        }

        $total = count($results);

        return response()->json($extra + [
            'success' => $ok > 0,
            'dry_run' => $dryRun,
            'message' => $dryRun
                ? "PREVIEW: {$ok} dari {$total} baris valid (belum disimpan). Kirim ulang tanpa dry_run untuk menyimpan."
                : "{$ok} dari {$total} jadwal tersimpan.",
            'valid' => $ok,
            'failed' => $total - $ok,
            'results' => $results,
        ]);
    }

    /** Proses satu baris jadwal: resolve karyawan + shift, lalu simpan (atau preview bila $dryRun). */
    private function applyScheduleRow(array $row, ?int $companyId, bool $dryRun = false): array
    {
        if (empty($row['date'])) {
            return ['success' => false, 'error' => 'Tanggal jadwal wajib diisi.'];
        }

        if (empty($row['shift_id']) && empty($row['shift'])) {
            return ['success' => false, 'error' => 'Wajib isi shift (nama) atau shift_id.'];
        }

        // 1. Resolve karyawan (scoped ke perusahaan Tessa bila diset).
        $base = Employee::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        if (! empty($row['employee_id'])) {
            $employee = (clone $base)->find($row['employee_id']);
        } elseif (! empty($row['employee_code'])) {
            $employee = (clone $base)->where('employee_code', $row['employee_code'])->first();
        } elseif (! empty($row['employee'])) {
            $matches = (clone $base)->where('full_name', 'like', '%'.$row['employee'].'%')->limit(2)->get();
            if ($matches->count() > 1) {
                return ['success' => false, 'error' => "Nama '{$row['employee']}' cocok ke lebih dari satu karyawan; gunakan employee_code atau employee_id."];
            }
            $employee = $matches->first();
        } else {
            return ['success' => false, 'error' => 'Wajib isi salah satu: employee_id, employee_code, atau employee (nama).'];
        }

        if (! $employee) {
            return ['success' => false, 'error' => 'Karyawan tidak ditemukan.'];
        }

        // 2. Resolve shift DALAM perusahaan karyawan (shift bersifat per-perusahaan).
        $shiftBase = Shift::query()->where('company_id', $employee->company_id);

        if (! empty($row['shift_id'])) {
            $shift = (clone $shiftBase)->find($row['shift_id']);
        } elseif (! empty($row['shift'])) {
            // Cocokkan nama persis dulu; bila ada >1 shift dengan nama sama, minta shift_id.
            $exact = (clone $shiftBase)->whereRaw('LOWER(name) = ?', [mb_strtolower($row['shift'])])->get();
            if ($exact->count() > 1) {
                return ['success' => false, 'error' => "Shift '{$row['shift']}' ada lebih dari satu (id: ".$exact->pluck('id')->implode(', ')."); gunakan shift_id."];
            }
            $shift = $exact->first();
            if (! $shift) {
                $cands = (clone $shiftBase)->where('name', 'like', '%'.$row['shift'].'%')->limit(2)->get();
                if ($cands->count() > 1) {
                    return ['success' => false, 'error' => "Shift '{$row['shift']}' ambigu untuk perusahaan karyawan; gunakan shift_id."];
                }
                $shift = $cands->first();
            }
        } else {
            return ['success' => false, 'error' => 'Wajib isi shift (nama) atau shift_id.'];
        }

        if (! $shift) {
            return ['success' => false, 'error' => "Shift tidak ditemukan untuk perusahaan {$employee->full_name}."];
        }

        // 3. Simpan (timpa bila tanggal yang sama sudah ada).
        $date = Carbon::parse($row['date'])->toDateString();

        $existing = ScheduleAssignment::with('shift:id,name')
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        // Mode preview: tampilkan apa yang AKAN terjadi tanpa menyimpan.
        if ($dryRun) {
            return [
                'success' => true,
                'preview' => true,
                'employee' => $employee->full_name,
                'date' => $date,
                'shift' => $shift->name,
                'action' => $existing ? 'would_update' : 'would_create',
                'current_shift' => $existing?->shift?->name,
            ];
        }

        $assignment = ScheduleAssignment::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            ['shift_id' => $shift->id, 'notes' => $row['notes'] ?? null],
        );

        return [
            'success' => true,
            'employee' => $employee->full_name,
            'date' => $date,
            'shift' => $shift->name,
            'action' => $assignment->wasRecentlyCreated ? 'created' : 'updated',
            'current_shift' => $existing?->shift?->name,
        ];
    }

    // =====================================================================
    // Helpers
    // =====================================================================

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

    private function approvalModel(string $type): ?string
    {
        return [
            'leave' => LeaveRequest::class,
            'overtime' => OvertimeRequest::class,
            'attendance' => AttendanceRequest::class,
            'budget' => BudgetRequest::class,
            'travel_report' => TravelReport::class,
        ][$type] ?? null;
    }

    private function requestTypeFor(string $type): string
    {
        return $type === 'travel_report' ? 'travel_report' : $type;
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
