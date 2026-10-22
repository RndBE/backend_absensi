<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
use App\Models\Employee;
use App\Support\AssignmentLetterApprovalFlow;
use App\Support\AssignmentLetterPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paraf digital surat tugas untuk aplikasi mobile: detail dan keputusan (setuju/tolak) atasan L3/L2
 * peserta, HRD, dan GA HSE. Antreannya ikut di `GET /approvals` (kunci `assignment_letter`).
 * Alur dan aturannya sama dengan portal karyawan (Employee\AssignmentLetterApprovalController) dan
 * ada di AssignmentLetterApprovalFlow; `id` di sini adalah id surat, bukan id kotak parafnya.
 */
class AssignmentLetterApprovalController extends Controller
{
    public function show(Request $request, int $id)
    {
        $employee = $request->user();
        $letter = $this->letterFor($employee, $id);

        return response()->json(['success' => true, 'data' => $this->detail($letter, $employee)]);
    }

    /**
     * Setujui satu paraf. Bila pemberi persetujuan memegang beberapa paraf di surat ini (mis.
     * superadmin sebagai HRD dan GA HSE), `approval_id` menentukan yang mana; tanpa itu, yang pertama.
     * Tanda tangan digital pemberi persetujuan disalin ke kotak parafnya.
     */
    public function approve(Request $request, int $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'approval_id' => 'nullable|integer',
        ]);

        $employee = $request->user();
        $letter = $this->letterFor($employee, $id);
        $mine = $this->choose($letter, $employee, $request->input('approval_id'));

        if ($mine instanceof JsonResponse) {
            return $mine;
        }

        $result = AssignmentLetterApprovalFlow::approve($mine, $employee, $request->input('notes'));

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => filled($employee->signature)
                ? 'Surat tugas disetujui. Tanda tangan Anda dicatat di kolom paraf.'
                : 'Surat tugas disetujui. Anda belum punya tanda tangan digital, jadi kolom paraf hanya memuat nama.',
            'data' => $this->detail($letter, $employee),
        ]);
    }

    /** Tolak satu paraf; alasan wajib supaya pembuat surat tahu apa yang harus diperbaiki. */
    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
            'approval_id' => 'nullable|integer',
        ], [
            'notes.required' => 'Alasan penolakan wajib diisi supaya pembuat surat tahu apa yang harus diperbaiki.',
        ]);

        $employee = $request->user();
        $letter = $this->letterFor($employee, $id);
        $mine = $this->choose($letter, $employee, $request->input('approval_id'));

        if ($mine instanceof JsonResponse) {
            return $mine;
        }

        $result = AssignmentLetterApprovalFlow::reject($mine, $employee, $validated['notes']);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Surat tugas ditolak dan dikembalikan ke pembuatnya.',
            'data' => $this->detail($letter, $employee),
        ]);
    }

    /**
     * Yang boleh membuka: atasan yang ditunjuk di surat itu, pemegang izin HRD/GA HSE yang
     * langkahnya masih menunggu, atau siapa pun yang pernah memutuskan langkah surat itu.
     */
    private function letterFor(Employee $employee, int $id): AssignmentLetter
    {
        $letter = AssignmentLetter::where('company_id', $employee->company_id)
            ->with('approvals')
            ->findOrFail($id);

        $involved = $letter->approvals->contains(fn ($a) => (int) $a->approver_id === (int) $employee->id
            || (int) $a->acted_by === (int) $employee->id);

        abort_unless($involved || AssignmentLetterApprovalFlow::actionableFor($letter, $employee)->isNotEmpty(), 404);

        return $letter;
    }

    /** @return AssignmentLetterApproval|JsonResponse paraf yang diputuskan, atau jawaban galat */
    private function choose(AssignmentLetter $letter, Employee $employee, mixed $approvalId): AssignmentLetterApproval|JsonResponse
    {
        $actionable = AssignmentLetterApprovalFlow::actionableFor($letter, $employee);

        if ($actionable->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada persetujuan yang menunggu Anda untuk surat ini.',
            ], 403);
        }

        if (blank($approvalId)) {
            return $actionable->first();
        }

        $chosen = $actionable->firstWhere('id', (int) $approvalId);

        return $chosen ?? response()->json([
            'success' => false,
            'message' => 'Anda tidak bisa memutuskan langkah ini sekarang (bukan giliran atau bukan hak Anda).',
        ], 422);
    }

    private function detail(AssignmentLetter $letter, Employee $employee): array
    {
        // Keputusan baru saja diambil lewat salinan surat lain; baca ulang supaya status terbarunya ikut.
        $letter->refresh()->load([
            ...AssignmentLetterPresenter::RELATIONS,
            'approvals.approver:id,full_name',
            'approvals.actor:id,full_name',
            'rejecter:id,full_name',
        ]);

        return AssignmentLetterPresenter::approvalDetail(
            $letter,
            $employee,
            AssignmentLetterApprovalFlow::actionableFor($letter, $employee),
        );
    }
}
