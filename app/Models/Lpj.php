<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Lpj extends Model
{
    protected $fillable = [
        'budget_request_id', 'travel_report_id', 'resubmission_of_id', 'employee_id',
        'nomor_lpj', 'total_anggaran', 'total_realisasi', 'sisa',
        'status', 'current_step', 'rejection_reason', 'catatan', 'kategori_notes', 'transfer_date',
    ];

    protected function casts(): array
    {
        return [
            'total_anggaran'  => 'decimal:2',
            'total_realisasi' => 'decimal:2',
            'sisa'            => 'decimal:2',
            'kategori_notes'  => 'array',
            'transfer_date'   => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function budgetRequest(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    public function travelReport(): BelongsTo
    {
        return $this->belongsTo(TravelReport::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LpjItem::class)->orderBy('sort_order');
    }

    /** Uang yang ditransfer finance untuk LPJ ini; terpisah dari realisasi pengaju. */
    public function disbursements(): HasMany
    {
        return $this->hasMany(LpjDisbursement::class)->orderBy('sort_order')->orderBy('transferred_at');
    }

    /** Log penolakan terakhir — sumber alasan yang ditampilkan ke pengaju dan approver. */
    public function latestRejection(): MorphOne
    {
        return $this->morphOne(ApprovalLog::class, 'approvable')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('action', 'rejected'));
    }

    /** LPJ ditolak yang digantikan oleh LPJ ini (pengajuan ulang). */
    public function resubmissionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmission_of_id');
    }

    /** LPJ pengganti, bila LPJ ini ditolak lalu diajukan ulang. */
    public function resubmission(): HasOne
    {
        return $this->hasOne(self::class, 'resubmission_of_id');
    }

    /** Hanya LPJ ditolak yang belum punya pengganti yang boleh diajukan ulang. */
    public function canBeResubmitted(): bool
    {
        if ($this->status !== 'rejected') {
            return false;
        }

        // Pakai hasil withExists/eager load bila ada, supaya daftar tidak query per baris.
        $hasResubmission = array_key_exists('resubmission_exists', $this->getAttributes())
            ? (bool) $this->resubmission_exists
            : ($this->relationLoaded('resubmission') ? $this->resubmission !== null : $this->resubmission()->exists());

        return ! $hasResubmission;
    }

    /** Koreksi realisasi oleh finance, terbaru di atas. */
    public function revisions(): HasMany
    {
        return $this->hasMany(LpjRevision::class)->latest('id');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    /**
     * LPJ yang boleh dilihat karyawan: buatannya sendiri, semua LPJ anggaran yang ia miliki,
     * dan LPJ pemilik pada anggaran yang ia ikuti sebagai peserta (memuat biaya bersama).
     * Peserta tidak melihat LPJ pribadi peserta lain.
     */
    public function scopeVisibleTo(Builder $query, int $employeeId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('employee_id', $employeeId)
            ->orWhereHas('budgetRequest', fn (Builder $budget) => $budget
                ->where('employee_id', $employeeId)
                ->orWhere(fn (Builder $asParticipant) => $asParticipant
                    ->whereColumn('budget_requests.employee_id', 'lpjs.employee_id')
                    ->whereHas('participants', fn (Builder $p) => $p->where('employees.id', $employeeId)))));
    }

    public function recalculate(): void
    {
        $realisasi = $this->items()->sum('realisasi');
        $this->update([
            'total_realisasi' => $realisasi,
            'sisa'            => $this->total_anggaran - $realisasi,
        ]);
    }
}
