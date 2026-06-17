<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LeavePolicy extends Model
{
    protected $fillable = [
        'company_id', 'leave_type_id', 'days_per_year',
        'min_tenure_months', 'max_carry_over', 'is_prorated',
        'eligibility_type', 'is_active',
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

    public function eligibleEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'leave_policy_employees')->withTimestamps();
    }

    public function appliesToAllEmployees(): bool
    {
        return $this->eligibility_type !== 'selected';
    }
}
