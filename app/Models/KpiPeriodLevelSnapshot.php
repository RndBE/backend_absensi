<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiPeriodLevelSnapshot extends Model
{
    protected $fillable = [
        'kpi_period_id', 'kpi_level_id', 'code', 'name', 'is_assessed',
        'weight_excellence', 'weight_contribution', 'weight_leadership', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_assessed' => 'boolean',
            'weight_excellence' => 'decimal:2',
            'weight_contribution' => 'decimal:2',
            'weight_leadership' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    /** Master asalnya. Boleh null bila level sudah dihapus setelah periode berjalan. */
    public function level(): BelongsTo
    {
        return $this->belongsTo(KpiLevel::class, 'kpi_level_id');
    }

    public function indicatorSnapshots(): HasMany
    {
        return $this->hasMany(KpiPeriodIndicatorSnapshot::class)->orderBy('sort_order');
    }

    /** @return array<string, float> */
    public function categoryWeights(): array
    {
        return [
            KpiLevel::CATEGORY_EXCELLENCE => (float) $this->weight_excellence,
            KpiLevel::CATEGORY_CONTRIBUTION => (float) $this->weight_contribution,
            KpiLevel::CATEGORY_LEADERSHIP => (float) $this->weight_leadership,
        ];
    }
}
