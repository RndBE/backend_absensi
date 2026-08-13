<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiCrossItem extends Model
{
    /** Lapis A menilai divisi sebagai unit; Lapis B menilai individu lintas fungsi. */
    public const LAYER_DIVISION = 'A';

    public const LAYER_INDIVIDUAL = 'B';

    public const LAYERS = [
        self::LAYER_DIVISION => 'Lapis A — Divisi',
        self::LAYER_INDIVIDUAL => 'Lapis B — Individu',
    ];

    protected $fillable = [
        'company_id', 'layer', 'code', 'name', 'question', 'weight', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLayer(Builder $query, string $layer): Builder
    {
        return $query->where('layer', $layer);
    }
}
