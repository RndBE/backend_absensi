<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiDivisionRelation extends Model
{
    /** Batas Bab 7.3 — di atas 6 mitra, mutu pengisian anjlok. */
    public const MIN_PARTNERS = 3;

    public const MAX_PARTNERS = 6;

    protected $fillable = ['company_id', 'department_id', 'partner_department_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'partner_department_id');
    }
}
