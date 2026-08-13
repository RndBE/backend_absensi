<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiAssessment extends Model
{
    public const ROLE_PRIMARY = 'primary';

    public const ROLE_SUPPORTING = 'supporting';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'kpi_period_id', 'employee_id', 'assessor_id', 'assessor_role',
        'weight', 'status', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    /** Karyawan yang dinilai. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessor_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(KpiAssessmentScore::class);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Setelah submit, `score_raw` beku. Pengisian ulang hanya lewat jalur koreksi
     * (`score_adjusted`) yang menuntut alasan tertulis.
     */
    public function isEditable(): bool
    {
        return ! $this->isSubmitted();
    }

    public function roleLabel(): string
    {
        return $this->assessor_role === self::ROLE_PRIMARY ? 'Penilai utama' : 'Penilai pendukung';
    }
}
