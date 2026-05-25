<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestItem;
use App\Models\EmployeeApprover;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /**
     * List budget requests for the authenticated employee.
     */
    public function index(Request $request)
    {
        $employee = $request->user();

        $query = BudgetRequest::where('employee_id', $employee->id)
            ->with(['items', 'approvalLogs.approver:id,full_name', 'payments.processor:id,full_name']);

        // Filter by month
        if ($request->filled('month')) {
            $date = \Carbon\Carbon::parse($request->month . '-01');
            $query->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Store a new budget/reimbursement request.
     */
    public function store(Request $request)
    {
        $rules = [
            'type' => 'required|in:budget,reimbursement',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|string|in:transport,meal,lumpsum,entertain,operasional,lainnya',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.amount' => 'required|numeric|min:0',
            'surat_tugas_no' => 'nullable|string|max:255',
            'surat_tugas_date' => 'nullable|date',
            'participants' => 'nullable|array',
            'participants.*' => 'exists:employees,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ];

        $items = is_string($request->items) ? json_decode($request->items, true) : $request->items;
        foreach (array_keys(is_array($items) ? $items : []) as $index) {
            $rules["item_attachments_{$index}"] = 'nullable|array';
            $rules["item_attachments_{$index}.*"] = 'file|mimes:jpg,jpeg,png,pdf|max:10240';
        }

        $request->validate($rules);

        $employee = $request->user();

        DB::beginTransaction();
        try {
            $budgetRequest = BudgetRequest::create([
                'employee_id' => $employee->id,
                'type' => $request->type,
                'title' => $request->title,
                'description' => $request->description,
                'status' => 'pending',
                'current_step' => 1,
                'total_amount' => 0,
                'surat_tugas_no' => $request->surat_tugas_no,
                'surat_tugas_date' => $request->surat_tugas_date,
            ]);

            $total = 0;
            $itemsData = $items;

            foreach ($itemsData as $index => $itemData) {
                $item = $budgetRequest->items()->create([
                    'type' => $itemData['type'],
                    'description' => $itemData['description'] ?? '',
                    'amount' => $itemData['amount'],
                ]);

                // Handle per-item attachments
                if ($request->hasFile("item_attachments_{$index}")) {
                    foreach ($request->file("item_attachments_{$index}") as $file) {
                        $path = $file->store('budget-attachments', 'public');
                        $item->attachments()->create([
                            'file_path' => $path,
                            'file_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                        ]);
                    }
                }

                $total += $itemData['amount'];
            }

            $budgetRequest->update(['total_amount' => $total]);

            // Handle main attachments (surat tugas, etc.)
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('budget-attachments', 'public');
                    $budgetRequest->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Sync participants
            if ($request->filled('participants')) {
                $participantIds = is_string($request->participants)
                    ? json_decode($request->participants, true)
                    : $request->participants;
                $budgetRequest->participants()->sync($participantIds);
            }

            DB::commit();

            // Notify first approver
            $firstApprover = EmployeeApprover::getApproverAt($employee->id, 'budget', 1);
            if ($firstApprover) {
                $typeLabel = $request->type === 'budget' ? 'Anggaran' : 'Reimbursement';
                $notification = Notification::create([
                    'employee_id' => $firstApprover->id,
                    'title' => "Pengajuan $typeLabel Baru",
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

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan berhasil dikirim',
                'data' => $budgetRequest->load('items'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim pengajuan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single budget request.
     */
    public function show(Request $request, $id)
    {
        $budgetRequest = BudgetRequest::with([
            'employee:id,full_name,photo,department_id,position',
            'employee.department:id,name',
            'items.attachments',
            'attachments',
            'participants:id,full_name,photo',
            'approvalLogs.approver:id,full_name,photo',
            'payments.processor:id,full_name',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $budgetRequest,
        ]);
    }

    /**
     * Available item types.
     */
    public function itemTypes()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => 'transport', 'label' => 'Transportasi'],
                ['value' => 'meal', 'label' => 'Makan'],
                ['value' => 'lumpsum', 'label' => 'Lumpsum'],
                ['value' => 'entertain', 'label' => 'Entertain'],
                ['value' => 'operasional', 'label' => 'Operasional'],
                ['value' => 'lainnya', 'label' => 'Lainnya'],
            ],
        ]);
    }
}
