<?php

namespace App\Models;

use App\Support\WorkingDays;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class BudgetRequest extends Model
{
    /** Default batas pengumpulan LHP (hari kerja) bila tidak ada override & setting global. */
    public const DEFAULT_LHP_DEADLINE_DAYS = 5;

    protected $fillable = [
        'employee_id', 'type', 'title', 'description',
        'status', 'current_step', 'total_amount',
        'surat_tugas_no', 'surat_tugas_date', 'rejection_reason',
        'distance_km', 'travel_zone_id',
        'departure_date', 'return_date', 'lhp_deadline_days',
    ];

    protected function casts(): array
    {
        return [
            'surat_tugas_date' => 'date',
            'departure_date' => 'date',
            'return_date' => 'date',
            'lhp_deadline_days' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetRequestItem::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'budget_request_participants')->withTimestamps();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RequestAttachment::class, 'attachable');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    public function travelZone()
    {
        return $this->belongsTo(TravelZone::class);
    }

    public function travelReport()
    {
        return $this->hasOne(TravelReport::class);
    }

    public function lpj()
    {
        return $this->hasOne(Lpj::class);
    }

    public function payments()
    {
        return $this->hasMany(BudgetPayment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(BudgetPayment::class)->latestOfMany();
    }

    /**
     * Recalculate total_amount from items.
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('amount'),
        ]);
    }

    /**
     * Batas pengumpulan LHP efektif dalam hari kerja.
     * Prioritas: override per-pengajuan (keringanan HR) → setting global → default konstanta.
     */
    public function effectiveLhpDeadlineDays(): int
    {
        if ($this->lhp_deadline_days) {
            return (int) $this->lhp_deadline_days;
        }

        return (int) Setting::getValue('lhp_deadline_working_days', self::DEFAULT_LHP_DEADLINE_DAYS);
    }

    /**
     * Tanggal batas akhir pengumpulan LHP = tanggal pulang + N hari kerja.
     * Null bila belum ada tanggal pulang (mis. pengajuan non-perjalanan).
     */
    public function lhpDeadlineDate(): ?Carbon
    {
        if (! $this->return_date) {
            return null;
        }

        return WorkingDays::add(
            $this->return_date,
            $this->effectiveLhpDeadlineDays(),
            $this->employee?->company_id,
        );
    }
}
