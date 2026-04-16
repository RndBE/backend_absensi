<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetPayment extends Model
{
    protected $fillable = [
        'budget_request_id', 'processed_by', 'amount',
        'payment_method', 'payment_proof', 'reference_no',
        'notes', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function budgetRequest(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'processed_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'transfer' => 'Transfer Bank',
            'cash' => 'Tunai',
            'check' => 'Cek/Giro',
            default => $this->payment_method ?? '-',
        };
    }
}
