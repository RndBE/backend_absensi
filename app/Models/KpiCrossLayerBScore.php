<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiCrossLayerBScore extends Model
{
    protected $fillable = [
        'kpi_cross_layer_b_id', 'item_code', 'score', 'score_corrected', 'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'score_corrected' => 'decimal:4',
        ];
    }

    public function effectiveScore(): float
    {
        return (float) ($this->score_corrected ?? $this->score);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KpiCrossLayerB::class, 'kpi_cross_layer_b_id');
    }
}
