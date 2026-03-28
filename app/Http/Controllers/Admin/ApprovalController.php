<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\AttendanceRequest;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Services\FcmService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $tab = $request->tab ?? 'leave';

        // Show requests where the current approver in the chain is this admin
        $leave = $this->getMyPendingRequests(LeaveRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name', 'leaveType'])
            ->orderBy('created_at', 'desc')->get();

        $overtime = $this->getMyPendingRequests(OvertimeRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name'])
            ->orderBy('created_at', 'desc')->get();

        $attendance = $this->getMyPendingRequests(AttendanceRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name'])
            ->orderBy('created_at', 'desc')->get();

        $dataChange = $this->getMyPendingRequests(DataChangeRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name'])
            ->orderBy('created_at', 'desc')->get();

        return view('admin.approvals.index', compact('leave', 'overtime', 'attendance', 'dataChange', 'tab', 'admin'));
    }

    public function approve(Request $request, $type, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $modelClass = $this->resolveModel($type);
        $typeLabel = $this->typeLabel($type);
        $item = $modelClass::with('employee')->findOrFail($id);

        // Check: is this admin the current approver in the chain?
        $expectedApprover = $this->getApproverAtStep($item->employee, $item->current_step);

        if (!$expectedApprover || $expectedApprover->id !== $admin->id) {
            return back()->with('error', 'Anda bukan approver untuk step ini.');
        }

        // Log the approval
        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $admin->id,
            'action' => 'approved',
            'step_order' => $item->current_step,
            'notes' => $request->notes,
        ]);

        // Check: is there a next approver in the chain?
        $nextApprover = $this->getApproverAtStep($item->employee, $item->current_step + 1);

        if ($nextApprover) {
            // Move to next step
            $item->update(['status' => 'in_review', 'current_step' => $item->current_step + 1]);

            // Notify the next approver
            Notification::create([
                'employee_id' => $nextApprover->id,
                'title' => "Pengajuan $typeLabel - Persetujuan Lanjutan",
                'message' => "{$item->employee->full_name} mengajukan {$typeLabel}, menunggu persetujuan Anda (Step " . ($item->current_step) . ")",
                'type' => 'approval',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($nextApprover, "Pengajuan $typeLabel - Persetujuan Lanjutan",
                "{$item->employee->full_name} mengajukan {$typeLabel}, menunggu persetujuan Anda",
                ['type' => 'approval', 'reference_type' => $type, 'reference_id' => (string) $item->id]
            );

            // Notify the requesting employee about step progress
            Notification::create([
                'employee_id' => $item->employee_id,
                'title' => "Pengajuan $typeLabel - Disetujui Step " . ($item->current_step - 1),
                'message' => "Disetujui oleh {$admin->full_name}. Menunggu approval {$nextApprover->full_name}.",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, "Pengajuan $typeLabel Diproses",
                "Disetujui oleh {$admin->full_name}. Menunggu approval selanjutnya."
            );

            return back()->with('success', "Step disetujui. Menunggu: {$nextApprover->full_name}");
        } else {
            // Final approval — no more approvers in chain
            $item->update(['status' => 'approved']);
            $this->onFinalApproval($modelClass, $item);

            Notification::create([
                'employee_id' => $item->employee_id,
                'title' => "Pengajuan $typeLabel Disetujui",
                'message' => "Pengajuan anda telah disetujui (final) oleh {$admin->full_name}",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, "Pengajuan $typeLabel Disetujui",
                "Pengajuan {$typeLabel} anda telah disetujui oleh {$admin->full_name}"
            );

            return back()->with('success', 'Pengajuan disetujui (final approval).');
        }
    }

    public function reject(Request $request, $type, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $modelClass = $this->resolveModel($type);
        $typeLabel = $this->typeLabel($type);
        $item = $modelClass::with('employee')->findOrFail($id);

        $expectedApprover = $this->getApproverAtStep($item->employee, $item->current_step);

        if (!$expectedApprover || $expectedApprover->id !== $admin->id) {
            return back()->with('error', 'Anda bukan approver untuk step ini.');
        }

        $item->update(['status' => 'rejected']);

        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $admin->id,
            'action' => 'rejected',
            'step_order' => $item->current_step,
            'notes' => $request->notes,
        ]);

        Notification::create([
            'employee_id' => $item->employee_id,
            'title' => "Pengajuan $typeLabel Ditolak",
            'message' => "Pengajuan anda ditolak oleh {$admin->full_name}" . ($request->notes ? ": {$request->notes}" : ''),
            'type' => 'info',
            'reference_type' => $modelClass,
            'reference_id' => $item->id,
        ]);

        FcmService::sendToEmployee($item->employee, "Pengajuan $typeLabel Ditolak",
            "Pengajuan {$typeLabel} anda ditolak oleh {$admin->full_name}"
        );

        return back()->with('success', 'Pengajuan berhasil ditolak.');
    }

    /**
     * Follow the approver_id chain to find who is the approver at step N.
     */
    private function getApproverAtStep(Employee $employee, int $step): ?Employee
    {
        $current = $employee;

        for ($i = 0; $i < $step; $i++) {
            if (!$current->approver_id) {
                return null;
            }
            $current = Employee::find($current->approver_id);
            if (!$current) {
                return null;
            }
        }

        return $current;
    }

    private function getChainLength(Employee $employee): int
    {
        $count = 0;
        $current = $employee;
        $visited = [];

        while ($current->approver_id && !in_array($current->approver_id, $visited)) {
            $visited[] = $current->id;
            $current = Employee::find($current->approver_id);
            if (!$current) break;
            $count++;
        }

        return $count;
    }

    private function getMyPendingRequests(string $modelClass, Employee $admin)
    {
        $pending = $modelClass::whereIn('status', ['pending', 'in_review'])
            ->with('employee:id,full_name,approver_id,job_level,company_id')
            ->get();

        $myIds = [];

        foreach ($pending as $req) {
            $expectedApprover = $this->getApproverAtStep($req->employee, $req->current_step);
            if ($expectedApprover && $expectedApprover->id === $admin->id) {
                $myIds[] = $req->id;
            }
        }

        if (empty($myIds)) {
            return $modelClass::whereRaw('1 = 0');
        }

        return $modelClass::whereIn('id', $myIds);
    }

    private function onFinalApproval(string $modelClass, $item): void
    {
        if ($modelClass === LeaveRequest::class) {
            $balance = LeaveBalance::where('employee_id', $item->employee_id)
                ->where('leave_type_id', $item->leave_type_id)
                ->where('year', now()->year)->first();
            if ($balance) {
                $balance->update([
                    'used_days' => $balance->used_days + $item->total_days,
                    'remaining_days' => $balance->remaining_days - $item->total_days,
                ]);
            }
        }
    }

    private function resolveModel(string $type): string
    {
        return match ($type) {
            'leave' => LeaveRequest::class,
            'overtime' => OvertimeRequest::class,
            'attendance' => AttendanceRequest::class,
            'data-change' => DataChangeRequest::class,
            default => abort(404),
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'leave' => 'Cuti',
            'overtime' => 'Lembur',
            'attendance' => 'Presensi',
            'data-change' => 'Perubahan Data',
            default => 'Pengajuan',
        };
    }
}
