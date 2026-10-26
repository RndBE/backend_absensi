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
use App\Support\LpjEligibility;
use App\Support\LpjPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LPJ (laporan pertanggungjawaban anggaran) untuk app mobile: daftar, detail, dan buat.
 * Aturannya sama dengan portal employee (Employee\LpjController): tiap orang membuat LPJ untuk
 * biayanya sendiri, biaya bersama dilaporkan pemilik anggaran; peserta tim melihat LPJ pemilik.
 * Angka uang dikirim sebagai angka, bukan teks desimal, supaya app tidak perlu menebak bentuknya.
 */
class LpjController extends Controller
{
    public function index(Request $request)
    {
        $viewerId = $request->user()->id;

        $lpjs = Lpj::with(['budgetRequest:id,title,total_amount', 'travelReport:id,destination_city', 'employee:id,full_name'])
            ->visibleTo($viewerId)
            ->latest()
            ->get()
            ->map(fn (Lpj $lpj) => [...LpjPresenter::summary($lpj), ...$this->authorship($lpj, $viewerId)]);

        return response()->json(['success' => true, 'data' => $lpjs]);
    }

    public function show(Request $request, $id)
    {
        $viewerId = $request->user()->id;

        $lpj = Lpj::with([...LpjPresenter::DETAIL_RELATIONS, 'employee:id,full_name'])
            ->visibleTo($viewerId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => [...LpjPresenter::detail($lpj), ...$this->authorship($lpj, $viewerId)]]);
    }

    /**
     * Anggaran yang bisa dibuatkan LPJ oleh peminta: disetujui/cair, ia punya biaya untuk
     * dilaporkan, dan belum membuat LPJ-nya. `items` dan `total_amount` hanya memuat biaya
     * yang ia laporkan (biaya bersama ada di LPJ pemilik anggaran). Beserta kategori
     * pengeluaran untuk formulirnya.
     */
    public function availableRequests(Request $request)
    {
        $viewerId = $request->user()->id;

        $requests = BudgetRequest::awaitingLpjFrom($viewerId)
            ->with(['items', 'travelReport:id,budget_request_id,destination_city'])
            ->latest()
            ->get()
            ->map(function (BudgetRequest $budget) use ($viewerId) {
                $items = $budget->itemsForLpj($viewerId);

                return [
                    'id' => $budget->id,
                    'title' => $budget->title,
                    'total_amount' => (float) $items->sum('amount'),
                    'surat_tugas_no' => $budget->surat_tugas_no,
                    'destination_city' => $budget->travelReport?->destination_city,
                    'items' => $items->map(fn ($item) => [
                        'uraian' => $item->description,
                        'kategori' => $item->type,
                        'kategori_label' => $item->type_label,
                        'jumlah' => (float) $item->amount,
                    ])->values(),
                ];
            });

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

        $budgetRequest = BudgetRequest::awaitingLpjFrom($employee->id)
            ->with('items')
            ->find($request->budget_request_id);

        if (! $budgetRequest) {
            return response()->json([
                'success' => false,
                'message' => LpjEligibility::whyNot((int) $request->budget_request_id, $employee->id)
                    ?? 'Anggaran tidak ditemukan, belum disetujui, atau Anda bukan pemilik maupun pesertanya.',
            ], 422);
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
                'total_anggaran'    => $budgetRequest->itemsForLpj($employee->id)->sum('amount'),
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

    /** Pembuat LPJ, dan apakah itu yang sedang melihat (false = LPJ tim, hanya bisa dilihat). */
    private function authorship(Lpj $lpj, int $viewerId): array
    {
        return [
            'employee' => ['id' => $lpj->employee?->id, 'full_name' => $lpj->employee?->full_name],
            'is_mine'  => (int) $lpj->employee_id === $viewerId,
        ];
    }
}
