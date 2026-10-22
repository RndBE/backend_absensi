<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetter;
use App\Models\Employee;
use App\Support\AssignmentLetterApprovalFlow;
use Illuminate\Http\Request;

/**
 * Atasan L3/L2 peserta, HRD, dan GA HSE memutuskan surat tugas dari portal karyawan (HRD dan
 * GA HSE juga bisa dari admin panel, Admin\AssignmentLetterController). Alur dan aturannya ada
 * di AssignmentLetterApprovalFlow.
 */
class AssignmentLetterApprovalController extends Controller
{
    public function show(Request $request, $id)
    {
        $employee = $this->employee($request);
        $letter = $this->letterFor($employee, $id);

        $letter->load([
            'employees', 'destinations', 'facilities',
            'approvals.approver:id,full_name,position', 'approvals.actor:id,full_name',
            'creator:id,full_name', 'rejecter:id,full_name',
        ]);

        return view('employee.assignment-approvals.show', [
            'letter' => $letter,
            'mine' => AssignmentLetterApprovalFlow::actionableFor($letter, $employee)->first(),
            'hasSignature' => filled($employee->signature),
        ]);
    }

    public function print(Request $request, $id)
    {
        $letter = $this->letterFor($this->employee($request), $id)
            ->load(['employees', 'destinations', 'facilities', 'approvals.approver:id,full_name', 'approvals.actor:id,full_name']);

        return view('assignment-letters.print', [
            'letter' => $letter,
            'backUrl' => route('employee.assignment-approvals.show', $letter->id),
            'signedUrl' => null,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $employee = $this->employee($request);
        $letter = $this->letterFor($employee, $id);
        $mine = AssignmentLetterApprovalFlow::actionableFor($letter, $employee)->first();

        $request->validate(['notes' => 'nullable|string|max:1000']);

        if (! $mine) {
            return redirect()->route('employee.assignment-approvals.show', $letter->id)
                ->with('error', 'Tidak ada persetujuan yang menunggu Anda untuk surat ini.');
        }

        $result = AssignmentLetterApprovalFlow::approve($mine, $employee, $request->input('notes'));

        return redirect()->route('employee.approvals.index')->with(
            isset($result['error']) ? 'error' : 'success',
            $result['error'] ?? 'Surat tugas disetujui. Tanda tangan Anda dicatat di kolom paraf.'
        );
    }

    public function reject(Request $request, $id)
    {
        $employee = $this->employee($request);
        $letter = $this->letterFor($employee, $id);
        $mine = AssignmentLetterApprovalFlow::actionableFor($letter, $employee)->first();

        $validated = $request->validate(['notes' => 'required|string|max:1000'], [
            'notes.required' => 'Alasan penolakan wajib diisi supaya pembuat surat tahu apa yang harus diperbaiki.',
        ]);

        if (! $mine) {
            return redirect()->route('employee.assignment-approvals.show', $letter->id)
                ->with('error', 'Tidak ada persetujuan yang menunggu Anda untuk surat ini.');
        }

        $result = AssignmentLetterApprovalFlow::reject($mine, $employee, $validated['notes']);

        return redirect()->route('employee.approvals.index')->with(
            isset($result['error']) ? 'error' : 'success',
            $result['error'] ?? 'Surat tugas ditolak dan dikembalikan ke pembuatnya.'
        );
    }

    private function employee(Request $request): Employee
    {
        return $request->attributes->get('employee');
    }

    /**
     * Yang boleh membuka: atasan yang ditunjuk di surat itu, pemegang izin HRD/GA HSE yang
     * langkahnya masih menunggu, atau siapa pun yang pernah memutuskan langkah surat itu.
     */
    private function letterFor(Employee $employee, $id): AssignmentLetter
    {
        $letter = AssignmentLetter::where('company_id', $employee->company_id)
            ->with('approvals')
            ->findOrFail($id);

        $involved = $letter->approvals->contains(fn ($a) => (int) $a->approver_id === (int) $employee->id
            || (int) $a->acted_by === (int) $employee->id);

        abort_unless($involved || AssignmentLetterApprovalFlow::actionableFor($letter, $employee)->isNotEmpty(), 404);

        return $letter;
    }
}
