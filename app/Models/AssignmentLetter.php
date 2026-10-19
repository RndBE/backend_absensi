<?php

namespace App\Models;

use App\Support\AssignmentLetterInitialBoxes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AssignmentLetter extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    /** Status yang sampai ke peserta (portal & API); draft dan proses persetujuan hanya terlihat di admin. */
    public const PUBLISHED_STATUSES = [self::STATUS_ISSUED, self::STATUS_CANCELLED];

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ISSUED => 'Terbit',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    private const ROMAN_MONTHS = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    protected $fillable = [
        'company_id', 'revises_letter_id', 'revision_reason', 'number', 'sequence', 'sequence_year',
        'letter_date', 'city', 'signer_id', 'signer_name', 'signer_position',
        'purpose', 'departure_date', 'work_start_date', 'return_date',
        'initial_boxes', 'notes', 'status',
        'submitted_at', 'submitted_by', 'approved_at', 'rejected_at', 'rejected_by', 'rejection_reason',
        'issued_at', 'issued_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'signed_file_path', 'signed_file_name', 'signed_uploaded_at', 'signed_uploaded_by',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'departure_date' => 'date',
            'work_start_date' => 'date',
            'return_date' => 'date',
            'initial_boxes' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'signed_uploaded_at' => 'datetime',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(AssignmentLetterEmployee::class)->orderBy('sort_order')->orderBy('id');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(AssignmentLetterDestination::class)->orderBy('sort_order')->orderBy('id');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(AssignmentLetterFacility::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Surat lama yang diperbaiki surat ini (surat perbaikan bernomor baru). */
    public function revises(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revises_letter_id');
    }

    /** Surat-surat perbaikan atas surat ini. */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revises_letter_id')->orderBy('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AssignmentLetterApproval::class)->orderBy('step_group')->orderBy('sort_order')->orderBy('id');
    }

    public function budgetRequests(): HasMany
    {
        return $this->hasMany(BudgetRequest::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'signer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'issued_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cancelled_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }

    public function signedUploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'signed_uploaded_by');
    }

    /** Hasil pindai surat yang sudah ditandatangani & diparaf basah sudah diunggah. */
    public function hasSignedFile(): bool
    {
        return filled($this->signed_file_path);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Keadaan paraf digital surat terbit: none (tanpa persetujuan), pending (masih ada yang
     * menunggu), rejected (ada yang menolak), complete (semua kotak selesai: setuju/dilewati).
     */
    public function approvalState(): string
    {
        $approvals = $this->relationLoaded('approvals') ? $this->approvals : $this->approvals()->get();

        return match (true) {
            $approvals->isEmpty() => 'none',
            $approvals->contains(fn (AssignmentLetterApproval $a) => $a->status === AssignmentLetterApproval::STATUS_REJECTED) => 'rejected',
            $approvals->contains(fn (AssignmentLetterApproval $a) => $a->isPending()) => 'pending',
            default => 'complete',
        };
    }

    /** Masih ada kotak paraf yang belum selesai (menunggu atau ditolak). */
    public function hasOutstandingApprovals(): bool
    {
        return in_array($this->approvalState(), ['pending', 'rejected'], true);
    }

    /**
     * Isi masih boleh diubah: draft, atau surat terbit yang belum ada hasil pindai bertanda tangan
     * (Direktur belum menandatangani). Pada surat terbit nomor tetap dan persetujuan yang sudah
     * masuk tidak diulang; begitu pindaian diunggah surat final dan koreksinya lewat surat perbaikan.
     */
    public function isEditable(): bool
    {
        return $this->isDraft() || ($this->isIssued() && ! $this->hasSignedFile());
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function formatNumber(int $sequence, CarbonInterface $letterDate): string
    {
        return sprintf(
            '%03d/ST-%s/%s/%d',
            $sequence,
            config('assignment_letters.number_code', 'ATC'),
            self::ROMAN_MONTHS[$letterDate->month],
            $letterDate->year
        );
    }

    /**
     * Nomor yang akan didapat draft ini bila diterbitkan sekarang. Tidak memesan nomor: bisa
     * bergeser kalau surat lain terbit lebih dulu, dan beberapa draft bisa menunjukkan angka sama.
     *
     * @param  int|null  $lastNumber  nomor terakhir tahun surat, bila sudah dimuat pemanggil
     */
    public function estimatedNumber(?int $lastNumber = null): ?string
    {
        if ($this->isIssued() || $this->isCancelled() || ! $this->letter_date) {
            return null;
        }

        $lastNumber ??= DocumentSequence::lastNumber(
            (int) $this->company_id,
            DocumentSequence::TYPE_ASSIGNMENT_LETTER,
            $this->letter_date->year
        );

        return self::formatNumber($lastNumber + 1, $this->letter_date);
    }

    /**
     * Hari kerja dihitung dari mulai bekerja sampai kembali, inklusif, termasuk akhir pekan.
     * Begitu cara HRD menghitungnya di surat Word (berangkat 22, mulai 23, kembali 24 = 2 hari).
     */
    public function workingDays(): int
    {
        if (! $this->work_start_date || ! $this->return_date) {
            return 0;
        }

        return (int) $this->work_start_date->diffInDays($this->return_date) + 1;
    }

    public function scheduleSentence(): string
    {
        $departure = self::formatDate($this->departure_date);
        $workStart = self::formatDate($this->work_start_date);
        $return = self::formatDate($this->return_date);
        $days = $this->workingDays();

        $start = $this->departure_date->isSameDay($this->work_start_date)
            ? "akan berangkat dan memulai pekerjaan pada {$departure}"
            : "akan berangkat pada {$departure}, memulai pekerjaan pada {$workStart}";

        return "Yang bersangkutan {$start} dan akan kembali pada {$return} ({$days} hari kerja).";
    }

    /** Baris pembuka bagian "Untuk"; null berarti tujuan tunggal ditulis menyambung. */
    public function purposeHeading(): ?string
    {
        if (filled($this->purpose)) {
            return 'Untuk '.rtrim(trim($this->purpose), " :").' :';
        }

        return $this->destinations->count() === 1 ? null : 'Untuk :';
    }

    /** @return array<int, string> */
    public function destinationSentences(): array
    {
        $sentences = $this->destinations->map(fn (AssignmentLetterDestination $d) => $d->sentence())->all();

        if ($this->purposeHeading() === null && $sentences) {
            // Huruf awal tidak dikecilkan: kegiatan sering diawali singkatan ("OP perangkat ...").
            $sentences[0] = 'Untuk '.$sentences[0];
        }

        return $sentences;
    }

    /**
     * Label kolom paraf di bawah surat. Tersimpan saat diterbitkan (atau diketik HRD); sebelum
     * itu disusun dari jenjang atasan peserta: level 3, level 2, lalu HRD dan GA HSE.
     *
     * @return array<int, string>
     */
    public function initialBoxes(): array
    {
        if (! empty($this->initial_boxes)) {
            return array_values($this->initial_boxes);
        }

        return AssignmentLetterInitialBoxes::boxes(AssignmentLetterInitialBoxes::superiorsFor(
            (int) $this->company_id,
            $this->employees->pluck('employee_id')->all()
        ));
    }

    /**
     * Kotak paraf untuk cetak: label + keputusan approval-nya (bila ada). Surat yang punya
     * baris approval mencetak kolom atasan/HRD/GA HSE dari approval, ditambah kolom tambahan
     * ketikan HRD (paraf basah). Surat lama tanpa approval tetap memakai label initialBoxes().
     *
     * @return array<int, array{label: string, approval: ?AssignmentLetterApproval}>
     */
    public function initialBoxCells(): array
    {
        $approvals = $this->relationLoaded('approvals') ? $this->approvals : $this->approvals()->get();

        if ($approvals->isEmpty()) {
            return array_map(fn (string $label) => ['label' => $label, 'approval' => null], $this->initialBoxes());
        }

        $cells = $approvals->map(fn (AssignmentLetterApproval $a) => ['label' => $a->label, 'approval' => $a])->all();

        foreach ((array) $this->initial_boxes as $extra) {
            $cells[] = ['label' => (string) $extra, 'approval' => null];
        }

        return $cells;
    }

    public static function formatDate(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->locale('id')->translatedFormat('d F Y');
    }
}
