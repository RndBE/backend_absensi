<?php

namespace App\Http\Controllers\Api;

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
    private $typeMap = [
        'leave' => LeaveRequest::class,
        'overtime' => OvertimeRequest::class,
        'attendance' => AttendanceRequest::class,
        'data-change' => DataChangeRequest::class,
    ];

    private $typeLabels = [
        'leave' => 'Cuti',
        'overtime' => 'Lembur',
        'attendance' => 'Presensi',
        'data-change' => 'Perubahan Data',
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

        return response()->json(['success' => true, 'data' => $pending]);
    }

    /**
     * Walk the approver chain to find requests where this employee is the expected approver.
     */
    private function getMyPendingItems(string $modelClass, Employee $me, array $relations): \Illuminate\Support\Collection
    {
        $items = $modelClass::whereIn('status', ['pending', 'in_review'])
            ->with('employee:id,full_name,approver_id')
            ->get();

        $myIds = [];
        foreach ($items as $item) {
            $approver = $this->getApproverAtStep($item->employee, $item->current_step ?? 1);
            if ($approver && $approver->id === $me->id) {
                $myIds[] = $item->id;
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

    /**
     * Walk approver_id chain: step 1 = employee->approver, step 2 = that approver's approver, etc.
     */
    private function getApproverAtStep(Employee $employee, int $step): ?Employee
    {
        $current = $employee;
        for ($i = 0; $i < $step; $i++) {
            if (!$current->approver_id) return null;
            $current = Employee::find($current->approver_id);
            if (!$current) return null;
        }
        return $current;
    }

    public function show(Request $request, $type, $id)
    {
        $modelClass = $this->typeMap[$type] ?? null;
        if (!$modelClass) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid'], 404);
        }

        $item = $modelClass::with(['employee', 'attachments', 'approvalLogs.approver'])->findOrFail($id);

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

        // Check if there's a next approver in the chain
        $nextApprover = $request->user()->approver; // the approver of the current approver

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
}
