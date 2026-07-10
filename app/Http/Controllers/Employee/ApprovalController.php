<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Lpj;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use App\Support\LeaveQuota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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

        $tab = $request->query('tab') === 'history' ? 'history' : 'pending';
        $items = $this->pendingFor($employee);

        return view('employee.approvals.index', [
            'employee' => $employee,
            'tab' => $tab,
            'items' => $items,
            'pendingCount' => $items->count(),
            'history' => $tab === 'history' ? $this->historyFor($employee) : null,
            'historyCount' => $this->historyCountFor($employee),
            'typeLabels' => $this->typeLabels,
        ]);
    }

    /**
     * Riwayat keputusan approver ini: apa yang pernah ia setujui/tolak, di step berapa,
     * beserta catatannya. Diambil dari approval_logs, bukan dari status pengajuan — sebuah
     * pengajuan bisa melewati beberapa approver, dan masing-masing hanya melihat langkahnya.
     */
    private function historyFor(Employee $approver)
    {
        $types = array_values($this->typeMap);

        return ApprovalLog::query()
            ->where('approver_id', $approver->id)
            ->whereIn('approvable_type', $types)
            ->with(['approvable' => fn (MorphTo $morphTo) => $morphTo->morphWith(
                array_fill_keys($types, ['employee:id,full_name,photo,department_id', 'employee.department:id,name'])
            )])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();
    }

    private function historyCountFor(Employee $approver): int
    {
        return ApprovalLog::where('approver_id', $approver->id)
            ->whereIn('approvable_type', array_values($this->typeMap))
            ->count();
    }

    public function printBudget(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $budgetRequest = BudgetRequest::with([
            'employee:id,full_name,photo,signature,department_id,position,job_level',
            'employee.department:id,name',
            'items.attachments',
            'attachments',
            'participants:id,full_name,photo',
            'approvalLogs.approver:id,full_name,signature,position,job_level',
            'travelZone',
        ])->findOrFail($id);

        if (! $this->canActOn($employee, 'budget', $budgetRequest)) {
            return redirect()->route('employee.approvals.index')
                ->with('error', 'Anda bukan approver untuk step pengajuan ini.');
        }

        return view('budget-requests.print', [
            'budgetRequest' => $budgetRequest,
            'approvalChain' => EmployeeApprover::getChain($budgetRequest->employee_id, 'budget'),
            'backUrl' => route('employee.approvals.index'),
        ]);
    }

    public function printTravelReport(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $report = TravelReport::with([
            'employee:id,full_name,photo,signature,department_id,position,job_level',
            'employee.department:id,name',
            'budgetRequest:id,title',
            'activities.documents',
            'documents',
            'approvalLogs.approver:id,full_name,signature',
        ])->findOrFail($id);

        if (! $this->canActOn($employee, 'travel_report', $report)) {
            return redirect()->route('employee.approvals.index')
                ->with('error', 'Anda bukan approver untuk step pengajuan ini.');
        }

        return view('admin.travel-reports.print', [
            'report' => $report,
            'backUrl' => route('employee.approvals.index'),
        ]);
    }

    public function printLpj(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $lpj = Lpj::with([
            'employee:id,full_name,photo,department_id,position,job_level',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount,surat_tugas_no,surat_tugas_date',
            'travelReport:id,destination_city,departure_date,return_date',
            'items.budgetRequestItem',
            'approvalLogs.approver:id,full_name,photo',
        ])->findOrFail($id);

        if (! $this->canActOn($employee, 'lpj', $lpj)) {
            return redirect()->route('employee.approvals.index')
                ->with('error', 'Anda bukan approver untuk step pengajuan ini.');
        }

        return view('lpj.print', [
            'lpj' => $lpj,
            'backUrl' => route('employee.approvals.index'),
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

                $mine = $items->filter(function ($item) use ($employeeSteps) {
                    $steps = $employeeSteps->get($item->employee_id);
                    if (! $steps) {
                        return false;
                    }

                    return $steps->contains(fn ($step) => (int) $step->step_order === (int) ($item->current_step ?? 1));
                });

                if ($mine->isEmpty()) {
                    return collect();
                }

                $chains = $this->chainsFor($mine->pluck('employee_id')->unique()->all(), $type);
                $balances = $type === 'leave' ? $this->leaveBalancesFor($mine) : collect();

                return $mine->map(function ($item) use ($type, $chains, $balances) {
                    $chain = $chains->get($item->employee_id) ?? collect();
                    $step = (int) ($item->current_step ?? 1);

                    return [
                        'type' => $type,
                        'type_label' => $this->typeLabels[$type],
                        'model' => $item,
                        'step' => $step,
                        'total_steps' => $chain->count(),
                        // Approver berikutnya; null berarti keputusan approver ini yang final.
                        'next_approver' => $chain->firstWhere('step_order', $step + 1)?->approver?->full_name,
                        // Sisa kuota cuti, hanya untuk jenis berkuota (Cuti Tahunan & WFH).
                        'balance' => $balances->get($item->employee_id.'-'.$item->leave_type_id),
                    ];
                });
            })
            ->sortByDesc(fn ($row) => $row['model']->created_at)
            ->values();
    }

    /** Rantai approver lengkap per karyawan — dipakai untuk "step X dari Y" dan siapa berikutnya. */
    private function chainsFor(array $employeeIds, string $type): Collection
    {
        return EmployeeApprover::whereIn('employee_id', $employeeIds)
            ->where('request_type', $type)
            ->orderBy('step_order')
            ->with('approver:id,full_name')
            ->get()
            ->groupBy('employee_id');
    }

    /**
     * Saldo cuti tahun berjalan, dikunci ke pasangan karyawan+jenis cuti. Approver perlu tahu
     * sisa kuota SEBELUM menyetujui — bukan setelahnya saat saldo sudah terpotong.
     */
    private function leaveBalancesFor(Collection $items): Collection
    {
        $berkuota = $items->filter(fn ($item) => LeaveQuota::tracksBalance($item->leaveType));

        if ($berkuota->isEmpty()) {
            return collect();
        }

        return LeaveBalance::whereIn('employee_id', $berkuota->pluck('employee_id')->unique()->all())
            ->whereIn('leave_type_id', $berkuota->pluck('leave_type_id')->unique()->all())
            ->where('year', now()->year)
            ->get()
            ->keyBy(fn ($b) => $b->employee_id.'-'.$b->leave_type_id);
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
        // Kurangi saldo untuk jenis berkuota (Cuti Tahunan & WFH). WFH tidak minus.
        if ($modelClass === LeaveRequest::class) {
            LeaveQuota::deduct($item);
            // Beri tahu delegasi (bila ditunjuk) bahwa pengajuan disetujui.
            \App\Services\LeaveDelegateNotifier::notifyApproved($item);
        }

        // Pengajuan presensi: tulis jam yang disetujui ke tabel Attendance.
        if ($modelClass === AttendanceRequest::class) {
            $item->applyToAttendance();
        }
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
