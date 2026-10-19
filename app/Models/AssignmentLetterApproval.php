<?php

namespace App\Models;

use App\Support\SignatureImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kotak paraf di surat tugas beserta keputusannya. Lihat migrasi
 * 2026_10_19_100000_create_assignment_letter_approvals untuk aturan alurnya.
 */
class AssignmentLetterApproval extends Model
{
    public const ROLE_LEVEL3 = 'level3';
    public const ROLE_LEVEL2 = 'level2';
    public const ROLE_HRD = 'hrd';
    public const ROLE_GA_HSE = 'ga_hse';

    public const GROUP_SUPERIORS = 1;
    public const GROUP_HRD = 2;
    public const GROUP_GA_HSE = 3;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    /** Dilewati HRD (mis. atasan berhalangan): kotak parafnya dibiarkan kosong untuk paraf basah. */
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'assignment_letter_id', 'role', 'step_group', 'sort_order', 'label', 'approver_id',
        'status', 'acted_by', 'acted_at', 'notes', 'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(AssignmentLetter::class, 'assignment_letter_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acted_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** Langkah sudah beres sehingga grup berikutnya boleh jalan. */
    public function isDone(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SKIPPED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_SKIPPED => 'Dilewati',
            default => 'Menunggu',
        };
    }

    public function signatureUrl(): ?string
    {
        return SignatureImage::url($this->signature_path);
    }
}
