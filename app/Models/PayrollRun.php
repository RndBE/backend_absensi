<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'company_id', 'period', 'status',
        'total_earning', 'total_deduction', 'total_net',
        'finalized_at', 'published_at', 'locked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_earning' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'total_net' => 'decimal:2',
            'finalized_at' => 'datetime',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollRunDetail::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PayrollLog::class)->orderBy('created_at', 'desc');
    }
}
