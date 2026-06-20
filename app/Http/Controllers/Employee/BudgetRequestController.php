<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\Notification;
use App\Models\TravelZone;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetRequestController extends Controller
{
    private const ITEM_TYPES = [
        'transport' => 'Transportasi',
        'meal' => 'Makan',
        'lumpsum' => 'Lumpsum',
        'entertain' => 'Entertain',
        'operasional' => 'Operasional',
        'lainnya' => 'Lainnya',
    ];

    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $period = $request->query('period') ? Carbon::parse($request->query('period').'-01') : now();

        return view('employee.budget-requests.index', [
            'employee' => $employee,
            'period' => $period,
            'requests' => BudgetRequest::with(['items', 'travelZone'])
                ->where('employee_id', $employee->id)
                ->whereYear('created_at', $period->year)
                ->whereMonth('created_at', $period->month)
                ->latest()
                ->get(),
        ]);
    }

    public function create(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.budget-requests.create', [
            'employee' => $employee,
            'itemTypes' => self::ITEM_TYPES,
            'employees' => Employee::where('is_active', true)
                ->where('id', '!=', $employee->id)
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:budget,reimbursement',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'surat_tugas_no' => 'nullable|string|max:255',
            'surat_tugas_date' => 'nullable|date',
            'distance_km' => 'nullable|integer|min:0',
            'participants' => 'nullable|array',
            'participants.*' => 'exists:employees,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:5120',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:transport,meal,lumpsum,entertain,operasional,lainnya',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.amount' => 'required|numeric|min:1',
            'item_attachments_*' => 'nullable|array',
            'item_attachments_*.*' => 'file|max:5120',
        ]);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        DB::beginTransaction();
        try {
            $distanceKm = $request->filled('distance_km') ? (int) $validated['distance_km'] : null;
            $travelZone = $distanceKm !== null ? TravelZone::findByKm($distanceKm) : null;

            $budgetRequest = BudgetRequest::create([
                'employee_id' => $employee->id,
                'type' => $validated['type'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => 'pending',
                'current_step' => 1,
                'total_amount' => 0,
                'surat_tugas_no' => $validated['surat_tugas_no'] ?? null,
                'surat_tugas_date' => $validated['surat_tugas_date'] ?? null,
                'distance_km' => $distanceKm,
                'travel_zone_id' => $travelZone?->id,
            ]);

            $total = 0;
            foreach ($validated['items'] as $index => $itemData) {
                $amount = (float) $itemData['amount'];
                $item = $budgetRequest->items()->create([
                    'type' => $itemData['type'],
                    'description' => $itemData['description'] ?? '',
                    'amount' => $amount,
                ]);
                $total += $amount;

                foreach ($request->file("item_attachments_{$index}", []) as $file) {
                    $path = $file->store('budget-attachments', 'public');
                    $item->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            $budgetRequest->update(['total_amount' => $total]);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('budget-attachments', 'public');
                $budgetRequest->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }

            $budgetRequest->participants()->sync($validated['participants'] ?? []);

            DB::commit();

            $this->notifyFirstApprover($employee, $budgetRequest);

            return redirect()
                ->route('employee.budget-requests.index')
                ->with('success', 'Pengajuan anggaran berhasil dikirim.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Gagal mengirim pengajuan anggaran: '.$e->getMessage());
        }
    }

    public function show(Request $request, int $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.budget-requests.show', [
            'budgetRequest' => BudgetRequest::with([
                'items.attachments',
                'attachments',
                'participants:id,full_name',
                'approvalLogs.approver:id,full_name',
                'travelZone',
            ])
                ->where('employee_id', $employee->id)
                ->findOrFail($id),
        ]);
    }

    private function notifyFirstApprover(Employee $employee, BudgetRequest $budgetRequest): void
    {
        $firstApprover = EmployeeApprover::getApproverAt($employee->id, 'budget', 1);
        if (! $firstApprover) {
            return;
        }

        $typeLabel = $budgetRequest->type === 'budget' ? 'Anggaran' : 'Reimbursement';
        $notification = Notification::create([
            'employee_id' => $firstApprover->id,
            'title' => "Pengajuan {$typeLabel} Baru",
            'message' => "{$employee->full_name} mengajukan {$typeLabel}: {$budgetRequest->title}",
            'type' => 'approval',
            'reference_type' => BudgetRequest::class,
            'reference_id' => $budgetRequest->id,
        ]);

        FcmService::sendToEmployee($firstApprover, $notification->title, $notification->message, [
            'type' => 'approval',
            'reference_type' => 'budget',
            'reference_id' => (string) $budgetRequest->id,
        ]);
    }
}
