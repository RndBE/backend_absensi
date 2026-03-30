<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollComponent extends Model
{
    protected $fillable = [
        'name', 'type', 'category', 'default_amount', 'is_taxable', 'is_auto', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_auto' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employeeComponents(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class);
    }
}
