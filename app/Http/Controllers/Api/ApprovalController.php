<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use App\Services\FcmService;
use App\Support\LeaveQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    public function monitor(Request $request)
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $statusFilter = $request->query('status', 'all');
        $typeFilter = $request->query('type');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $statuses = match ($statusFilter) {
            'active' => ['pending', 'in_review'],
            'approved' => ['approved'],
            'rejected' => ['rejected'],
            default => ['pending', 'in_review', 'approved', 'rejected'],
        };

        $result = [];

        foreach ($this->typeMap as $typeKey => $modelClass) {
            if ($typeFilter && $typeFilter !== $typeKey) {
                continue;
            }

            $relations = ['employee:id,full_name,photo,department_id', 'employee.department:id,name', 'approvalLogs.approver:id,full_name'];
            if ($modelClass === LeaveRequest::class) {
                $relations[] = 'leaveType:id,name';
            }
            if ($modelClass === BudgetRequest::class) {
                $relations[] = 'items';
            }

            $query = $modelClass::whereIn('status', $statuses)->with($relations);
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $items = $query->orderBy('created_at', 'desc')->get();

            foreach ($items as $item) {
                $requestType = $this->modelToRequestType($modelClass);
                $totalSteps = EmployeeApprover::totalSteps($item->employee_id, $requestType);
                $currentStep = $item->current_step ?? 1;

                // Build full chain with approval status per step
                $chain = [];
                for ($step = 1; $step <= max($totalSteps, 1); $step++) {
                    $approver = EmployeeApprover::getApproverAt($item->employee_id, $requestType, $step);
                    $log = $item->approvalLogs->firstWhere('step_order', $step);

                    $chain[] = [
                        'step' => $step,
                        'approver_name' => $approver?->full_name ?? '-',
                        'action' => $log?->action ?? ($step < $currentStep ? 'approved' : ($step === $currentStep && in_array($item->status, ['pending', 'in_review']) ? 'waiting' : 'pending')),
                        'notes' => $log?->notes,
                        'acted_at' => $log?->created_at?->format('d/m/Y H:i'),
                    ];
                }

                $result[] = [
                    'id' => $item->id,
                    'type' => $typeKey,
                    'type_label' => $this->typeLabels[$typeKey],
                    'status' => $item->status,
                    'current_step' => $currentStep,
                    'total_steps' => $totalSteps ?: 1,
                    'employee' => [
                        'full_name' => $item->employee?->full_name ?? '-',
                        'department' => $item->employee?->department?->name ?? '-',
                        'photo' => $item->employee?->photo ? asset('storage/'.$item->employee->photo) : null,
                    ],
                    'chain' => $chain,
                    'created_at' => $item->created_at?->format('d/m/Y H:i'),
                ];
            }
        }

        // Sort by created_at desc
        usort($result, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return response()->json(['success' => true, 'data' => $result]);
    }

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
    private function getMyPendingItems(string $modelClass, Employee $me, array $relations): Collection
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
        if (! $modelClass) {
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
        if (! $modelClass) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid'], 404);
        }

        $item = $modelClass::with('employee')->findOrFail($id);

        if (! in_array($item->status, ['pending', 'in_review'])) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses'], 422);
        }

        $currentStep = $item->current_step ?? 1;
        $requestType = $this->modelToRequestType($modelClass);

        if ($modelClass !== DataChangeRequest::class && $request->user()->role !== 'superadmin') {
            $expectedApprover = EmployeeApprover::getApproverAt($item->employee_id, $requestType, $currentStep);
            if (! $expectedApprover || $expectedApprover->id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Anda bukan approver untuk step ini.'], 403);
            }
        }

        // Tentukan atribusi: bila superadmin approve menggantikan approver yang ditugaskan,
        // catat atas nama approver asli dan simpan jejak siapa yang sebenarnya menekan.
        [$attributedApprover, $actedById, $approverName] = $this->resolveAttribution(
            $request->user(),
            $modelClass === DataChangeRequest::class
                ? null
                : EmployeeApprover::getApproverAt($item->employee_id, $requestType, $currentStep)
        );

        // Log this approval step
        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $attributedApprover->id,
            'acted_by_id' => $actedById,
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
                'message' => "{$item->employee->full_name} mengajukan {$typeLabel}, menunggu persetujuan Anda (Step ".($currentStep + 1).')',
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
                'message' => "Pengajuan {$typeLabel} Anda disetujui oleh {$approverName}, menunggu persetujuan selanjutnya",
                'type' => 'info',
                'reference_type' => $modelClass,
                'reference_id' => $item->id,
            ]);

            FcmService::sendToEmployee($item->employee, $progressNotif->title, $progressNotif->message);
        } else {
            // Final approval — no more approvers in chain
            $item->update(['status' => 'approved']);

            // Kurangi saldo untuk jenis berkuota (Cuti Tahunan & WFH). WFH tidak minus.
            if ($modelClass === LeaveRequest::class) {
                LeaveQuota::deduct($item);
            }

            // Notify the employee - final approval
            $notification = Notification::create([
                'employee_id' => $item->employee_id,
                'title' => "Pengajuan $typeLabel Disetujui",
                'message' => "Pengajuan {$typeLabel} Anda telah disetujui oleh {$approverName}",
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
        if (! $modelClass) {
            return response()->json(['success' => false, 'message' => 'Tipe tidak valid'], 404);
        }

        $item = $modelClass::with('employee')->findOrFail($id);

        if (! in_array($item->status, ['pending', 'in_review'])) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses'], 422);
        }

        $currentStep = $item->current_step ?? 1;
        $requestType = $this->modelToRequestType($modelClass);

        if ($modelClass !== DataChangeRequest::class && $request->user()->role !== 'superadmin') {
            $expectedApprover = EmployeeApprover::getApproverAt($item->employee_id, $requestType, $currentStep);
            if (! $expectedApprover || $expectedApprover->id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Anda bukan approver untuk step ini.'], 403);
            }
        }

        // Atribusi: bila superadmin menolak menggantikan approver asli, catat atas nama
        // approver asli dengan jejak siapa yang sebenarnya menekan.
        [$attributedApprover, $actedById, $approverName] = $this->resolveAttribution(
            $request->user(),
            $modelClass === DataChangeRequest::class
                ? null
                : EmployeeApprover::getApproverAt($item->employee_id, $requestType, $currentStep)
        );

        $item->update(['status' => 'rejected']);

        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $attributedApprover->id,
            'acted_by_id' => $actedById,
            'action' => 'rejected',
            'notes' => $request->notes,
            'step_order' => $currentStep,
        ]);

        $notification = Notification::create([
            'employee_id' => $item->employee_id,
            'title' => "Pengajuan $typeLabel Ditolak",
            'message' => "Pengajuan {$typeLabel} Anda ditolak oleh {$approverName}",
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

    /**
     * Tentukan atribusi pencatatan approval.
     *
     * Bila $expectedApprover ada dan berbeda dari user yang menekan (kasus superadmin
     * approve menggantikan approver asli), approval dicatat atas nama approver asli
     * dan acted_by_id merekam pelaku sebenarnya. Selain itu, pelaku = approver.
     *
     * @return array{0: Employee, 1: int|null, 2: string} [approver, acted_by_id, nama approver]
     */
    private function resolveAttribution($actingUser, ?Employee $expectedApprover): array
    {
        $attributedApprover = $expectedApprover ?? $actingUser;
        $actedById = $attributedApprover->id !== $actingUser->id ? $actingUser->id : null;

        return [$attributedApprover, $actedById, $attributedApprover->full_name];
    }
}
