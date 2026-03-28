<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicy extends Model
{
    protected $fillable = [
        'company_id', 'leave_type_id', 'days_per_year',
        'min_tenure_months', 'max_carry_over', 'is_prorated', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_prorated' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
