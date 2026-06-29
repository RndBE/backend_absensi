<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\Lpj;
use App\Models\TravelReport;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Services\FcmService;
use App\Support\LeaveQuota;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $tab = $request->tab ?? 'leave';

        // Show requests where the current approver in the chain is this admin
        $leave = $this->getMyPendingRequests(LeaveRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name', 'leaveType', 'attachments'])
            ->orderBy('created_at', 'desc')->get();

        $overtime = $this->getMyPendingRequests(OvertimeRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name'])
            ->orderBy('created_at', 'desc')->get();

        $attendance = $this->getMyPendingRequests(AttendanceRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name'])
            ->orderBy('created_at', 'desc')->get();

        // Budget requests
        $budget = $this->getMyPendingRequests(BudgetRequest::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name', 'items'])
            ->orderBy('created_at', 'desc')->get();

        // Travel Reports (LHP)
        $travelReport = $this->getMyPendingRequests(TravelReport::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name'])
            ->orderBy('created_at', 'desc')->get();

        // LPJ
        $lpj = $this->getMyPendingRequests(Lpj::class, $admin)
            ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name', 'budgetRequest:id,title'])
            ->orderBy('created_at', 'desc')->get();

        // Data change requests: only visible to superadmin
        if ($admin->role === 'superadmin') {
            $dataChange = DataChangeRequest::whereIn('status', ['pending', 'in_review'])
                ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
                ->with(['employee:id,full_name,photo,department_id,job_level', 'employee.department:id,name', 'attachments'])
                ->orderBy('created_at', 'desc')->get();
        } else {
            $dataChange = collect();
        }

        return view('admin.approvals.index', compact('leave', 'overtime', 'attendance', 'budget', 'travelReport', 'lpj', 'dataChange', 'tab', 'admin'));
    }

    public function approve(Request $request, $type, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $modelClass = $this->resolveModel($type);
        $typeLabel = $this->typeLabel($type);
        $item = $modelClass::with('employee')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        // Data change requests: only superadmin can approve
        if ($type === 'data-change') {
            if ($admin->role !== 'superadmin') {
                return back()->with('error', 'Hanya superadmin yang dapat menyetujui perubahan data.');
            }
        } elseif ($admin->role !== 'superadmin') {
            // Other types: follow approver chain from employee_approvers
            $requestType = $this->modelToRequestType($modelClass);
            $expectedApprover = $this->getApproverAtStep($item->employee, $item->current_step, $requestType);
            if (!$expectedApprover || $expectedApprover->id !== $admin->id) {
                return back()->with('error', 'Anda bukan approver untuk step ini.');
            }
        }

        // Atribusi: bila superadmin approve menggantikan approver asli, catat atas nama
        // approver asli dan simpan jejak siapa yang sebenarnya menekan.
        [$attributedApprover, $actedById, $approverName] = $this->resolveAttribution(
            $admin,
            $type === 'data-change'
                ? null
                : $this->getApproverAtStep($item->employee, $item->current_step, $this->modelToRequestType($modelClass))
        );

        // Log the approval
        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $attributedApprover->id,
            'acted_by_id' => $actedById,
            'action' => 'approved',
            'step_order' => $item->current_step,
            'notes' => $request->notes,
        ]);

        // If overtime: approver can adjust duration/break
        if ($modelClass === OvertimeRequest::class && $request->filled('adjusted_duration')) {
            $item->update([
                'approved_duration' => (int) $request->adjusted_duration,
                'approved_break' => (int) ($request->adjusted_break ?? $item->break_duration),
            ]);
        }

        // Data change: superadmin is always final approver (no chain)
        if ($type === 'data-change') {
            $item->update(['status' => 'approved']);
            $this->onFinalApproval($modelClass, $item);

            Notification::create([
                'employee_id' => $item->employee_id,
                'title' => "Pengajuan $typeLabel Disetujui",
                'message' => "Pengajuan perubahan data anda telah disetujui oleh {$admin->full_name}",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, "Pengajuan $typeLabel Disetujui",
                "Pengajuan perubahan data anda telah disetujui oleh {$admin->full_name}"
            );

            return back()->with('success', 'Perubahan data disetujui dan telah diterapkan.');
        }

        // Check: is there a next approver in the chain?
        $requestType = $this->modelToRequestType($modelClass);
        $nextApprover = $this->getApproverAtStep($item->employee, $item->current_step + 1, $requestType);

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
                'message' => "Disetujui oleh {$approverName}. Menunggu approval {$nextApprover->full_name}.",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, "Pengajuan $typeLabel Diproses",
                "Disetujui oleh {$approverName}. Menunggu approval selanjutnya."
            );

            return back()->with('success', "Step disetujui. Menunggu: {$nextApprover->full_name}");
        } else {
            // Final approval — no more approvers in chain
            $item->update(['status' => 'approved']);
            $this->onFinalApproval($modelClass, $item);

            Notification::create([
                'employee_id' => $item->employee_id,
                'title' => "Pengajuan $typeLabel Disetujui",
                'message' => "Pengajuan anda telah disetujui (final) oleh {$approverName}",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, "Pengajuan $typeLabel Disetujui",
                "Pengajuan {$typeLabel} anda telah disetujui oleh {$approverName}"
            );

            return back()->with('success', 'Pengajuan disetujui (final approval).');
        }
    }

    public function reject(Request $request, $type, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $modelClass = $this->resolveModel($type);
        $typeLabel = $this->typeLabel($type);
        $item = $modelClass::with('employee')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);

        // Data change requests: only superadmin can reject
        if ($type === 'data-change') {
            if ($admin->role !== 'superadmin') {
                return back()->with('error', 'Hanya superadmin yang dapat menolak perubahan data.');
            }
        } elseif ($admin->role !== 'superadmin') {
            $requestType = $this->modelToRequestType($modelClass);
            $expectedApprover = $this->getApproverAtStep($item->employee, $item->current_step, $requestType);
            if (!$expectedApprover || $expectedApprover->id !== $admin->id) {
                return back()->with('error', 'Anda bukan approver untuk step ini.');
            }
        }

        // Atribusi: bila superadmin menolak menggantikan approver asli, catat atas nama
        // approver asli dengan jejak siapa yang sebenarnya menekan.
        [$attributedApprover, $actedById, $approverName] = $this->resolveAttribution(
            $admin,
            $type === 'data-change'
                ? null
                : $this->getApproverAtStep($item->employee, $item->current_step, $this->modelToRequestType($modelClass))
        );

        $item->update(['status' => 'rejected']);

        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $attributedApprover->id,
            'acted_by_id' => $actedById,
            'action' => 'rejected',
            'step_order' => $item->current_step,
            'notes' => $request->notes,
        ]);

        Notification::create([
            'employee_id' => $item->employee_id,
            'title' => "Pengajuan $typeLabel Ditolak",
            'message' => "Pengajuan anda ditolak oleh {$approverName}" . ($request->notes ? ": {$request->notes}" : ''),
            'type' => 'info',
            'reference_type' => $modelClass,
            'reference_id' => $item->id,
        ]);

        FcmService::sendToEmployee($item->employee, "Pengajuan $typeLabel Ditolak",
            "Pengajuan {$typeLabel} anda ditolak oleh {$approverName}"
        );

        return back()->with('success', 'Pengajuan berhasil ditolak.');
    }

    /**
     * Get the approver at a specific step using employee_approvers table.
     */
    private function getApproverAtStep(Employee $employee, int $step, string $requestType = 'leave'): ?Employee
    {
        return EmployeeApprover::getApproverAt($employee->id, $requestType, $step);
    }

    /**
     * Tentukan atribusi pencatatan approval.
     *
     * Bila $expectedApprover ada dan berbeda dari user yang menekan (kasus superadmin
     * approve menggantikan approver asli), approval dicatat atas nama approver asli
     * dan acted_by_id merekam pelaku sebenarnya. Selain itu, pelaku = approver.
     *
     * @return array{0: Employee, 1: int|null, 2: string} [approver, acted_by_id, nama approver]
     */
    private function resolveAttribution(Employee $actingUser, ?Employee $expectedApprover): array
    {
        $attributedApprover = $expectedApprover ?? $actingUser;
        $actedById = $attributedApprover->id !== $actingUser->id ? $actingUser->id : null;

        return [$attributedApprover, $actedById, $attributedApprover->full_name];
    }

    /**
     * Map model class to request_type string for employee_approvers lookup.
     */
    private function modelToRequestType(string $modelClass): string
    {
        return match ($modelClass) {
            LeaveRequest::class => 'leave',
            OvertimeRequest::class => 'overtime',
            AttendanceRequest::class => 'attendance',
            BudgetRequest::class => 'budget',
            TravelReport::class => 'travel_report',
            Lpj::class => 'lpj',
            default => 'leave',
        };
    }

    private function getMyPendingRequests(string $modelClass, Employee $admin)
    {
        if ($admin->role === 'superadmin') {
            return $modelClass::whereIn('status', ['pending', 'in_review']);
        }

        $requestType = $this->modelToRequestType($modelClass);

        // Find all employees where this admin is an approver for this request type
        $employeeSteps = EmployeeApprover::where('approver_id', $admin->id)
            ->where('request_type', $requestType)
            ->get()
            ->groupBy('employee_id');

        $pending = $modelClass::whereIn('status', ['pending', 'in_review'])
            ->get();

        $myIds = [];

        foreach ($pending as $req) {
            if (isset($employeeSteps[$req->employee_id])) {
                // Check if this admin is the approver at the current step
                $steps = $employeeSteps[$req->employee_id];
                foreach ($steps as $stepRecord) {
                    if ($stepRecord->step_order === $req->current_step) {
                        $myIds[] = $req->id;
                        break;
                    }
                }
            }
        }

        if (empty($myIds)) {
            return $modelClass::whereRaw('1 = 0');
        }

        return $modelClass::whereIn('id', $myIds);
    }

    private function onFinalApproval(string $modelClass, $item): void
    {
        // Kurangi saldo untuk jenis berkuota (Cuti Tahunan & WFH). WFH tidak minus.
        if ($modelClass === LeaveRequest::class) {
            LeaveQuota::deduct($item);
        }

        // Data change: apply the approved change to employee record
        if ($modelClass === DataChangeRequest::class) {
            $employee = Employee::find($item->employee_id);
            if ($employee && $item->field_name) {
                $allowedFields = [
                    'full_name', 'nik', 'residential_address', 'ktp_address',
                    'religion', 'phone', 'email', 'marital_status',
                    'blood_type', 'postal_code',
                ];
                if (in_array($item->field_name, $allowedFields)) {
                    $employee->update([$item->field_name => $item->new_value]);
                }
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
            'budget' => BudgetRequest::class,
            'travel_report' => TravelReport::class,
            'lpj' => Lpj::class,
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
            'budget' => 'Anggaran',
            'travel_report' => 'LHP',
            'lpj' => 'LPJ',
            default => 'Pengajuan',
        };
    }
}
