<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'interest_rate',
        'interest_amount',
        'total_repayable',
        'installment_count',
        'monthly_installment',
        'installment_schedule',
        'remaining_amount',
        'start_period',
        'purpose',
        'status',
        'disbursed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_repayable' => 'decimal:2',
            'installment_count' => 'integer',
            'monthly_installment' => 'decimal:2',
            'installment_schedule' => 'array',
            'remaining_amount' => 'decimal:2',
            'disbursed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
