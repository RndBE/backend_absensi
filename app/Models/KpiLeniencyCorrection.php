<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiLeniencyCorrection extends Model
{
    /**
     * Koreksi hanya diterapkan bila selisih rata-rata penilai terhadap rata-rata perusahaan
     * melebihi ambang ini (Bab 7.8d). Di bawahnya, koreksi cuma menambah kerumitan.
     */
    public const THRESHOLD = 0.5;

    protected $fillable = [
        'kpi_period_id', 'assessor_id', 'assessor_mean', 'company_mean', 'correction_value',
    ];

    protected function casts(): array
    {
        return [
            'assessor_mean' => 'decimal:4',
            'company_mean' => 'decimal:4',
            'correction_value' => 'decimal:4',
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
}
