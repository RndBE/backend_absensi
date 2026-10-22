<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\EmployeeApprover;
use App\Models\Lpj;
use App\Models\LpjItem;
use App\Models\Notification;
use App\Models\TravelReport;
use App\Services\FcmService;
use App\Support\LpjPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LPJ (laporan pertanggungjawaban anggaran) untuk app mobile: daftar, detail, dan buat.
 * Aturannya sama dengan portal employee (Employee\LpjController). Angka uang dikirim sebagai
 * angka, bukan teks desimal, supaya app tidak perlu menebak bentuknya.
 */
class LpjController extends Controller
{
    public function index(Request $request)
    {
        $lpjs = Lpj::with(['budgetRequest:id,title,total_amount', 'travelReport:id,destination_city'])
            ->where('employee_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Lpj $lpj) => LpjPresenter::summary($lpj));

        return response()->json(['success' => true, 'data' => $lpjs]);
    }

    public function show(Request $request, $id)
    {
        $lpj = Lpj::with(LpjPresenter::DETAIL_RELATIONS)
            ->where('employee_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => LpjPresenter::detail($lpj)]);
    }

    /**
     * Anggaran yang bisa dibuatkan LPJ: disetujui/cair, milik sendiri atau diikuti sebagai peserta,
     * dan belum punya LPJ dari karyawan ini. Beserta kategori pengeluaran untuk formulirnya.
     */
    public function availableRequests(Request $request)
    {
        $requests = $this->eligibleBudgets($request->user()->id)
            ->whereDoesntHave('lpj', fn ($q) => $q->where('employee_id', $request->user()->id))
            ->with(['items', 'travelReport:id,budget_request_id,destination_city'])
            ->latest()
            ->get()
            ->map(fn (BudgetRequest $budget) => [
                'id' => $budget->id,
                'title' => $budget->title,
                'total_amount' => (float) $budget->total_amount,
                'surat_tugas_no' => $budget->surat_tugas_no,
                'destination_city' => $budget->travelReport?->destination_city,
                'items' => $budget->items->map(fn ($item) => [
                    'uraian' => $item->description,
                    'kategori' => $item->type,
                    'kategori_label' => $item->type_label,
                    'jumlah' => (float) $item->amount,
                ])->values(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $requests,
            'categories' => LpjItem::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $employee = $request->user();

        $request->validate([
            'budget_request_id' => 'required|exists:budget_requests,id',
            'nomor_lpj'         => 'nullable|string|max:100',
            'catatan'           => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.kategori'  => 'required|string|in:'.implode(',', array_keys(LpjItem::CATEGORIES)),
            'items.*.uraian'    => 'required|string|max:255',
            'items.*.satuan'    => 'nullable|string|max:50',
            'items.*.volume'    => 'nullable|numeric|min:0',
            'items.*.realisasi' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string|max:255',
            'items.*.bukti_file' => 'nullable|file|mimes:'.implode(',', [...LpjPresenter::PROOF_IMAGE_EXTENSIONS, 'pdf']).'|max:10240',
        ], [
            'items.*.kategori.required' => 'Kategori tiap baris pengeluaran wajib dipilih.',
            'items.*.kategori.in'       => 'Kategori pengeluaran tidak valid.',
            'items.*.uraian.required'   => 'Uraian tiap baris pengeluaran wajib diisi.',
            'items.*.realisasi.required' => 'Jumlah realisasi tiap baris wajib diisi.',
            'items.*.bukti_file.mimes'  => 'Bukti harus berupa gambar (JPG, PNG, GIF, WEBP) atau PDF.',
            'items.*.bukti_file.max'    => 'Ukuran bukti maksimal 10 MB.',
        ]);

        $budgetRequest = $this->eligibleBudgets($employee->id)->find($request->budget_request_id);

        if (! $budgetRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Anggaran tidak ditemukan, belum disetujui, atau bukan milik Anda.',
            ], 422);
        }

        if ($budgetRequest->lpj()->where('employee_id', $employee->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'LPJ untuk anggaran ini sudah dibuat.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $lpj = Lpj::create([
                'budget_request_id' => $budgetRequest->id,
                // LHP yang berlaku, bukan versi yang ditolak lalu diajukan ulang.
                'travel_report_id'  => TravelReport::where('budget_request_id', $budgetRequest->id)
                    ->where('employee_id', $employee->id)
                    ->where('status', '!=', 'rejected')
                    ->latest('id')
                    ->value('id'),
                'employee_id'       => $employee->id,
                'nomor_lpj'         => $request->nomor_lpj,
                'total_anggaran'    => $budgetRequest->total_amount,
                'catatan'           => $request->catatan,
                'status'            => 'pending',
                'current_step'      => 1,
            ]);

            // Bukti dicari dengan kunci baris yang sama dengan isiannya, bukan urutannya.
            $position = 0;
            foreach ($request->input('items', []) as $key => $itemData) {
                $proof = $request->file("items.$key.bukti_file");

                $lpj->items()->create([
                    'uraian'       => $itemData['uraian'],
                    'kategori'     => $itemData['kategori'],
                    'satuan'       => $itemData['satuan'] ?? null,
                    'volume'       => $itemData['volume'] ?? 1,
                    'harga_satuan' => 0,
                    'anggaran'     => 0,
                    'realisasi'    => (float) $itemData['realisasi'],
                    'bukti_file'   => $proof ? $proof->store('lpj-bukti', 'public') : null,
                    'keterangan'   => $itemData['keterangan'] ?? null,
                    'sort_order'   => $position++,
                ]);
            }

            $lpj->recalculate();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat LPJ: '.$e->getMessage(),
            ], 500);
        }

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

        $lpj->load(['budgetRequest:id,title,total_amount', 'travelReport:id,destination_city']);

        return response()->json([
            'success' => true,
            'message' => 'LPJ berhasil diajukan',
            'data'    => LpjPresenter::summary($lpj),
        ], 201);
    }

    /** Anggaran milik karyawan, atau yang diikutinya sebagai peserta, yang sudah disetujui atau cair. */
    private function eligibleBudgets(int $employeeId)
    {
        return BudgetRequest::where(function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)
                    ->orWhereHas('participants', fn ($q) => $q->where('employees.id', $employeeId));
            })
            ->whereIn('status', ['approved', 'paid']);
    }
}
