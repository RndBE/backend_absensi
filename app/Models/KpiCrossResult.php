<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiCrossResult extends Model
{
    /** Batas penyesuaian atasan langsung, Bab 7.9 Langkah 4. */
    public const MAX_ADJUSTMENT = 1.0;

    /**
     * Kalau seorang atasan menyesuaikan lebih dari sekian bawahan ke arah yang sama,
     * penyesuaiannya butuh persetujuan atasan di atasnya — mencegah manajer menaikkan
     * seluruh timnya demi melindungi divisinya.
     */
    public const APPROVAL_TRIGGER_COUNT = 3;

    protected $fillable = [
        'kpi_period_id', 'department_id', 'employee_id',
        'score_a_raw', 'score_a_corrected', 'score_b_raw', 'score_b_corrected',
        'score_collaboration', 'superior_adjustment', 'adjustment_reason', 'adjusted_by',
        'adjusted_at', 'adjustment_needs_approval', 'adjustment_approved_by',
        'quorum_met', 'assessor_count', 'division_count', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'score_a_raw' => 'decimal:4',
            'score_a_corrected' => 'decimal:4',
            'score_b_raw' => 'decimal:4',
            'score_b_corrected' => 'decimal:4',
            'score_collaboration' => 'decimal:4',
            'superior_adjustment' => 'decimal:2',
            'quorum_met' => 'boolean',
            'adjustment_needs_approval' => 'boolean',
            'adjusted_at' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'adjusted_by');
    }

    /** Baris hasil divisi (Lapis A kolektif), bukan hasil individu. */
    public function scopeDivisionLevel(Builder $query): Builder
    {
        return $query->whereNull('employee_id');
    }

    public function scopeIndividual(Builder $query): Builder
    {
        return $query->whereNotNull('employee_id');
    }

    public function isDivisionResult(): bool
    {
        return $this->employee_id === null;
    }

    /**
     * Skor yang dipakai mengisi indikator KPI: skor kolaborasi ditambah penyesuaian atasan,
     * dijepit ke rentang 1–5. Penyesuaian yang masih menunggu persetujuan belum berlaku.
     */
    public function effectiveScore(): ?float
    {
        if ($this->score_collaboration === null) {
            return null;
        }

        $score = (float) $this->score_collaboration;

        if ($this->superior_adjustment !== null && ! $this->adjustmentPending()) {
            $score += (float) $this->superior_adjustment;
        }

        return round(max(1.0, min(5.0, $score)), 4);
    }

    public function adjustmentPending(): bool
    {
        return $this->adjustment_needs_approval && $this->adjustment_approved_by === null;
    }
}
