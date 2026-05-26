<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\TravelReport;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Services\FcmService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    private $typeMap = [
        'leave' => LeaveRequest::class,
        'overtime' => OvertimeRequest::class,
        'attendance' => AttendanceRequest::class,
        'data-change' => DataChangeRequest::class,
        'budget' => BudgetRequest::class,
        'travel_report' => TravelReport::class,
    ];

    private $typeLabels = [
        'leave' => 'Cuti',
        'overtime' => 'Lembur',
        'attendance' => 'Presensi',
        'data-change' => 'Perubahan Data',
        'budget' => 'Anggaran',
        'travel_report' => 'LHP',
    ];

    public function index(Request $request)
    {
        $employee = $request->user();

        $pending = [];
        $pending['leave'] = $this->getMyPendingItems(LeaveRequest::class, $employee,
            ['employee:id,full_name,photo,department_id,approver_id', 'employee.department:id,name', 'leaveType']);
        $pending['overtime'] = $this->getMyPendingItems(OvertimeRequest::class, $employee,
            ['employee:id,full_name,photo,department_id,approver_id', 'employee.department:id,name']);
        $pending['attendance'] = $this->getMyPendingItems(AttendanceRequest::class, $employee,
            ['employee:id,full_name,photo,department_id,approver_id', 'employee.department:id,name']);
        $pending['data_change'] = $this->getMyPendingItems(DataChangeRequest::class, $employee,
            ['employee:id,full_name,photo,department_id,approver_id', 'employee.department:id,name']);
        $pending['budget'] = $this->getMyPendingItems(BudgetRequest::class, $employee,
            ['employee:id,full_name,photo,department_id,approver_id', 'employee.department:id,name', 'items']);
        $pending['travel_report'] = $this->getMyPendingItems(TravelReport::class, $employee,
            ['employee:id,full_name,photo,department_id,approver_id', 'employee.department:id,name']);

        return response()->json(['success' => true, 'data' => $pending]);
    }

    /**
     * Walk the approver chain to find requests where this employee is the expected approver.
     */
    private function getMyPendingItems(string $modelClass, Employee $me, array $relations): \Illuminate\Support\Collection
    {
        if ($modelClass === DataChangeRequest::class) {
            if ($me->role !== 'superadmin') {
                return collect([]);
            }

            return $modelClass::whereIn('status', ['pending', 'in_review'])
                ->with($relations)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        if ($me->role === 'superadmin') {
            return $modelClass::whereIn('status', ['pending', 'in_review'])
                ->with($relations)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $requestType = $this->modelToRequestType($modelClass);
        $employeeSteps = EmployeeApprover::where('approver_id', $me->id)
            ->where('request_type', $requestType)
            ->get()
            ->groupBy('employee_id');

        $items = $modelClass::whereIn('status', ['pending', 'in_review'])
            ->get();

        $myIds = [];
        foreach ($items as $item) {
            if (isset($employeeSteps[$item->employee_id])) {
                $steps = $employeeSteps[$item->employee_id];
                foreach ($steps as $stepRecord) {
                    if ((int) $stepRecord->step_order === (int) ($item->current_step ?? 1)) {
                        $myIds[] = $item->id;
                        break;
                    }
                }
            }
        }

        if (empty($myIds)) {
            return collect([]);
        }

        return $modelClass::whereIn('id', $myIds)
            ->with($relations)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function show(Request $request, $type, $id)
    {
        $modelClass = $this->typeMap[$type] ?? null;
        if (!$modelClass) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid'], 404);
        }

        $relations = ['employee', 'attachments', 'approvalLogs.approver'];
        if ($modelClass === LeaveRequest::class) {
            $relations[] = 'leaveType';
        }
        if ($modelClass === BudgetRequest::class) {
            $relations[] = 'items';
            $relations[] = 'participants';
        }
        if ($modelClass === TravelReport::class) {
            $relations[] = 'activities.documents';
            $relations[] = 'documents';
            $relations[] = 'budgetRequest';
        }

        $item = $modelClass::with($relations)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function approve(Request $request, $type, $id)
    {
        $request->validate([
            'notes' => 'nullable|string',
            'adjusted_duration' => 'nullable|integer|min:0',
            'adjusted_break' => 'nullable|integer|min:0',
        ]);

        $modelClass = $this->typeMap[$type] ?? null;
        $typeLabel = $this->typeLabels[$type] ?? 'Pengajuan';
        if (!$modelClass) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid'], 404);
        }

        $item = $modelClass::with('employee')->findOrFail($id);

        if (!in_array($item->status, ['pending', 'in_review'])) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses'], 422);
        }

        $currentStep = $item->current_step ?? 1;
        $requestType = $this->modelToRequestType($modelClass);

        if ($modelClass !== DataChangeRequest::class && $request->user()->role !== 'superadmin') {
            $expectedApprover = EmployeeApprover::getApproverAt($item->employee_id, $requestType, $currentStep);
            if (!$expectedApprover || $expectedApprover->id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Anda bukan approver untuk step ini.'], 403);
            }
        }

        // Log this approval step
        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $request->user()->id,
            'action' => 'approved',
            'notes' => $request->notes,
            'step_order' => $currentStep,
        ]);

        // If overtime: approver can adjust duration/break
        if ($modelClass === OvertimeRequest::class && $request->filled('adjusted_duration')) {
            $item->update([
                'approved_duration' => $request->adjusted_duration,
                'approved_break' => $request->adjusted_break ?? $item->break_duration,
            ]);
        }

        // Check if there's a next approver in the configured employee_approvers chain.
        $nextApprover = $modelClass === DataChangeRequest::class
            ? null
            : EmployeeApprover::getApproverAt($item->employee_id, $requestType, $currentStep + 1);

        if ($nextApprover) {
            // There's a next level — forward to next step
            $item->update([
                'status' => 'in_review',
                'current_step' => $currentStep + 1,
            ]);

            // Notify next approver
            $notification = Notification::create([
                'employee_id' => $nextApprover->id,
                'title' => "Pengajuan $typeLabel - Persetujuan Lanjutan",
                'message' => "{$item->employee->full_name} mengajukan {$typeLabel}, menunggu persetujuan Anda (Step " . ($currentStep + 1) . ")",
                'type' => 'approval',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            // Send push notification
            FcmService::sendToEmployee($nextApprover, $notification->title, $notification->message, [
                'type' => 'approval',
                'reference_type' => $type,
                'reference_id' => (string) $item->id,
            ]);

            // Notify the requesting employee about step progress
            $progressNotif = Notification::create([
                'employee_id' => $item->employee_id,
                'title' => "Pengajuan $typeLabel - Disetujui Step $currentStep",
                'message' => "Pengajuan {$typeLabel} Anda disetujui oleh {$request->user()->full_name}, menunggu persetujuan selanjutnya",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, $progressNotif->title, $progressNotif->message);
        } else {
            // Final approval — no more approvers in chain
            $item->update(['status' => 'approved']);

            // Update leave balance if leave request
            if ($modelClass === LeaveRequest::class) {
                $balance = LeaveBalance::where('employee_id', $item->employee_id)
                    ->where('leave_type_id', $item->leave_type_id)
                    ->where('year', now()->year)
                    ->first();

                if ($balance) {
                    $balance->update([
                        'used_days' => $balance->used_days + $item->total_days,
                        'remaining_days' => $balance->remaining_days - $item->total_days,
                    ]);
                }
            }

            // Notify the employee - final approval
            $notification = Notification::create([
                'employee_id' => $item->employee_id,
                'title' => "Pengajuan $typeLabel Disetujui",
                'message' => "Pengajuan {$typeLabel} Anda telah disetujui oleh {$request->user()->full_name}",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, $notification->title, $notification->message);
        }

        return response()->json(['success' => true, 'message' => 'Pengajuan disetujui']);
    }

    public function reject(Request $request, $type, $id)
    {
        $request->validate(['notes' => 'nullable|string']);

        $modelClass = $this->typeMap[$type] ?? null;
        $typeLabel = $this->typeLabels[$type] ?? 'Pengajuan';
        if (!$modelClass) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid'], 404);
        }

        $item = $modelClass::with('employee')->findOrFail($id);

        if (!in_array($item->status, ['pending', 'in_review'])) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses'], 422);
        }

        $currentStep = $item->current_step ?? 1;
        $requestType = $this->modelToRequestType($modelClass);

        if ($modelClass !== DataChangeRequest::class && $request->user()->role !== 'superadmin') {
            $expectedApprover = EmployeeApprover::getApproverAt($item->employee_id, $requestType, $currentStep);
            if (!$expectedApprover || $expectedApprover->id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Anda bukan approver untuk step ini.'], 403);
            }
        }

        $item->update(['status' => 'rejected']);

        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $request->user()->id,
            'action' => 'rejected',
            'notes' => $request->notes,
            'step_order' => $currentStep,
        ]);

        $notification = Notification::create([
            'employee_id' => $item->employee_id,
            'title' => "Pengajuan $typeLabel Ditolak",
            'message' => "Pengajuan {$typeLabel} Anda ditolak oleh {$request->user()->full_name}",
            'type' => 'info',
            'reference_type' => $modelClass,
            'reference_id' => $item->id,
        ]);

        FcmService::sendToEmployee($item->employee, $notification->title, $notification->message);

        return response()->json(['success' => true, 'message' => 'Pengajuan ditolak']);
    }

    private function modelToRequestType(string $modelClass): string
    {
        return match ($modelClass) {
            LeaveRequest::class => 'leave',
            OvertimeRequest::class => 'overtime',
            AttendanceRequest::class => 'attendance',
            BudgetRequest::class => 'budget',
            TravelReport::class => 'travel_report',
            DataChangeRequest::class => 'data-change',
            default => 'leave',
        };
    }
}
