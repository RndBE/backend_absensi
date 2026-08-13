<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiIndicator extends Model
{
    /** Skor diambil dari hasil penilaian silang antar divisi, bukan diisi penilai. */
    public const SOURCE_CROSS_ASSESSMENT = 'cross_assessment';

    /** Skor dihitung dari data absensi periode berjalan. */
    public const SOURCE_ATTENDANCE = 'attendance';

    protected $fillable = [
        'company_id', 'kpi_level_id', 'category', 'code', 'name', 'description',
        'weight', 'is_core', 'is_auto_filled', 'auto_source', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_core' => 'boolean',
            'is_auto_filled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(KpiLevel::class, 'kpi_level_id');
    }

    public function rubrics(): HasMany
    {
        return $this->hasMany(KpiIndicatorRubric::class)->orderBy('score', 'desc');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeManuallyScored(Builder $query): Builder
    {
        return $query->where('is_auto_filled', false);
    }

    public function categoryName(): string
    {
        return KpiLevel::CATEGORIES[$this->category] ?? $this->category;
    }

    /** @return array<int, string> skor 1–5 => deskripsi, siap dibekukan ke snapshot periode. */
    public function rubricMap(): array
    {
        return $this->rubrics->pluck('description', 'score')->all();
    }
}
