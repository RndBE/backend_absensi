<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiAssessmentScore extends Model
{
    protected $fillable = [
        'kpi_assessment_id', 'kpi_period_indicator_snapshot_id',
        'score_raw', 'evidence_text',
        'score_adjusted', 'adjusted_by', 'adjusted_reason', 'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'score_raw' => 'integer',
            'score_adjusted' => 'integer',
            'adjusted_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(KpiAssessment::class, 'kpi_assessment_id');
    }

    public function indicatorSnapshot(): BelongsTo
    {
        return $this->belongsTo(KpiPeriodIndicatorSnapshot::class, 'kpi_period_indicator_snapshot_id');
    }

    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'adjusted_by');
    }

    /** Skor yang dipakai perhitungan: hasil koreksi bila ada, kalau tidak skor mentah. */
    public function effectiveScore(): ?int
    {
        return $this->score_adjusted ?? $this->score_raw;
    }

    public function isAdjusted(): bool
    {
        return $this->score_adjusted !== null;
    }

    /** Bukti wajib untuk skor ≥ 4 dan ≤ 2 (Bab 1.3). Skor 3 tidak perlu dijelaskan. */
    public function needsEvidence(): bool
    {
        return $this->score_raw !== null && ($this->score_raw >= 4 || $this->score_raw <= 2);
    }

    public function hasEvidence(): bool
    {
        return trim((string) $this->evidence_text) !== '';
    }
}
