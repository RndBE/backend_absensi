<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\Notification;
use App\Models\TravelReport;
use App\Models\TravelZone;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TravelReportController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.travel-reports.index', [
            'employee' => $employee,
            'reports' => TravelReport::with(['budgetRequest:id,title,total_amount', 'activities'])
                ->where('employee_id', $employee->id)
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.travel-reports.create', [
            'employee' => $employee,
            'availableRequests' => $this->availableBudgetRequests($employee),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $this->assertBudgetAccessible($employee, $validated['budget_request_id'] ?? null);

        DB::beginTransaction();
        try {
            $report = $this->persistReport($request, $employee, $validated);

            DB::commit();

            $this->notifyFirstApprover($employee, $report);

            return redirect()
                ->route('employee.travel-reports.index')
                ->with('success', 'LHP berhasil dikirim.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Gagal mengirim LHP: '.$e->getMessage());
        }
    }

    public function show(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.travel-reports.show', [
            'report' => $this->ownedReport($employee, $id),
        ]);
    }

    public function edit(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $report = $this->ownedReport($employee, $id);

        if (! $this->canEditTravelReport($report)) {
            return redirect()
                ->route('employee.travel-reports.show', $report->id)
                ->with('error', 'LHP tidak dapat diedit karena sedang dalam proses persetujuan lanjutan atau sudah selesai.');
        }

        return view('employee.travel-reports.edit', [
            'employee' => $employee,
            'report' => $report,
            'availableRequests' => $this->availableBudgetRequests($employee, $report),
        ]);
    }

    public function update(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $report = $this->ownedReport($employee, $id);

        if (! $this->canEditTravelReport($report)) {
            return redirect()
                ->route('employee.travel-reports.show', $report->id)
                ->with('error', 'LHP tidak dapat diedit karena sedang dalam proses persetujuan lanjutan atau sudah selesai.');
        }

        $validated = $this->validateRequest($request);

        $this->assertBudgetAccessible($employee, $validated['budget_request_id'] ?? null);

        DB::beginTransaction();
        try {
            $this->persistReport($request, $employee, $validated, $report);

            DB::commit();

            $this->notifyCurrentApprover($employee, $report);

            return redirect()
                ->route('employee.travel-reports.show', $report->id)
                ->with('success', 'LHP berhasil diperbarui.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Gagal memperbarui LHP: '.$e->getMessage());
        }
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'budget_request_id' => 'nullable|exists:budget_requests,id',
            'destination_city' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'surat_tugas_no' => 'nullable|string|max:255',
            'surat_tugas_date' => 'nullable|date',
            'distance_km' => 'nullable|integer|min:0',
            'purpose' => 'required|string',
            'conclusion' => 'required|string',
            'recommendations' => 'nullable|array',
            'recommendations.*' => 'nullable|string',
            'activities' => 'required|array|min:1',
            'activities.*.date' => 'required|date',
            'activities.*.description' => 'required|string',
            'activities.*.results' => 'nullable|array',
            'activities.*.results.*' => 'nullable|string',
            'activities.*.issues' => 'nullable|string',
            'activities.*.conclusion' => 'nullable|string',
            'activity_documents_*' => 'nullable|array',
            'activity_documents_*.*' => 'file|max:5120',
            'activity_captions_*' => 'nullable|array',
            'activity_captions_*.*' => 'nullable|string|max:255',
        ]);
    }

    private function persistReport(Request $request, Employee $employee, array $validated, ?TravelReport $report = null): TravelReport
    {
        $distanceKm = $request->filled('distance_km') ? (int) $validated['distance_km'] : null;
        $travelZone = $distanceKm !== null ? TravelZone::findByKm($distanceKm) : null;
        $recommendations = array_values(array_filter($validated['recommendations'] ?? []));

        $payload = [
            'employee_id' => $employee->id,
            'budget_request_id' => $validated['budget_request_id'] ?? null,
            'surat_tugas_no' => $validated['surat_tugas_no'] ?? null,
            'surat_tugas_date' => $validated['surat_tugas_date'] ?? null,
            'destination_city' => $validated['destination_city'],
            'distance_km' => $distanceKm,
            'travel_zone_id' => $travelZone?->id,
            'departure_date' => $validated['departure_date'],
            'return_date' => $validated['return_date'],
            'purpose' => $validated['purpose'],
            'conclusion' => $validated['conclusion'],
            'recommendations' => count($recommendations) ? $recommendations : null,
        ];

        if ($report) {
            foreach ($report->documents as $document) {
                Storage::disk('public')->delete($document->file_path);
            }
            $report->documents()->delete();
            $report->activities()->delete();
            $report->update($payload);
        } else {
            $report = TravelReport::create($payload + [
                'status' => 'pending',
                'current_step' => 1,
            ]);
        }

        foreach ($validated['activities'] as $index => $activityData) {
            $results = array_values(array_filter($activityData['results'] ?? []));
            $activity = $report->activities()->create([
                'activity_date' => $activityData['date'],
                'description' => $activityData['description'],
                'results' => count($results) ? $results : null,
                'issues' => $activityData['issues'] ?? null,
                'conclusion' => $activityData['conclusion'] ?? null,
                'sort_order' => $index,
            ]);

            foreach ($request->file("activity_documents_{$index}", []) as $docIndex => $file) {
                $path = $file->store('travel-report-docs', 'public');
                $report->documents()->create([
                    'travel_report_activity_id' => $activity->id,
                    'file_path' => $path,
                    'caption' => $request->input("activity_captions_{$index}.{$docIndex}"),
                    'activity_date' => $activityData['date'],
                    'sort_order' => $docIndex,
                ]);
            }
        }

        return $report->load(['activities.documents', 'documents']);
    }

    private function ownedReport(Employee $employee, int $id): TravelReport
    {
        return TravelReport::with([
            'budgetRequest:id,title,total_amount,surat_tugas_no,surat_tugas_date,distance_km,travel_zone_id',
            'budgetRequest.travelZone',
            'travelZone',
            'activities.documents',
            'documents',
            'approvalLogs.approver:id,full_name',
        ])
            ->where('employee_id', $employee->id)
            ->findOrFail($id);
    }

    private function availableBudgetRequests(Employee $employee, ?TravelReport $report = null)
    {
        return BudgetRequest::where(function ($query) use ($employee) {
                // Budget milik sendiri ATAU budget yang men-tag user sebagai peserta tim.
                $query->where('employee_id', $employee->id)
                    ->orWhereHas('participants', fn ($q) => $q->where('employees.id', $employee->id));
            })
            ->whereIn('status', ['approved', 'paid'])
            ->where(function ($query) use ($report, $employee) {
                // "Sudah punya LHP" dinilai per-user: tiap peserta bikin LHP-nya sendiri.
                $query->whereDoesntHave('travelReport', fn ($q) => $q->where('employee_id', $employee->id));
                if ($report?->budget_request_id) {
                    $query->orWhere('id', $report->budget_request_id);
                }
            })
            ->latest()
            ->get(['id', 'title', 'total_amount', 'surat_tugas_no', 'surat_tugas_date', 'distance_km', 'travel_zone_id']);
    }

    /**
     * Pastikan employee berhak memakai budget request tsb (pemilik atau peserta yang di-tag).
     */
    private function assertBudgetAccessible(Employee $employee, ?int $budgetRequestId): void
    {
        if (! $budgetRequestId) {
            return;
        }

        $accessible = BudgetRequest::where('id', $budgetRequestId)
            ->where(function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->orWhereHas('participants', fn ($q) => $q->where('employees.id', $employee->id));
            })
            ->exists();

        if (! $accessible) {
            throw ValidationException::withMessages([
                'budget_request_id' => 'Anda tidak berhak memakai budget request ini.',
            ]);
        }
    }

    private function canEditTravelReport(TravelReport $report): bool
    {
        if (! in_array($report->status, ['pending', 'in_review'], true)) {
            return false;
        }

        $hseStep = EmployeeApprover::where('employee_id', $report->employee_id)
            ->where('request_type', 'travel_report')
            ->whereHas('approver', fn ($query) => $query->where('position', 'like', '%HSE%'))
            ->min('step_order');

        if ($hseStep === null) {
            return true;
        }

        // Boleh edit selama LHP belum melewati step HSE (saat pending di awal
        // hingga tepat ditinjau HSE). Setelah lewat HSE, terkunci.
        return (int) $report->current_step <= (int) $hseStep;
    }

    private function notifyFirstApprover(Employee $employee, TravelReport $report): void
    {
        $firstApprover = EmployeeApprover::getApproverAt($employee->id, 'travel_report', 1);
        if (! $firstApprover) {
            return;
        }

        $notification = Notification::create([
            'employee_id' => $firstApprover->id,
            'title' => 'Pengajuan LHP Baru',
            'message' => "{$employee->full_name} mengajukan LHP ke {$report->destination_city}",
            'type' => 'approval',
            'reference_type' => TravelReport::class,
            'reference_id' => $report->id,
        ]);

        FcmService::sendToEmployee($firstApprover, $notification->title, $notification->message, [
            'type' => 'approval',
            'reference_type' => 'travel_report',
            'reference_id' => (string) $report->id,
        ]);
    }

    private function notifyCurrentApprover(Employee $employee, TravelReport $report): void
    {
        $currentApprover = EmployeeApprover::getApproverAt($employee->id, 'travel_report', $report->current_step);
        if (! $currentApprover) {
            return;
        }

        $notification = Notification::create([
            'employee_id' => $currentApprover->id,
            'title' => 'LHP Diperbarui',
            'message' => "{$employee->full_name} memperbarui LHP ke {$report->destination_city}.",
            'type' => 'approval',
            'reference_type' => TravelReport::class,
            'reference_id' => $report->id,
        ]);

        FcmService::sendToEmployee($currentApprover, $notification->title, $notification->message, [
            'type' => 'approval',
            'reference_type' => 'travel_report',
            'reference_id' => (string) $report->id,
        ]);
    }
}
