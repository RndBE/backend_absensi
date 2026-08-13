<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiIndicatorRubric extends Model
{
    /** Predikat baku Bab 6.1, dipakai sebagai label kolom rubrik di form penilaian. */
    public const PREDICATES = [
        5 => 'Jauh melebihi harapan',
        4 => 'Melebihi harapan',
        3 => 'Sesuai harapan',
        2 => 'Di bawah harapan',
        1 => 'Bermasalah serius',
    ];

    protected $fillable = ['kpi_indicator_id', 'score', 'description'];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(KpiIndicator::class, 'kpi_indicator_id');
    }

    public function predicate(): string
    {
        return self::PREDICATES[$this->score] ?? '';
    }
}
