<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
use App\Models\AssignmentLetterFacility;
use App\Models\CommunicationDevice;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\TravelReport;
use App\Models\Vehicle;
use App\Support\AdminPermission;
use App\Support\AssignmentLetterApprovalFlow;
use App\Support\AssignmentLetterConflicts;
use App\Support\AssignmentLetterInitialBoxes;
use App\Support\AssignmentLetterNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Surat Tugas: dibuat HRD atau GA&HSE sebagai draft, diterbitkan (nomor keluar, peserta diberi tahu),
 * lalu kotak parafnya diisi tanda tangan digital lewat persetujuan atasan L3/L2 peserta, HRD, dan
 * GA HSE (lihat AssignmentLetterApprovalFlow). Setelah paraf lengkap surat dicetak untuk tanda
 * tangan basah Direktur dan pindaiannya diunggah.
 *
 * Surat terbit masih bisa diubah selama belum ada pindaian bertanda tangan (nomor tetap). Setelah
 * itu surat final: koreksi lewat surat perbaikan bernomor baru. Salah ketik diperbaiki dengan "Batalkan" lalu "Salin ke
 * draft baru", supaya surat yang sudah dibagikan tidak pernah berubah isinya diam-diam.
 */
class AssignmentLetterController extends Controller
{
    public function index(Request $request, AdminPermission $permissions)
    {
        $admin = $this->admin();

        $query = AssignmentLetter::with(['employees', 'destinations', 'approvals'])
            ->where('company_id', $admin->company_id);

        // Surat yang menunggu keputusan admin ini (sebagai HRD/GA HSE/atasan): banner, tab, dan tombol "Putuskan".
        $awaiting = AssignmentLetterApprovalFlow::awaitingLetters($admin);

        if ($request->status === 'awaiting') {
            $query->whereIn('id', $awaiting->pluck('id'));
        } elseif ($request->filled('status') && array_key_exists($request->status, AssignmentLetter::STATUSES)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('year')) {
            $query->whereYear('letter_date', (int) $request->year);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('employees', fn ($e) => $e->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinations', fn ($d) => $d->where('activity', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%"));
            });
        }

        $letters = $query->orderByDesc('letter_date')->orderByDesc('id')->paginate(20)->withQueryString();

        // Perkiraan nomor draft: nomor terakhir per tahun dimuat sekali, bukan per baris.
        $draftLastNumbers = $letters->getCollection()
            ->filter(fn (AssignmentLetter $letter) => $letter->number === null)
            ->map(fn (AssignmentLetter $letter) => $letter->letter_date->year)
            ->unique()
            ->mapWithKeys(fn ($year) => [$year => DocumentSequence::lastNumber($admin->company_id, DocumentSequence::TYPE_ASSIGNMENT_LETTER, $year)])
            ->all();

        $year = now()->year;
        $lastNumber = DocumentSequence::lastNumber($admin->company_id, DocumentSequence::TYPE_ASSIGNMENT_LETTER, $year);

        return view('admin.assignment-letters.index', [
            'letters' => $letters,
            'draftLastNumbers' => $draftLastNumbers,
            'awaitingIds' => $awaiting->pluck('id'),
            'canManage' => $permissions->can($admin, 'assignment_letters.manage'),
            'canIssue' => $permissions->can($admin, 'assignment_letters.issue'),
            'numbering' => [
                'year' => $year,
                'last' => $lastNumber,
                'next' => AssignmentLetter::formatNumber($lastNumber + 1, now()),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $admin = $this->admin();
        $tab = $request->query('tab') === 'revision' ? 'revision' : 'new';
        $source = null;

        if ($tab === 'revision' && $request->filled('source')) {
            $source = AssignmentLetter::where('company_id', $admin->company_id)
                ->where('status', AssignmentLetter::STATUS_ISSUED)
                ->with(['employees', 'destinations', 'facilities'])
                ->find((int) $request->query('source'));

            if (! $source) {
                return redirect()->route('admin.assignment-letters.create', ['tab' => 'revision'])
                    ->with('error', 'Surat tugas yang dipilih tidak ditemukan atau belum terbit.');
            }

            $open = AssignmentLetter::where('revises_letter_id', $source->id)->where('status', AssignmentLetter::STATUS_DRAFT)->first();

            if ($open) {
                return redirect()->route('admin.assignment-letters.show', $open->id)
                    ->with('error', "Surat perbaikan untuk {$source->number} sudah dibuat dan belum terbit. Lanjutkan yang ini.");
            }
        }

        if ($source) {
            // Formulir terisi data surat lama (belum disimpan); tanggal surat hari ini, nomor baru saat terbit.
            $letter = new AssignmentLetter($source->only([
                'city', 'signer_id', 'signer_name', 'signer_position', 'purpose',
                'departure_date', 'work_start_date', 'return_date', 'notes',
            ]) + ['letter_date' => now()->toDateString()]);
            $letter->setRelation('employees', $source->employees);
            $letter->setRelation('destinations', $source->destinations);
            $letter->setRelation('facilities', $source->facilities);
        } else {
            $letter = new AssignmentLetter([
                'letter_date' => now()->toDateString(),
                'city' => config('assignment_letters.default_city'),
                'departure_date' => now()->toDateString(),
                'work_start_date' => now()->toDateString(),
                'return_date' => now()->toDateString(),
            ]);

            $this->applyDefaultSigner($letter, $admin);
        }

        // Daftar surat terbit untuk pemilih di tab "Perbaikan Surat Tugas" (terbaru dulu).
        $issuedLetters = $tab === 'revision'
            ? AssignmentLetter::where('company_id', $admin->company_id)
                ->where('status', AssignmentLetter::STATUS_ISSUED)
                ->with(['employees', 'destinations', 'revisions:id,revises_letter_id,status'])
                ->orderByDesc('letter_date')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
            : collect();

        return view('admin.assignment-letters.create', $this->formData($letter, $admin) + [
            'tab' => $tab,
            'issuedLetters' => $issuedLetters,
            'revisesLetter' => $source,
        ]);
    }

    public function store(Request $request)
    {
        $admin = $this->admin();
        $data = $this->validatedData($request, $admin);

        // Surat perbaikan: formulir tab "Perbaikan Surat Tugas" mengirim surat yang diperbaiki.
        $source = null;
        if ($request->filled('revises_letter_id')) {
            $source = AssignmentLetter::where('company_id', $admin->company_id)
                ->where('status', AssignmentLetter::STATUS_ISSUED)
                ->find((int) $request->input('revises_letter_id'));

            if (! $source) {
                return back()->withInput()->with('error', 'Surat tugas yang diperbaiki tidak ditemukan atau belum terbit.');
            }

            $open = AssignmentLetter::where('revises_letter_id', $source->id)->where('status', AssignmentLetter::STATUS_DRAFT)->first();

            if ($open) {
                return redirect()->route('admin.assignment-letters.show', $open->id)
                    ->with('error', "Surat perbaikan untuk {$source->number} sudah dibuat dan belum terbit. Lanjutkan yang ini.");
            }

            $data['letter']['revises_letter_id'] = $source->id;
            $data['letter']['revision_reason'] = filled($request->input('revision_reason')) ? trim($request->input('revision_reason')) : null;
        }

        $letter = DB::transaction(function () use ($data, $admin) {
            $letter = AssignmentLetter::create($data['letter'] + [
                'company_id' => $admin->company_id,
                'status' => AssignmentLetter::STATUS_DRAFT,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            $this->syncChildren($letter, $data, $admin);

            return $letter;
        });

        return redirect()->route('admin.assignment-letters.show', $letter->id)->with('success', $source
            ? "Draft perbaikan atas {$source->number} disimpan. Terbitkan untuk mendapatkan nomor baru; paraf diminta lagi setelah terbit."
            : 'Draft surat tugas disimpan. Terbitkan untuk mendapatkan nomor surat.');
    }

    public function show($id, AdminPermission $permissions)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin)->load([
            'employees', 'destinations', 'facilities.inspector:id,full_name',
            'creator:id,full_name', 'updater:id,full_name', 'issuer:id,full_name', 'canceller:id,full_name',
            'rejecter:id,full_name', 'submitter:id,full_name',
            'revises:id,number', 'revisions:id,number,status,revises_letter_id',
            'approvals.approver:id,full_name,position', 'approvals.actor:id,full_name',
        ]);

        // Kolom budget_requests.assignment_letter_id datang dari migrasi tahap 2; server yang belum
        // menjalankannya tetap bisa membuka detail surat (tanpa daftar anggaran terkait).
        $budgets = Schema::hasColumn('budget_requests', 'assignment_letter_id')
            ? $letter->budgetRequests()
                ->with(['employee:id,full_name', 'lpj:id,budget_request_id,status'])
                ->latest()
                ->get()
            : collect();

        // Tiap peserta membuat LHP sendiri dari anggaran yang sama, jadi LHP dihitung per anggaran.
        $travelReports = TravelReport::whereIn('budget_request_id', $budgets->pluck('id'))
            ->where('status', '!=', 'rejected')
            ->with('employee:id,full_name')
            ->get(['id', 'budget_request_id', 'employee_id', 'status'])
            ->groupBy('budget_request_id');

        return view('admin.assignment-letters.show', [
            'letter' => $letter,
            'conflicts' => AssignmentLetterConflicts::for($letter),
            'canManage' => $permissions->can($admin, 'assignment_letters.manage'),
            'canIssue' => $permissions->can($admin, 'assignment_letters.issue'),
            'canViewBudgets' => $permissions->can($admin, 'budget.view'),
            'approvalRequired' => AssignmentLetterApprovalFlow::required(),
            'approvalPlan' => $letter->isDraft() ? AssignmentLetterApprovalFlow::plan($letter) : null,
            'approvalState' => $letter->approvalState(),
            'actionableApprovals' => AssignmentLetterApprovalFlow::actionableFor($letter, $admin),
            'budgets' => $budgets,
            'travelReports' => $travelReports,
        ]);
    }

    public function edit($id)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin)->load(['employees', 'destinations', 'facilities']);

        if (! $letter->isEditable()) {
            return redirect()->route('admin.assignment-letters.show', $letter->id)
                ->with('error', $this->lockedMessage($letter));
        }

        return view('admin.assignment-letters.edit', $this->formData($letter, $admin));
    }

    public function update(Request $request, $id)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);

        if (! $letter->isEditable()) {
            return redirect()->route('admin.assignment-letters.show', $letter->id)
                ->with('error', $this->lockedMessage($letter));
        }

        $data = $this->validatedData($request, $admin);

        if ($letter->isIssued() && ($problem = $this->issuedEditProblem($letter, $data))) {
            return back()->withInput()->with('error', $problem);
        }

        DB::transaction(function () use ($letter, $data, $admin) {
            $letter->update($data['letter'] + ['updated_by' => $admin->id]);
            $this->syncChildren($letter, $data, $admin);
        });

        if ($letter->isIssued()) {
            AssignmentLetterApprovalFlow::resync($letter->fresh());
            AssignmentLetterNotifier::updated($letter->fresh());

            return redirect()->route('admin.assignment-letters.show', $letter->id)
                ->with('success', "Surat tugas {$letter->number} diperbarui (nomor tetap; paraf yang sudah masuk tidak diulang). Peserta sudah diberi notifikasi. Cetak ulang sebelum ditandatangani Direktur.");
        }

        return redirect()->route('admin.assignment-letters.show', $letter->id)
            ->with('success', 'Draft surat tugas diperbarui.');
    }

    public function destroy($id)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);

        if (! $letter->isDraft()) {
            return back()->with('error', 'Hanya draft yang bisa dihapus. Surat yang sudah terbit dibatalkan, bukan dihapus.');
        }

        $letter->delete();

        return redirect()->route('admin.assignment-letters.index')
            ->with('success', 'Draft surat tugas dihapus.');
    }

    public function issue($id)
    {
        $admin = $this->admin();

        $result = DB::transaction(function () use ($id, $admin) {
            $letter = AssignmentLetter::where('company_id', $admin->company_id)
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $letter->isDraft()) {
                return ['error' => 'Surat ini sudah '.strtolower($letter->statusLabel()).'.', 'letter' => $letter];
            }

            $letter->load(['employees', 'destinations']);

            if ($letter->employees->isEmpty() || $letter->destinations->isEmpty()) {
                return ['error' => 'Surat belum bisa diterbitkan: isi minimal satu karyawan dan satu tujuan.', 'letter' => $letter];
            }

            // Kotak paraf diminta ke atasan peserta begitu surat terbit, jadi atasannya harus bisa dicari dulu.
            $requiresApproval = AssignmentLetterApprovalFlow::required();
            $problem = $requiresApproval ? AssignmentLetterApprovalFlow::problemMessage(AssignmentLetterApprovalFlow::plan($letter)) : null;

            if ($problem) {
                return ['error' => 'Surat belum bisa diterbitkan. '.$problem, 'letter' => $letter];
            }

            // Status karyawan bisa berubah sejak draft dibuat (resign, jadi outsourcing).
            $eligible = $this->eligibleEmployeeQuery($admin->company_id)
                ->with('department:id,name')
                ->whereIn('id', $letter->employees->pluck('employee_id'))
                ->get()
                ->keyBy('id');

            $ineligible = $letter->employees->reject(fn ($row) => $eligible->has($row->employee_id));
            if ($ineligible->isNotEmpty()) {
                return [
                    'error' => 'Surat belum bisa diterbitkan. Karyawan berikut sudah tidak bisa diberi surat tugas: '
                        .$ineligible->pluck('name')->implode(', ').'. Ubah draft dulu.',
                    'letter' => $letter,
                ];
            }

            // Salinan terakhir dari data karyawan. Setelah ini surat tidak ikut berubah lagi.
            foreach ($letter->employees as $row) {
                $employee = $eligible[$row->employee_id];
                $row->update([
                    'name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'department_name' => $employee->department?->name,
                ]);
            }
            $letter->load('employees');

            $year = $letter->letter_date->year;
            $sequence = DocumentSequence::next($letter->company_id, DocumentSequence::TYPE_ASSIGNMENT_LETTER, $year);

            $letter->update([
                'number' => AssignmentLetter::formatNumber($sequence, $letter->letter_date),
                'sequence' => $sequence,
                'sequence_year' => $year,
                // Surat dengan approval mencetak kolom parafnya dari baris approval; initial_boxes
                // hanya berisi kolom tambahan ketikan HRD, jadi tidak ditimpa label beku.
                'initial_boxes' => $requiresApproval ? $letter->initial_boxes : $letter->initialBoxes(),
                'status' => AssignmentLetter::STATUS_ISSUED,
                'issued_at' => now(),
                'issued_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            if ($requiresApproval) {
                AssignmentLetterApprovalFlow::createRows($letter, $admin);
            }

            return ['letter' => $letter, 'requiresApproval' => $requiresApproval];
        });

        $letter = $result['letter'];

        if (isset($result['error'])) {
            return redirect()->route('admin.assignment-letters.show', $letter->id)->with('error', $result['error']);
        }

        AssignmentLetterNotifier::issued($letter);

        if ($result['requiresApproval']) {
            AssignmentLetterApprovalFlow::start($letter);
        }

        return redirect()->route('admin.assignment-letters.show', $letter->id)->with('success', "Surat tugas terbit dengan nomor {$letter->number}. Peserta sudah diberi notifikasi"
            .($result['requiresApproval'] ? '; paraf diminta ke atasan peserta, HRD, dan GA HSE.' : '.'));
    }

    /** Setelah surat diperbaiki: kotak yang ditolak diminta memutuskan lagi (yang sudah setuju tidak diulang). */
    public function resubmit($id)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);

        $result = AssignmentLetterApprovalFlow::resubmit($letter, $admin);

        return redirect()->route('admin.assignment-letters.show', $letter->id)->with(
            isset($result['error']) ? 'error' : 'success',
            $result['error'] ?? 'Diajukan ulang. Pemberi persetujuan yang menolak sudah diberi notifikasi.'
        );
    }

    /** HRD / GA HSE menyetujui dari admin panel. Atasan L3/L2 menyetujui dari portal karyawan. */
    public function approve(Request $request, $id, $approvalId)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);
        $approval = $letter->approvals()->findOrFail($approvalId);

        $request->validate(['notes' => 'nullable|string|max:1000']);

        $result = AssignmentLetterApprovalFlow::approve($approval, $admin, $request->input('notes'));

        return redirect()->route('admin.assignment-letters.show', $letter->id)->with(
            isset($result['error']) ? 'error' : 'success',
            $result['error'] ?? "Persetujuan {$approval->label} dicatat."
        );
    }

    public function reject(Request $request, $id, $approvalId)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);
        $approval = $letter->approvals()->findOrFail($approvalId);

        $validated = $request->validate(['notes' => 'required|string|max:1000'], [
            'notes.required' => 'Alasan penolakan wajib diisi supaya pembuat surat tahu apa yang harus diperbaiki.',
        ]);

        $result = AssignmentLetterApprovalFlow::reject($approval, $admin, $validated['notes']);

        return redirect()->route('admin.assignment-letters.show', $letter->id)->with(
            isset($result['error']) ? 'error' : 'success',
            $result['error'] ?? 'Surat ditolak dan dikembalikan ke draft.'
        );
    }

    /** Lewati langkah yang macet (atasan berhalangan); kotak parafnya dibiarkan kosong untuk paraf basah. */
    public function skip(Request $request, $id, $approvalId)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);
        $approval = $letter->approvals()->findOrFail($approvalId);

        $validated = $request->validate(['notes' => 'required|string|max:1000'], [
            'notes.required' => 'Alasan melewati persetujuan wajib diisi.',
        ]);

        $result = AssignmentLetterApprovalFlow::skip($approval, $admin, $validated['notes']);

        return redirect()->route('admin.assignment-letters.show', $letter->id)->with(
            isset($result['error']) ? 'error' : 'success',
            $result['error'] ?? "Langkah {$approval->label} dilewati; kotak parafnya kosong untuk paraf basah."
        );
    }

    public function cancel(Request $request, $id)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);

        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        if (! $letter->isIssued()) {
            return back()->with('error', 'Hanya surat yang sudah terbit yang bisa dibatalkan. Draft cukup dihapus.');
        }

        $letter->update([
            'status' => AssignmentLetter::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
            'cancel_reason' => $validated['cancel_reason'],
            'updated_by' => $admin->id,
        ]);

        AssignmentLetterNotifier::cancelled($letter);

        return redirect()->route('admin.assignment-letters.show', $letter->id)
            ->with('success', "Surat tugas {$letter->number} dibatalkan. Nomornya tidak dipakai ulang.");
    }

    public function duplicate($id)
    {
        $admin = $this->admin();
        $source = $this->findLetter($id, $admin)->load(['employees', 'destinations', 'facilities']);

        $copy = $this->copyAsDraft($source, $admin);

        return redirect()->route('admin.assignment-letters.edit', $copy->id)
            ->with('success', 'Salinan dibuat sebagai draft baru. Periksa isinya sebelum diterbitkan.');
    }

    private function copyAsDraft(AssignmentLetter $source, Employee $admin): AssignmentLetter
    {
        return DB::transaction(function () use ($source, $admin) {
            $copy = AssignmentLetter::create($source->only([
                'company_id', 'city', 'signer_id', 'signer_name', 'signer_position', 'purpose',
                'departure_date', 'work_start_date', 'return_date', 'notes',
            ]) + [
                'letter_date' => now()->toDateString(),
                'status' => AssignmentLetter::STATUS_DRAFT,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            // Kolom paraf yang dibekukan surat asal tidak ikut: draft kembali menyusunnya
            // dari divisi peserta, kecuali HRD mengetiknya sendiri.
            foreach (['employees', 'destinations', 'facilities'] as $relation) {
                foreach ($source->{$relation} as $row) {
                    $copy->{$relation}()->create(collect($row->getAttributes())
                        ->except(['id', 'assignment_letter_id', 'created_at', 'updated_at'])
                        ->all());
                }
            }

            return $copy;
        });
    }

    public function print($id)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin)->load(['employees', 'destinations', 'facilities', 'approvals.approver:id,full_name', 'approvals.actor:id,full_name']);

        return view('assignment-letters.print', [
            'letter' => $letter,
            'backUrl' => route('admin.assignment-letters.show', $letter->id),
            'signedUrl' => $letter->hasSignedFile() ? route('admin.assignment-letters.signed.show', $letter->id) : null,
        ]);
    }

    /**
     * Unggah hasil pindai surat yang sudah ditandatangani & diparaf basah. Unggahan baru
     * menggantikan yang lama (mis. pindaian pertama buram).
     */
    public function storeSigned(Request $request, $id)
    {
        $admin = $this->admin();
        $letter = $this->findLetter($id, $admin);

        if (! $letter->isIssued()) {
            return back()->with('error', 'Surat bertanda tangan hanya bisa diunggah untuk surat yang sudah terbit.');
        }

        if ($letter->hasOutstandingApprovals()) {
            return back()->with('error', 'Paraf belum lengkap: masih ada kotak yang menunggu atau ditolak. Selesaikan dulu, atau lewati langkah yang macet, lalu cetak untuk tanda tangan Direktur.');
        }

        $request->validate([
            'signed_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'signed_file.required' => 'Pilih file hasil pindai surat yang sudah ditandatangani.',
            'signed_file.mimes' => 'File harus berupa PDF, JPG, atau PNG.',
            'signed_file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $file = $request->file('signed_file');
        $oldPath = $letter->signed_file_path;

        $letter->update([
            'signed_file_path' => $file->store('assignment-letters/signed', 'local'),
            'signed_file_name' => 'Surat Tugas '.str_replace('/', '-', $letter->number).' (TTD).'.$file->extension(),
            'signed_uploaded_at' => now(),
            'signed_uploaded_by' => $admin->id,
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        } else {
            AssignmentLetterNotifier::signedUploaded($letter);
        }

        // Kembali ke halaman asal: bisa dari daftar surat (tombol "Upload Surat Tugas") atau dari Detail.
        return back()->with('success', $oldPath
            ? "Surat bertanda tangan {$letter->number} diganti."
            : "Surat bertanda tangan {$letter->number} diunggah.");
    }

    public function showSigned($id)
    {
        $letter = $this->findLetter($id, $this->admin());
        abort_unless($letter->hasSignedFile() && Storage::disk('local')->exists($letter->signed_file_path), 404);

        return Storage::disk('local')->response($letter->signed_file_path, $letter->signed_file_name);
    }

    /** Surat yang sudah dibatalkan menyimpan unggahannya sebagai arsip, jadi hanya surat terbit. */
    public function destroySigned($id)
    {
        $letter = $this->findLetter($id, $this->admin());

        if (! $letter->isIssued() || ! $letter->hasSignedFile()) {
            return back()->with('error', 'Tidak ada surat bertanda tangan yang bisa dihapus.');
        }

        Storage::disk('local')->delete($letter->signed_file_path);
        $letter->update([
            'signed_file_path' => null,
            'signed_file_name' => null,
            'signed_uploaded_at' => null,
            'signed_uploaded_by' => null,
        ]);

        return redirect()->route('admin.assignment-letters.show', $letter->id)
            ->with('success', 'Surat bertanda tangan dihapus.');
    }

    /**
     * Peringatan bentrok per karyawan (cuti / surat tugas lain) untuk rentang tanggal di form,
     * supaya HRD melihatnya saat memilih peserta, bukan baru setelah draft disimpan.
     */
    public function availability(Request $request)
    {
        $admin = $this->admin();
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'exclude' => 'nullable|integer',
        ]);

        $employeeIds = $this->eligibleEmployeeQuery($admin->company_id)->pluck('id')->all();

        return response()->json([
            'data' => (object) AssignmentLetterConflicts::byEmployee(
                $admin->company_id,
                $employeeIds,
                $validated['from'],
                $validated['to'],
                isset($validated['exclude'])
                    ? array_filter([(int) $validated['exclude'], AssignmentLetter::whereKey($validated['exclude'])->value('revises_letter_id')])
                    : null
            ),
        ]);
    }

    /** Menyambung nomor dari surat Word: set nomor terakhir yang sudah terpakai tahun ini. */
    public function updateNumbering(Request $request)
    {
        $admin = $this->admin();

        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'last_number' => 'required|integer|min:0|max:9999',
        ], [
            'last_number.required' => 'Nomor terakhir wajib diisi.',
        ]);

        // Nilai form selalu berupa teks ("2026"); Carbon 3 menolak teks di setYear().
        $year = (int) $validated['year'];
        $lastNumber = (int) $validated['last_number'];

        $highestIssued = (int) AssignmentLetter::where('company_id', $admin->company_id)
            ->where('sequence_year', $year)
            ->max('sequence');

        if ($lastNumber < $highestIssued) {
            throw ValidationException::withMessages([
                'last_number' => "Nomor terakhir tidak boleh di bawah {$highestIssued}: nomor itu sudah dipakai surat yang terbit dari sistem.",
            ]);
        }

        DocumentSequence::setLastNumber($admin->company_id, DocumentSequence::TYPE_ASSIGNMENT_LETTER, $year, $lastNumber);

        // Pratinjau nomor memakai bulan berjalan pada tahun yang diatur.
        $next = AssignmentLetter::formatNumber($lastNumber + 1, now()->startOfMonth()->setYear($year));

        return back()->with('success', "Nomor terakhir {$year} diset ke {$lastNumber}. Surat berikutnya akan bernomor {$next}.");
    }

    /**
     * Batasan edit surat terbit: nomor memuat bulan dan tahun surat, dan anggaran yang sudah
     * memakai surat ini menyalin peserta, jadwal, dan jarak darinya.
     */
    private function issuedEditProblem(AssignmentLetter $letter, array $data): ?string
    {
        $newDate = Carbon::parse($data['letter']['letter_date']);

        if ($newDate->month !== $letter->letter_date->month || $newDate->year !== $letter->letter_date->year) {
            return 'Bulan dan tahun tanggal surat harus tetap ('.$letter->letter_date->locale('id')->translatedFormat('F Y')
                .') karena nomor '.$letter->number.' sudah terbit.';
        }

        // Peserta baru yang atasan L3/L2-nya tak bisa dicari tidak bisa dimintai paraf.
        if ($letter->approvals()->exists()) {
            $draftForPlan = new AssignmentLetter(['company_id' => $letter->company_id]);
            $draftForPlan->setRelation('employees', collect($data['employees'])->map(fn ($row) => new \App\Models\AssignmentLetterEmployee(['employee_id' => $row['employee_id']])));

            if ($message = AssignmentLetterApprovalFlow::problemMessage(AssignmentLetterApprovalFlow::plan($draftForPlan))) {
                return $message;
            }
        }

        $hasBudget = Schema::hasColumn('budget_requests', 'assignment_letter_id')
            && $letter->budgetRequests()->where('status', '!=', 'rejected')->exists();

        if (! $hasBudget) {
            return null;
        }

        $letter->loadMissing(['employees', 'destinations']);
        $oldIds = $letter->employees->pluck('employee_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $newIds = collect($data['employees'])->pluck('employee_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $datesChanged = collect(['departure_date', 'work_start_date', 'return_date'])
            ->contains(fn ($field) => $letter->{$field}->toDateString() !== Carbon::parse($data['letter'][$field])->toDateString());
        $oldKm = (float) $letter->destinations->max('distance_km');
        $newKm = (float) collect($data['destinations'])->max('distance_km');

        if ($oldIds !== $newIds || $datesChanged || $oldKm !== $newKm) {
            return 'Peserta, tanggal, dan jarak tidak bisa diubah: sudah ada pengajuan anggaran yang memakai surat ini. '
                .'Tolak atau lepas anggarannya dulu, atau gunakan "Buat Perbaikan" setelah surat ditandatangani.';
        }

        return null;
    }

    private function lockedMessage(AssignmentLetter $letter): string
    {
        return match (true) {
            $letter->isIssued() => 'Surat sudah ada hasil pindai bertanda tangan sehingga final. Untuk koreksi, gunakan "Buat Perbaikan" (nomor baru).',
            default => 'Surat yang dibatalkan tidak bisa diubah. Salin ke draft baru bila perlu.',
        };
    }

    private function admin(): Employee
    {
        return Employee::findOrFail(session('admin_id'));
    }

    private function findLetter($id, Employee $admin): AssignmentLetter
    {
        return AssignmentLetter::where('company_id', $admin->company_id)->findOrFail($id);
    }

    private function eligibleEmployeeQuery(int $companyId)
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('employment_status', config('assignment_letters.eligible_employment_statuses'));
    }

    /**
     * Penanda tangan default: penanda tangan surat terakhir (nama bergelar dan jabatannya
     * sudah diketik HRD di sana), atau Direktur bila belum ada surat.
     */
    private function applyDefaultSigner(AssignmentLetter $letter, Employee $admin): void
    {
        $last = AssignmentLetter::where('company_id', $admin->company_id)
            ->whereNotNull('signer_id')
            ->latest('id')
            ->first();

        if ($last) {
            $letter->fill($last->only(['signer_id', 'signer_name', 'signer_position']));

            return;
        }

        $director = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('position', 'like', '%direk%')->orWhere('position', 'like', '%director%'))
            ->orderBy('job_level')
            ->first();

        if ($director) {
            $letter->fill([
                'signer_id' => $director->id,
                'signer_name' => $director->full_name,
                'signer_position' => 'Direktur',
            ]);
        }
    }

    private function formData(AssignmentLetter $letter, Employee $admin): array
    {
        $eligible = $this->eligibleEmployeeQuery($admin->company_id)
            ->with('department:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'position', 'department_id']);

        $toPickerRow = fn (Employee $e, bool $eligible = true) => [
            'id' => $e->id,
            'name' => $e->full_name,
            'code' => $e->employee_code,
            'position' => $e->position,
            'department' => $e->department?->name,
            'eligible' => $eligible,
        ];

        $employees = $eligible->map(fn ($e) => $toPickerRow($e));

        // Peserta draft lama yang kini tidak memenuhi syarat tetap ditampilkan (bertanda merah)
        // supaya HRD tahu siapa yang harus dikeluarkan dari surat.
        $missingIds = $letter->relationLoaded('employees')
            ? $letter->employees->pluck('employee_id')->diff($eligible->pluck('id'))
            : collect();
        if ($missingIds->isNotEmpty()) {
            $employees = $employees->concat(
                Employee::with('department:id,name')->whereIn('id', $missingIds)->get()
                    ->map(fn ($e) => $toPickerRow($e, false))
            );
        }

        // Penanda tangan dari jajaran Direktur/Manager (job_level 1–2). Kalau job_level belum
        // diisi di data karyawan, semua karyawan yang memenuhi syarat ditampilkan.
        $signerQuery = fn () => $this->eligibleEmployeeQuery($admin->company_id)
            ->orderByRaw('job_level is null')
            ->orderBy('job_level')
            ->orderBy('full_name');
        $signers = $signerQuery()->whereBetween('job_level', [1, 2])->get(['id', 'full_name', 'position', 'job_level']);
        if ($signers->isEmpty()) {
            $signers = $signerQuery()->get(['id', 'full_name', 'position', 'job_level']);
        }
        if ($letter->signer_id && ! $signers->contains('id', $letter->signer_id) && $letter->signer) {
            $signers->push($letter->signer);
        }

        // Atasan level 3/2 tiap karyawan, untuk pratinjau kolom paraf otomatis di form.
        $superiors = AssignmentLetterInitialBoxes::superiorsFor($admin->company_id, $employees->pluck('id')->all());
        $employees = $employees->map(fn (array $row) => $row + ['superiors' => $superiors[$row['id']] ?? []]);

        return [
            'letter' => $letter,
            'pickerEmployees' => $employees->values(),
            'signers' => $signers,
            'vehicles' => Vehicle::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('name')->get(),
            'devices' => CommunicationDevice::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('code')->get(),
            'inspectionSuggestions' => config('assignment_letters.inspection_suggestions', []),
            'initialRows' => $this->initialRows($letter),
        ];
    }

    /** Baris awal form: dari old() setelah gagal validasi, dari draft, atau kosong. */
    private function initialRows(AssignmentLetter $letter): array
    {
        $fromLetter = fn (string $relation, callable $map) => $letter->relationLoaded($relation)
            ? $letter->{$relation}->map($map)->values()->all()
            : [];

        $employees = old('employees', $fromLetter('employees', fn ($row) => [
            'employee_id' => $row->employee_id,
            'position' => $row->position,
        ]));

        $destinations = old('destinations', $fromLetter('destinations', fn ($row) => [
            'activity' => $row->activity,
            'address' => $row->address,
            'distance_km' => $row->distance_km !== null ? (float) $row->distance_km : null,
        ]));

        $facilities = old('facilities', $fromLetter('facilities', fn ($row) => [
            'type' => $row->type,
            'vehicle_id' => $row->vehicle_id,
            'communication_device_id' => $row->communication_device_id,
            'description' => $row->description,
            'inspection_result' => $row->inspection_result,
            'inspected_on' => $row->inspected_on?->toDateString(),
        ]));

        if (! $destinations) {
            $destinations = [['activity' => '', 'address' => '', 'distance_km' => null]];
        }

        if (! $facilities) {
            $facilities = [
                ['type' => AssignmentLetterFacility::TYPE_DEVICE],
                ['type' => AssignmentLetterFacility::TYPE_TRANSPORT],
            ];
        }

        return [
            'employees' => array_values($employees),
            'destinations' => array_values($destinations),
            'facilities' => array_values($facilities),
        ];
    }

    private function validatedData(Request $request, Employee $admin): array
    {
        $validated = $request->validate([
            'letter_date' => 'required|date',
            'city' => 'required|string|max:100',
            'signer_id' => 'required|integer',
            'signer_name' => 'required|string|max:255',
            'signer_position' => 'required|string|max:255',
            'purpose' => 'nullable|string|max:500',
            'departure_date' => 'required|date',
            'work_start_date' => 'required|date|after_or_equal:departure_date',
            'return_date' => 'required|date|after_or_equal:work_start_date',
            'initial_boxes' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'revises_letter_id' => 'nullable|integer',
            'revision_reason' => 'nullable|string|max:500',
            'employees' => 'required|array|min:1|max:50',
            'employees.*.employee_id' => 'required|integer|distinct',
            'employees.*.position' => 'nullable|string|max:255',
            'destinations' => 'required|array|min:1|max:30',
            'destinations.*.activity' => 'required|string|max:1000',
            'destinations.*.address' => 'nullable|string|max:1000',
            'destinations.*.distance_km' => 'nullable|numeric|min:0|max:99999',
            'facilities' => 'nullable|array|max:20',
            'facilities.*.type' => 'required|in:device,transport',
            'facilities.*.vehicle_id' => 'nullable|integer',
            'facilities.*.communication_device_id' => 'nullable|integer',
            'facilities.*.description' => 'nullable|string|max:500',
            'facilities.*.inspection_result' => 'nullable|string|max:255',
            'facilities.*.inspected_on' => 'nullable|date',
        ], [
            'employees.required' => 'Pilih minimal satu karyawan yang ditugaskan.',
            'employees.*.employee_id.distinct' => 'Karyawan yang sama dipilih lebih dari sekali.',
            'destinations.required' => 'Isi minimal satu tujuan/kegiatan.',
            'destinations.*.activity.required' => 'Kegiatan pada setiap baris tujuan wajib diisi.',
            'work_start_date.after_or_equal' => 'Tanggal mulai bekerja tidak boleh sebelum tanggal berangkat.',
            'return_date.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal mulai bekerja.',
        ]);

        $companyId = $admin->company_id;

        if (! Employee::where('company_id', $companyId)->where('is_active', true)->whereKey($validated['signer_id'])->exists()) {
            throw ValidationException::withMessages(['signer_id' => 'Penanda tangan tidak ditemukan atau sudah nonaktif.']);
        }

        return [
            'letter' => [
                'letter_date' => $validated['letter_date'],
                'city' => trim($validated['city']),
                'signer_id' => (int) $validated['signer_id'],
                'signer_name' => trim($validated['signer_name']),
                'signer_position' => trim($validated['signer_position']),
                'purpose' => filled($validated['purpose'] ?? null) ? trim($validated['purpose']) : null,
                'departure_date' => $validated['departure_date'],
                'work_start_date' => $validated['work_start_date'],
                'return_date' => $validated['return_date'],
                'initial_boxes' => $this->parseInitialBoxes($validated['initial_boxes'] ?? null),
                'notes' => $validated['notes'] ?? null,
            ],
            'employees' => $this->participantRows($validated['employees'], $companyId),
            'destinations' => collect($validated['destinations'])->values()->map(fn ($row, $i) => [
                'sort_order' => $i,
                'activity' => trim($row['activity']),
                'address' => filled($row['address'] ?? null) ? trim($row['address']) : null,
                'distance_km' => isset($row['distance_km']) && $row['distance_km'] !== '' ? $row['distance_km'] : null,
            ])->all(),
            'facilities' => $this->facilityRows($validated['facilities'] ?? [], $companyId),
        ];
    }

    private function participantRows(array $rows, int $companyId): array
    {
        $ids = collect($rows)->pluck('employee_id')->map(fn ($id) => (int) $id);

        $employees = $this->eligibleEmployeeQuery($companyId)
            ->with('department:id,name')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rejected = $ids->reject(fn ($id) => $employees->has($id));
        if ($rejected->isNotEmpty()) {
            $names = Employee::whereIn('id', $rejected)->pluck('full_name')->implode(', ') ?: 'ID '.$rejected->implode(', ');

            throw ValidationException::withMessages([
                'employees' => "Karyawan berikut tidak bisa diberi surat tugas (magang, outsourcing, nonaktif, atau beda perusahaan): {$names}.",
            ]);
        }

        return collect($rows)->values()->map(function ($row, $i) use ($employees) {
            $employee = $employees[(int) $row['employee_id']];

            return [
                'employee_id' => $employee->id,
                'sort_order' => $i,
                'name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                // Jabatan boleh ditulis versi surat ("O&P", "RND"); kosong = ikut data karyawan.
                'position' => filled($row['position'] ?? null) ? trim($row['position']) : $employee->position,
                'department_name' => $employee->department?->name,
            ];
        })->all();
    }

    private function facilityRows(array $rows, int $companyId): array
    {
        $vehicles = Vehicle::where('company_id', $companyId)->get()->keyBy('id');
        $devices = CommunicationDevice::where('company_id', $companyId)->get()->keyBy('id');
        $result = [];

        foreach (array_values($rows) as $row) {
            $isTransport = $row['type'] === AssignmentLetterFacility::TYPE_TRANSPORT;
            $vehicleId = $isTransport && filled($row['vehicle_id'] ?? null) ? (int) $row['vehicle_id'] : null;
            $deviceId = ! $isTransport && filled($row['communication_device_id'] ?? null) ? (int) $row['communication_device_id'] : null;

            if (($vehicleId && ! $vehicles->has($vehicleId)) || ($deviceId && ! $devices->has($deviceId))) {
                throw ValidationException::withMessages(['facilities' => 'Kendaraan atau perangkat yang dipilih tidak ditemukan.']);
            }

            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '' || $description === '-') {
                $description = $vehicleId ? $vehicles[$vehicleId]->letterLabel() : ($deviceId ? $devices[$deviceId]->letterLabel() : '');
            }

            $inspection = trim((string) ($row['inspection_result'] ?? ''));

            // Baris kosong dilewati: di surat tercetak "-", sama seperti surat tanpa HT.
            if ($description === '') {
                if ($inspection !== '' && $inspection !== '-') {
                    throw ValidationException::withMessages([
                        'facilities' => 'Ada hasil pemeriksaan tanpa perangkat/transportasi. Isi keterangannya atau hapus barisnya.',
                    ]);
                }

                continue;
            }

            $result[] = [
                'sort_order' => count($result),
                'type' => $row['type'],
                'vehicle_id' => $vehicleId,
                'communication_device_id' => $deviceId,
                'description' => $description,
                'inspection_result' => $inspection !== '' && $inspection !== '-' ? $inspection : null,
                'inspected_on' => filled($row['inspected_on'] ?? null) ? $row['inspected_on'] : null,
            ];
        }

        return $result;
    }

    private function parseInitialBoxes(?string $value): ?array
    {
        $boxes = collect(explode(',', (string) $value))
            ->map(fn ($box) => trim($box))
            ->filter()
            ->values()
            ->all();

        return $boxes ?: null;
    }

    private function syncChildren(AssignmentLetter $letter, array $data, Employee $admin): void
    {
        // Pemeriksa dicatat per baris: kalau hasil pemeriksaannya tidak berubah dari simpanan
        // sebelumnya, pemeriksa lama dipertahankan; kalau baru/berubah, pemeriksanya admin ini.
        $previousInspectors = $letter->facilities()->get()
            ->mapWithKeys(fn ($row) => [$this->inspectionKey([
                'type' => $row->type,
                'description' => $row->description,
                'inspection_result' => $row->inspection_result,
                'inspected_on' => $row->inspected_on?->toDateString(),
            ]) => $row->inspected_by]);

        $letter->employees()->delete();
        $letter->destinations()->delete();
        $letter->facilities()->delete();

        foreach ($data['employees'] as $row) {
            $letter->employees()->create($row);
        }

        foreach ($data['destinations'] as $row) {
            $letter->destinations()->create($row);
        }

        foreach ($data['facilities'] as $row) {
            $row['inspected_by'] = $row['inspection_result']
                ? ($previousInspectors[$this->inspectionKey($row)] ?? $admin->id)
                : null;

            $letter->facilities()->create($row);
        }
    }

    private function inspectionKey(array $row): string
    {
        $date = $row['inspected_on'] ?? null;
        $date = $date ? substr((string) $date, 0, 10) : '';

        return implode('|', [$row['type'], $row['description'], $row['inspection_result'] ?? '', $date]);
    }
}
