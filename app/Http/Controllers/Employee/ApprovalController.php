<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveRequest;
use App\Models\Lpj;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use App\Support\LeaveQuota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ApprovalController extends Controller
{
    private array $typeMap = [
        'leave' => LeaveRequest::class,
        'overtime' => OvertimeRequest::class,
        'attendance' => AttendanceRequest::class,
        'budget' => BudgetRequest::class,
        'travel_report' => TravelReport::class,
        'lpj' => Lpj::class,
    ];

    private array $typeLabels = [
        'leave' => 'Cuti',
        'overtime' => 'Lembur',
        'attendance' => 'Absensi',
        'budget' => 'Anggaran',
        'travel_report' => 'LHP',
        'lpj' => 'LPJ',
    ];

    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.approvals.index', [
            'employee' => $employee,
            'items' => $this->pendingFor($employee),
        ]);
    }

    public function approve(Request $request, string $type, int $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'adjusted_duration' => 'nullable|integer|min:0',
            'adjusted_break' => 'nullable|integer|min:0',
        ]);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $modelClass = $this->resolveModel($type);
        $item = $modelClass::with('employee')->findOrFail($id);

        if (! $this->canActOn($employee, $type, $item)) {
            return redirect()->route('employee.approvals.index')
                ->with('error', 'Anda bukan approver untuk step ini.');
        }

        $currentStep = (int) ($item->current_step ?? 1);

        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $employee->id,
            'action' => 'approved',
            'step_order' => $currentStep,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($modelClass === OvertimeRequest::class && $request->filled('adjusted_duration')) {
            $item->update([
                'approved_duration' => (int) $validated['adjusted_duration'],
                'approved_break' => (int) ($validated['adjusted_break'] ?? $item->break_duration),
            ]);
        }

        $nextApprover = EmployeeApprover::getApproverAt($item->employee_id, $type, $currentStep + 1);

        if ($nextApprover) {
            $item->update([
                'status' => 'in_review',
                'current_step' => $currentStep + 1,
            ]);

            return redirect()->route('employee.approvals.index')
                ->with('success', "Step disetujui. Menunggu: {$nextApprover->full_name}");
        }

        $item->update(['status' => 'approved']);
        $this->onFinalApproval($modelClass, $item);

        return redirect()->route('employee.approvals.index')
            ->with('success', 'Pengajuan disetujui.');
    }

    public function reject(Request $request, string $type, int $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $modelClass = $this->resolveModel($type);
        $item = $modelClass::with('employee')->findOrFail($id);

        if (! $this->canActOn($employee, $type, $item)) {
            return redirect()->route('employee.approvals.index')
                ->with('error', 'Anda bukan approver untuk step ini.');
        }

        $currentStep = (int) ($item->current_step ?? 1);
        $item->update(['status' => 'rejected']);

        ApprovalLog::create([
            'approvable_type' => $modelClass,
            'approvable_id' => $item->id,
            'approver_id' => $employee->id,
            'action' => 'rejected',
            'step_order' => $currentStep,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('employee.approvals.index')
            ->with('success', 'Pengajuan berhasil ditolak.');
    }

    private function pendingFor(Employee $approver): Collection
    {
        return collect($this->typeMap)
            ->flatMap(function (string $modelClass, string $type) use ($approver) {
                if (! Schema::hasTable((new $modelClass())->getTable())) {
                    return collect();
                }

                $employeeSteps = EmployeeApprover::where('approver_id', $approver->id)
                    ->where('request_type', $type)
                    ->get()
                    ->groupBy('employee_id');

                if ($employeeSteps->isEmpty()) {
                    return collect();
                }

                $items = $modelClass::whereIn('status', ['pending', 'in_review'])
                    ->whereIn('employee_id', $employeeSteps->keys())
                    ->with($this->relationsFor($type))
                    ->orderBy('created_at', 'desc')
                    ->get();

                return $items
                    ->filter(function ($item) use ($employeeSteps) {
                        $steps = $employeeSteps->get($item->employee_id);
                        if (! $steps) {
                            return false;
                        }

                        return $steps->contains(fn ($step) => (int) $step->step_order === (int) ($item->current_step ?? 1));
                    })
                    ->map(fn ($item) => [
                        'type' => $type,
                        'type_label' => $this->typeLabels[$type],
                        'model' => $item,
                    ]);
            })
            ->sortByDesc(fn ($row) => $row['model']->created_at)
            ->values();
    }

    private function canActOn(Employee $approver, string $type, Model $item): bool
    {
        if (! in_array($item->status, ['pending', 'in_review'], true)) {
            return false;
        }

        $expectedApprover = EmployeeApprover::getApproverAt(
            (int) $item->employee_id,
            $type,
            (int) ($item->current_step ?? 1)
        );

        return $expectedApprover?->id === $approver->id;
    }

    private function onFinalApproval(string $modelClass, Model $item): void
    {
        if ($modelClass !== LeaveRequest::class) {
            return;
        }

        // Kurangi saldo untuk jenis berkuota (Cuti Tahunan & WFH). WFH tidak minus.
        LeaveQuota::deduct($item);
    }

    private function resolveModel(string $type): string
    {
        return $this->typeMap[$type] ?? abort(404);
    }

    private function relationsFor(string $type): array
    {
        return match ($type) {
            'leave' => ['employee:id,full_name,position,photo', 'leaveType', 'attachments'],
            'overtime' => ['employee:id,full_name,position,photo', 'attachments'],
            'budget' => ['employee:id,full_name,position,photo', 'items', 'attachments'],
            'travel_report' => ['employee:id,full_name,position,photo', 'budgetRequest', 'attachments'],
            'lpj' => ['employee:id,full_name,position,photo', 'budgetRequest:id,title,total_amount', 'travelReport:id,destination_city'],
            default => ['employee:id,full_name,position,photo'],
        };
    }
}
