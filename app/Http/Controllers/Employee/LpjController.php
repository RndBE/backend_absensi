<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\Lpj;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LpjController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $lpjs = Lpj::with(['budgetRequest:id,title,total_amount', 'travelReport:id,destination_city'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->get();

        return view('employee.lpj.index', compact('employee', 'lpjs'));
    }

    public function create(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $availableRequests = BudgetRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'paid'])
            ->whereDoesntHave('lpj')
            ->with(['items', 'travelReport:id,budget_request_id,destination_city'])
            ->latest()
            ->get();

        $selectedRequest = null;
        if ($request->filled('budget_request_id')) {
            $selectedRequest = BudgetRequest::with(['items', 'travelReport'])
                ->where('employee_id', $employee->id)
                ->find($request->budget_request_id);
        }

        return view('employee.lpj.create', compact('employee', 'availableRequests', 'selectedRequest'));
    }

    public function store(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $request->validate([
            'budget_request_id' => 'required|exists:budget_requests,id',
            'nomor_lpj'         => 'nullable|string|max:100',
            'catatan'           => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.uraian'    => 'required|string|max:255',
            'items.*.realisasi' => 'required|numeric|min:0',
        ]);

        $budgetRequest = BudgetRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'paid'])
            ->findOrFail($request->budget_request_id);

        if ($budgetRequest->lpj()->exists()) {
            return back()->with('error', 'LPJ untuk anggaran ini sudah dibuat.');
        }

        DB::beginTransaction();
        try {
            $lpj = Lpj::create([
                'budget_request_id' => $budgetRequest->id,
                'travel_report_id'  => $budgetRequest->travelReport?->id,
                'employee_id'       => $employee->id,
                'nomor_lpj'         => $request->nomor_lpj,
                'total_anggaran'    => $budgetRequest->total_amount,
                'catatan'           => $request->catatan,
                'status'            => 'pending',
                'current_step'      => 1,
            ]);

            foreach ($request->items as $i => $itemData) {
                $anggaran  = (float) ($itemData['anggaran'] ?? 0);
                $realisasi = (float) ($itemData['realisasi'] ?? 0);

                $buktFile = null;
                if (isset($itemData['bukti_file']) && $itemData['bukti_file'] instanceof \Illuminate\Http\UploadedFile) {
                    $buktFile = $itemData['bukti_file']->store('lpj-bukti', 'public');
                }

                $lpj->items()->create([
                    'budget_request_item_id' => $itemData['budget_request_item_id'] ?? null,
                    'uraian'                 => $itemData['uraian'],
                    'satuan'                 => $itemData['satuan'] ?? null,
                    'volume'                 => $itemData['volume'] ?? 1,
                    'harga_satuan'           => $itemData['harga_satuan'] ?? 0,
                    'anggaran'               => $anggaran,
                    'realisasi'              => $realisasi,
                    'bukti_file'             => $buktFile,
                    'keterangan'             => $itemData['keterangan'] ?? null,
                    'sort_order'             => $i,
                ]);
            }

            $lpj->recalculate();

            DB::commit();

            $firstApprover = EmployeeApprover::getApproverAt($employee->id, 'lpj', 1);
            if ($firstApprover) {
                $notif = Notification::create([
                    'employee_id'    => $firstApprover->id,
                    'title'          => 'Pengajuan LPJ Baru',
                    'message'        => "{$employee->full_name} mengajukan LPJ untuk {$budgetRequest->title}",
                    'type'           => 'approval',
                    'reference_type' => Lpj::class,
                    'reference_id'   => $lpj->id,
                ]);

                FcmService::sendToEmployee($firstApprover, $notif->title, $notif->message, [
                    'type'           => 'approval',
                    'reference_type' => 'lpj',
                    'reference_id'   => (string) $lpj->id,
                ]);
            }

            return redirect()->route('employee.lpj.show', $lpj->id)
                ->with('success', 'LPJ berhasil diajukan.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(Request $request, $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $lpj = Lpj::with([
            'budgetRequest:id,title,total_amount,surat_tugas_no,surat_tugas_date',
            'travelReport:id,destination_city',
            'items',
            'approvalLogs.approver:id,full_name,photo',
        ])->where('employee_id', $employee->id)->findOrFail($id);

        return view('employee.lpj.show', compact('employee', 'lpj'));
    }
}
