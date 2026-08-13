<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiCrossLayerAScore extends Model
{
    protected $fillable = [
        'kpi_cross_layer_a_id', 'item_code', 'score', 'score_corrected', 'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'score_corrected' => 'decimal:4',
        ];
    }

    /** Skor yang dipakai perhitungan: hasil pemangkasan bila ada, kalau tidak skor mentah. */
    public function effectiveScore(): float
    {
        return (float) ($this->score_corrected ?? $this->score);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KpiCrossLayerA::class, 'kpi_cross_layer_a_id');
    }
}
