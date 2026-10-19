<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterDestination;
use App\Models\AssignmentLetterEmployee;
use App\Models\AssignmentLetterFacility;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Surat tugas untuk aplikasi mobile: hanya surat yang mencantumkan karyawan yang login, dan
 * bukan draft (isinya masih bisa berubah dan belum bernomor).
 *
 * Aplikasi membuka surat & hasil pindai TTD lewat browser, yang tidak membawa token API. Karena
 * itu API memberi tautan bertanda tangan yang berlaku FILE_LINK_MINUTES menit; aplikasi meminta
 * ulang detail surat setiap kali tombol ditekan supaya tautannya selalu baru.
 */
class AssignmentLetterController extends Controller
{
    private const FILE_LINK_MINUTES = 30;

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
            'data' => $letters->map(fn (AssignmentLetter $letter) => $this->payload($letter))->values(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $letter = $this->ownLetters($request->user())
            ->with(['employees', 'destinations', 'facilities'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->payload($letter)]);
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
            'signedUrl' => $this->signedFileUrl($letter),
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

    private function payload(AssignmentLetter $letter): array
    {
        $date = fn ($value) => $value?->toDateString();

        return [
            'id' => $letter->id,
            'number' => $letter->number,
            'status' => $letter->status,
            'status_label' => $letter->statusLabel(),
            'letter_date' => $date($letter->letter_date),
            'city' => $letter->city,
            'signer_name' => $letter->signer_name,
            'signer_position' => $letter->signer_position,
            'title' => (string) $letter->destinations->first()?->activity,
            'purpose_text' => trim(implode("\n", array_filter([$letter->purposeHeading(), ...$letter->destinationSentences()]))),
            'departure_date' => $date($letter->departure_date),
            'work_start_date' => $date($letter->work_start_date),
            'return_date' => $date($letter->return_date),
            'working_days' => $letter->workingDays(),
            'schedule_text' => $letter->scheduleSentence(),
            'destinations' => $letter->destinations->map(fn (AssignmentLetterDestination $d) => [
                'activity' => $d->activity,
                'address' => $d->address,
                'distance_km' => $d->distance_km !== null ? (float) $d->distance_km : null,
            ])->values()->all(),
            'employees' => $letter->employees->map(fn (AssignmentLetterEmployee $e) => [
                'employee_id' => $e->employee_id,
                'name' => $e->name,
                'employee_code' => $e->employee_code,
                'position' => $e->position,
            ])->values()->all(),
            'facilities' => $letter->facilities->map(fn (AssignmentLetterFacility $f) => [
                'type' => $f->type,
                'type_label' => $f->type === AssignmentLetterFacility::TYPE_DEVICE ? 'Perangkat Komunikasi' : 'Transportasi',
                'description' => $f->description,
                'inspection' => $f->inspectionLabel(),
            ])->values()->all(),
            'cancelled_at' => $letter->cancelled_at?->toIso8601String(),
            'cancel_reason' => $letter->cancel_reason,
            'print_url' => $this->link('assignment-letters.link.print', $letter),
            'signed_file' => $letter->hasSignedFile() ? [
                'file_name' => $letter->signed_file_name,
                'is_pdf' => str_ends_with(strtolower((string) $letter->signed_file_name), '.pdf'),
                'uploaded_at' => $letter->signed_uploaded_at?->toIso8601String(),
                'url' => $this->signedFileUrl($letter),
            ] : null,
        ];
    }

    private function signedFileUrl(AssignmentLetter $letter): ?string
    {
        return $letter->hasSignedFile() ? $this->link('assignment-letters.link.signed', $letter) : null;
    }

    /**
     * Tanda tangan dihitung dari path saja (relative) supaya tetap sah walau server berada di
     * balik proxy yang mengubah skema/host; host diambil dari request yang sedang berjalan.
     */
    private function link(string $route, AssignmentLetter $letter): string
    {
        return url(URL::temporarySignedRoute($route, now()->addMinutes(self::FILE_LINK_MINUTES), ['id' => $letter->id], false));
    }
}
