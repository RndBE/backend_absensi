<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiCrossAssessor extends Model
{
    /** Bab 7.4 — total 3–5 penilai per divisi. Cukup meredam bias, belum membebani. */
    public const MIN_PER_DIVISION = 3;

    public const MAX_PER_DIVISION = 5;

    protected $fillable = [
        'kpi_period_id', 'department_id', 'employee_id', 'can_assess_individual',
    ];

    protected function casts(): array
    {
        return ['can_assess_individual' => 'boolean'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
