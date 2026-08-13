<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiPeriodCrossItemSnapshot extends Model
{
    protected $fillable = [
        'kpi_period_id', 'kpi_cross_item_id', 'layer', 'code', 'name', 'question', 'weight', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(KpiCrossItem::class, 'kpi_cross_item_id');
    }

    public function scopeLayer(Builder $query, string $layer): Builder
    {
        return $query->where('layer', $layer);
    }
}
