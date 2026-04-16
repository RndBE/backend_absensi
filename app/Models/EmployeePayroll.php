<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayroll extends Model
{
    protected $fillable = [
        'employee_id', 'basic_salary',
        'payment_schedule', 'payment_method',
        'bank_name', 'bank_account_number', 'bank_account_name',
        'npwp', 'ptkp_status', 'bpjs_kesehatan', 'bpjs_ketenagakerjaan',
        'effective_date', 'is_active',
        'is_exempt_penalty', 'late_penalty_per_day', 'overtime_multiplier',
        'tax_method', 'pph21_dtp',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'late_penalty_per_day' => 'decimal:2',
            'overtime_multiplier' => 'decimal:2',
            'effective_date' => 'date',
            'is_active' => 'boolean',
            'is_exempt_penalty' => 'boolean',
            'pph21_dtp' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
