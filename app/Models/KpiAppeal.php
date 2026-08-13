<?php

namespace App\Models;

use App\Support\WorkingDays;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hak sanggah atas hasil KPI (Bab 9.4).
 */
class KpiAppeal extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Menunggu Keputusan',
        self::STATUS_ACCEPTED => 'Diterima',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    /** Batas pengajuan: 7 hari kerja sejak hasil diterima. */
    public const SUBMIT_WINDOW_DAYS = 7;

    /** Batas keputusan atasan dua tingkat: 14 hari kerja sejak sanggahan diajukan. */
    public const DECISION_WINDOW_DAYS = 14;

    protected $fillable = [
        'kpi_period_id', 'employee_id', 'reason', 'submitted_at', 'deadline_at',
        'status', 'decided_by', 'decision_note', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'deadline_at' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** Atasan dua tingkat yang mengambil keputusan. */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    /**
     * Tenggat pengajuan: 7 hari kerja sejak hasil diterima karyawan.
     *
     * Hari kerja, bukan hari kalender — hasil yang disampaikan Kamis sebelum libur panjang
     * tidak boleh memakan jatah waktu berpikir karyawan.
     */
    public static function deadlineFor(CarbonInterface|string $receivedAt, ?int $companyId = null): Carbon
    {
        return WorkingDays::add(Carbon::parse($receivedAt), self::SUBMIT_WINDOW_DAYS, $companyId);
    }

    /** Batas waktu putusan, dihitung dari tanggal sanggahan masuk. */
    public function decisionDeadline(): ?Carbon
    {
        if (! $this->submitted_at) {
            return null;
        }

        return WorkingDays::add($this->submitted_at, self::DECISION_WINDOW_DAYS, $this->period?->company_id);
    }

    /** Sanggahan yang melewati batas 14 hari kerja tanpa keputusan. */
    public function isOverdue(): bool
    {
        if ($this->isDecided()) {
            return false;
        }

        $deadline = $this->decisionDeadline();

        return $deadline !== null && Carbon::today()->gt($deadline);
    }

    public function isDecided(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED], true);
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
