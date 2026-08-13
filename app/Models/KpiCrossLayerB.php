<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu kuesioner Lapis B: seorang penilai menilai satu individu lintas fungsi. */
class KpiCrossLayerB extends Model
{
    protected $table = 'kpi_cross_layer_b';

    protected $fillable = [
        'kpi_period_id', 'assessor_id', 'assessor_department_id', 'target_employee_id',
        'comment', 'is_valid', 'invalid_reason', 'comment_hidden', 'hidden_reason', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
            'comment_hidden' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessor_id');
    }

    public function assessorDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assessor_department_id');
    }

    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(KpiCrossLayerBScore::class, 'kpi_cross_layer_b_id');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true)->whereNotNull('submitted_at');
    }

    public function scopeAnonymous(Builder $query): Builder
    {
        return $query->select([
            'id', 'kpi_period_id', 'target_employee_id', 'comment', 'comment_hidden', 'submitted_at',
        ]);
    }

    public function scoreDeviation(): float
    {
        $values = $this->scores->pluck('score')->map(fn ($s) => (float) $s)->all();
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $count;

        return sqrt($variance);
    }
}
