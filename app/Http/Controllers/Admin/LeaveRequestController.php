<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Services\FcmService;
use App\Support\LeaveQuota;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $status = $request->status ?? 'all';

        $query = LeaveRequest::with([
            'employee:id,full_name,photo,department_id,job_level',
            'employee.department:id,name',
            'leaveType',
            'delegate:id,full_name',
            'approvalLogs.approver:id,full_name',
        ]);

        // Filter by employee's company (manager: hanya departemennya).
        $query->whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id)
            ->when(\App\Support\AdminDataScope::departmentId($admin), fn($e, $d) => $e->where('department_id', $d)));

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        $leaves = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $employees = Employee::where('company_id', $admin->company_id)
            ->when(\App\Support\AdminDataScope::departmentId($admin), fn($q, $d) => $q->where('department_id', $d))
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return view('admin.leaves.index', compact('leaves', 'status', 'employees', 'admin'));
    }

    public function create()
    {
        $admin = Employee::with('department:id,name')->find(session('admin_id'));
        $leaveTypes = LeaveType::all();

        // Colleagues for delegate dropdown
        $colleagues = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('id', '!=', $admin->id)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        // Super admin can create for others
        $employees = null;
        if ($admin->role === 'admin') {
            $employees = Employee::where('company_id', $admin->company_id)
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name']);
        }

        return view('admin.leaves.create', compact('leaveTypes', 'admin', 'colleagues', 'employees'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $isAdmin = $admin->role === 'admin';

        $rules = [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'delegate_to' => 'nullable|exists:employees,id',
        ];

        // Admin can pick employee
        if ($isAdmin) {
            $rules['employee_id'] = 'required|exists:employees,id';
        }

        $request->validate($rules);

        // Determine the actual requester
        $employeeId = $isAdmin ? $request->employee_id : $admin->id;
        $employee = Employee::find($employeeId);

        // Calculate total days (exclude weekends)
        $start = \Carbon\Carbon::parse($request->start_date);
        $end = \Carbon\Carbon::parse($request->end_date);
        $totalDays = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if (!$current->isWeekend()) {
                $totalDays++;
            }
            $current->addDay();
        }

        // Cuti Tahunan memblokir bila saldo kurang; WFH tetap boleh walau saldo 0.
        $leaveType = LeaveType::find($request->leave_type_id);

        if (LeaveQuota::blocksWhenInsufficient($leaveType)) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('year', now()->year)
                ->first();

            if ($balance && $balance->remaining_days < $totalDays) {
                return back()->withInput()->with('error', "Sisa cuti tidak cukup. Tersisa: {$balance->remaining_days} hari, diajukan: {$totalDays} hari.");
            }
        }

        // Super admin creating → auto-approve
        if ($isAdmin) {
            $leave = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'reason' => $request->reason,
                'delegate_to' => $request->delegate_to,
                'status' => 'approved',
                'current_step' => 0,
            ]);

            // Kurangi saldo untuk jenis berkuota (Cuti Tahunan & WFH). WFH tidak minus.
            LeaveQuota::deduct($leave);

            return redirect()->route('admin.leaves.index')->with('success', "Cuti {$employee->full_name} langsung disetujui ({$totalDays} hari kerja).");
        }

        // Non-admin: pending, notify approver
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_days' => $totalDays,
            'reason' => $request->reason,
            'delegate_to' => $request->delegate_to,
            'status' => 'pending',
            'current_step' => 1,
        ]);

        $approver = $this->getApproverAtStep($employee, 1);
        if ($approver) {
            Notification::create([
                'employee_id' => $approver->id,
                'title' => 'Pengajuan Cuti Baru',
                'message' => "{$employee->full_name} mengajukan cuti {$totalDays} hari ({$request->start_date} - {$request->end_date})",
                'type' => 'approval',
                'reference_type' => LeaveRequest::class,
                'reference_id' => $leave->id,
            ]);

            FcmService::sendToEmployee($approver, 'Pengajuan Cuti Baru',
                "{$employee->full_name} mengajukan cuti {$totalDays} hari ({$request->start_date} - {$request->end_date})",
                ['type' => 'approval', 'reference_type' => 'leave', 'reference_id' => (string) $leave->id]
            );
        }

        return redirect()->route('admin.leaves.index')->with('success', "Pengajuan cuti berhasil dikirim ({$totalDays} hari kerja).");
    }

    public function show($id)
    {
        $admin = Employee::find(session('admin_id'));
        $leave = LeaveRequest::with([
            'employee:id,full_name,photo,department_id,job_level,approver_id',
            'employee.department:id,name',
            'leaveType',
            'delegate:id,full_name',
            'attachments',
            'approvalLogs' => fn($q) => $q->orderBy('created_at'),
            'approvalLogs.approver:id,full_name,position',
        ])->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
          ->findOrFail($id);

        // Build the approval chain for this employee
        $chain = $this->buildApprovalChain($leave->employee);

        return view('admin.leaves.show', compact('leave', 'chain'));
    }

    public function destroy($id)
    {
        $admin = Employee::find(session('admin_id'));
        $leave = LeaveRequest::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Hanya bisa hapus pengajuan dengan status pending.');
        }

        $leave->delete();
        return redirect()->route('admin.leaves.index')->with('success', 'Pengajuan cuti berhasil dihapus.');
    }

    private function getApproverAtStep(Employee $employee, int $step): ?Employee
    {
        return EmployeeApprover::getApproverAt($employee->id, 'leave', $step);
    }

    private function buildApprovalChain(Employee $employee): array
    {
        return EmployeeApprover::getChain($employee->id, 'leave')
            ->map(fn (EmployeeApprover $step) => [
                'step' => $step->step_order,
                'employee' => $step->approver,
            ])
            ->filter(fn (array $step) => $step['employee'] !== null)
            ->values()
            ->all();
    }
}
