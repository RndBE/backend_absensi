<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetter;
use App\Models\Employee;
use App\Support\AssignmentLetterPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Surat tugas untuk aplikasi mobile: hanya surat yang mencantumkan karyawan yang login, dan
 * bukan draft (isinya masih bisa berubah dan belum bernomor). Bentuk datanya ada di
 * AssignmentLetterPresenter, yang juga menjelaskan tautan bertanda tangan untuk surat dan
 * hasil pindai TTD.
 */
class AssignmentLetterController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('assignment_letters')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $letters = $this->ownLetters($request->user())
            ->with(['employees', 'destinations', 'facilities'])
            ->orderByDesc('letter_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $letters->map(fn (AssignmentLetter $letter) => AssignmentLetterPresenter::payload($letter))->values(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $letter = $this->ownLetters($request->user())
            ->with(['employees', 'destinations', 'facilities'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => AssignmentLetterPresenter::payload($letter)]);
    }

    /** Halaman cetak surat, dibuka dari tautan bertanda tangan (middleware signed:relative). */
    public function printFromLink(int $id)
    {
        $letter = AssignmentLetter::with(['employees', 'destinations', 'facilities'])
            ->whereIn('status', AssignmentLetter::PUBLISHED_STATUSES)
            ->findOrFail($id);

        return view('assignment-letters.print', [
            'letter' => $letter,
            'backUrl' => null,
            'signedUrl' => AssignmentLetterPresenter::signedFileUrl($letter),
        ]);
    }

    /** Hasil pindai surat bertanda tangan, dibuka dari tautan bertanda tangan. */
    public function signedFileFromLink(int $id)
    {
        $letter = AssignmentLetter::whereIn('status', AssignmentLetter::PUBLISHED_STATUSES)->findOrFail($id);
        abort_unless($letter->hasSignedFile() && Storage::disk('local')->exists($letter->signed_file_path), 404);

        return Storage::disk('local')->response($letter->signed_file_path, $letter->signed_file_name);
    }

    private function ownLetters(Employee $employee): Builder
    {
        return AssignmentLetter::query()
            ->where('company_id', $employee->company_id)
            ->whereIn('status', AssignmentLetter::PUBLISHED_STATUSES)
            ->whereHas('employees', fn ($q) => $q->where('employee_id', $employee->id));
    }
}
