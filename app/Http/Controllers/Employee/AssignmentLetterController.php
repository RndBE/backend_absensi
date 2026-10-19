<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetter;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Karyawan yang tercantum di surat tugas bisa melihat & mencetak suratnya sendiri. Draft tidak
 * ditampilkan: isinya masih bisa berubah dan belum bernomor.
 */
class AssignmentLetterController extends Controller
{
    public function print(Request $request, int $id)
    {
        $letter = $this->findOwnLetter($request, $id)->load(['employees', 'destinations', 'facilities']);

        return view('assignment-letters.print', [
            'letter' => $letter,
            'backUrl' => url()->previous() !== url()->current() ? url()->previous() : route('employee.budget-requests.index'),
            'signedUrl' => $letter->hasSignedFile() ? route('employee.assignment-letters.signed', $letter->id) : null,
        ]);
    }

    /** Hasil pindai surat yang sudah ditandatangani & diparaf basah, diunggah HRD/GA HSE. */
    public function signed(Request $request, int $id)
    {
        $letter = $this->findOwnLetter($request, $id);
        abort_unless($letter->hasSignedFile() && Storage::disk('local')->exists($letter->signed_file_path), 404);

        return Storage::disk('local')->response($letter->signed_file_path, $letter->signed_file_name);
    }

    private function findOwnLetter(Request $request, int $id): AssignmentLetter
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return AssignmentLetter::where('company_id', $employee->company_id)
            ->whereIn('status', AssignmentLetter::PUBLISHED_STATUSES)
            ->whereHas('employees', fn ($q) => $q->where('employee_id', $employee->id))
            ->findOrFail($id);
    }
}
