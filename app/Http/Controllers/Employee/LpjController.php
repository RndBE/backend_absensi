<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\Lpj;
use App\Models\Notification;
use App\Models\TravelReport;
use App\Services\FcmService;
use App\Services\LpjExcelExporter;
use App\Support\LpjEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LpjController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        // Termasuk LPJ pemilik pada anggaran tim yang diikuti (memuat biaya bersama), hanya baca.
        $lpjs = Lpj::with(['budgetRequest:id,title,total_amount', 'travelReport:id,destination_city', 'employee:id,full_name'])
            ->visibleTo($employee->id)
            ->latest()
            ->paginate(15);

        return view('employee.lpj.index', compact('employee', 'lpjs'));
    }

    public function create(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        // Tiap orang membuat LPJ untuk biayanya sendiri; biaya bersama dilaporkan pemilik anggaran.
        $availableRequests = BudgetRequest::awaitingLpjFrom($employee->id)
            ->with(['items', 'travelReport:id,budget_request_id,destination_city'])
            ->latest()
            ->get()
            ->each(fn (BudgetRequest $budget) => $this->forAuthor($budget, $employee->id));

        $selectedRequest = null;
        if ($request->filled('budget_request_id')) {
            $selectedRequest = BudgetRequest::awaitingLpjFrom($employee->id)
                ->with(['items', 'travelReport'])
                ->find($request->budget_request_id);
            if ($selectedRequest) {
                $this->forAuthor($selectedRequest, $employee->id);
            }
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
            'items.*.kategori'  => 'required|string|in:'.implode(',', array_keys(\App\Models\LpjItem::CATEGORIES)),
            'items.*.uraian'    => 'required|string|max:255',
            'items.*.realisasi' => 'required|numeric|min:0',
        ], [
            'items.*.kategori.required' => 'Kategori tiap baris pengeluaran wajib dipilih.',
            'items.*.kategori.in'       => 'Kategori pengeluaran tidak valid.',
            'items.*.uraian.required'   => 'Uraian tiap baris pengeluaran wajib diisi.',
            'items.*.realisasi.required'=> 'Jumlah realisasi tiap baris wajib diisi.',
        ]);

        $budgetRequest = BudgetRequest::awaitingLpjFrom($employee->id)
            ->with('items')
            ->find($request->budget_request_id);

        if (! $budgetRequest) {
            $reason = LpjEligibility::whyNot((int) $request->budget_request_id, $employee->id);

            abort_if($reason === null, 404);

            return back()->withInput()->withErrors(['error' => $reason]);
        }

        DB::beginTransaction();
        try {
            $lpj = Lpj::create([
                'budget_request_id' => $budgetRequest->id,
                // LHP yang berlaku (bukan versi yang ditolak lalu diajukan ulang): milik pemilik dulu,
                // bila pemilik tidak ikut berangkat pakai LHP peserta terbaru.
                'travel_report_id'  => TravelReport::where('budget_request_id', $budgetRequest->id)
                    ->where('status', '!=', 'rejected')
                    ->orderByRaw('employee_id = ? desc', [$employee->id])
                    ->latest('id')
                    ->value('id'),
                'employee_id'       => $employee->id,
                'nomor_lpj'         => $request->nomor_lpj,
                // PEMASUKAN dari anggaran: hanya baris biaya milik pembuat LPJ ini.
                'total_anggaran'    => $budgetRequest->itemsForLpj($employee->id)->sum('amount'),
                'catatan'           => $request->catatan,
                'status'            => 'pending',
                'current_step'      => 1,
            ]);

            // PENGELUARAN = baris realisasi bebas (berkategori), tidak diikat ke item anggaran.
            foreach ($request->items as $i => $itemData) {
                $buktFile = null;
                if (isset($itemData['bukti_file']) && $itemData['bukti_file'] instanceof \Illuminate\Http\UploadedFile) {
                    $buktFile = $itemData['bukti_file']->store('lpj-bukti', 'public');
                }

                $lpj->items()->create([
                    'uraian'       => $itemData['uraian'],
                    'kategori'     => $itemData['kategori'] ?? null,
                    'satuan'       => $itemData['satuan'] ?? null,
                    'volume'       => $itemData['volume'] ?? 1,
                    'harga_satuan' => $itemData['harga_satuan'] ?? 0,
                    'anggaran'     => 0, // anggaran kini di sisi pemasukan, bukan per baris realisasi
                    'realisasi'    => (float) ($itemData['realisasi'] ?? 0),
                    'bukti_file'   => $buktFile,
                    'keterangan'   => $itemData['keterangan'] ?? null,
                    'sort_order'   => $i,
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
            'budgetRequest:id,employee_id,title,total_amount,surat_tugas_no,surat_tugas_date',
            'travelReport:id,destination_city',
            'items',
            'approvalLogs.approver:id,full_name,photo',
            'employee:id,full_name',
        ])->visibleTo($employee->id)->findOrFail($id);

        return view('employee.lpj.show', compact('employee', 'lpj'));
    }

    /**
     * Unduh LPJ sebagai Excel (.xlsx), format sama dengan export admin. Peserta anggaran tim
     * juga boleh mengunduh LPJ yang dibuat pemiliknya.
     */
    public function exportExcel(Request $request, $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $lpj = Lpj::visibleTo($employee->id)->findOrFail($id);

        return LpjExcelExporter::download($lpj);
    }

    /**
     * Sisakan di anggaran hanya baris biaya yang dilaporkan $employeeId, dan hitung totalnya
     * (lpj_total). Hanya untuk tampilan formulir; modelnya tidak disimpan.
     */
    private function forAuthor(BudgetRequest $budget, int $employeeId): void
    {
        $items = $budget->itemsForLpj($employeeId);

        $budget->setRelation('items', $items);
        $budget->setAttribute('lpj_total', (float) $items->sum('amount'));
    }
}
