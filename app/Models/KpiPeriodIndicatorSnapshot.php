<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiPeriodIndicatorSnapshot extends Model
{
    protected $fillable = [
        'kpi_period_id', 'kpi_indicator_id', 'kpi_period_level_snapshot_id',
        'category', 'code', 'name', 'description', 'weight',
        'is_core', 'is_auto_filled', 'auto_source', 'rubrics', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_core' => 'boolean',
            'is_auto_filled' => 'boolean',
            'rubrics' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(KpiIndicator::class, 'kpi_indicator_id');
    }

    public function levelSnapshot(): BelongsTo
    {
        return $this->belongsTo(KpiPeriodLevelSnapshot::class, 'kpi_period_level_snapshot_id');
    }

    public function scopeManuallyScored(Builder $query): Builder
    {
        return $query->where('is_auto_filled', false);
    }

    public function scopeCore(Builder $query): Builder
    {
        return $query->where('is_core', true);
    }

    /** Teks rubrik untuk satu skor, sesuai keadaan saat periode dibuka. */
    public function rubricFor(int $score): ?string
    {
        return $this->rubrics[$score] ?? $this->rubrics[(string) $score] ?? null;
    }

    /**
     * Bukti wajib untuk skor ekstrem (Bab 1.3). Skor 3 dianggap sesuai standar dan
     * tidak perlu dijelaskan.
     */
    public function requiresEvidence(int $score): bool
    {
        return $score >= 4 || $score <= 2;
    }
}
