<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lpj extends Model
{
    protected $fillable = [
        'budget_request_id', 'travel_report_id', 'employee_id',
        'nomor_lpj', 'total_anggaran', 'total_realisasi', 'sisa',
        'status', 'current_step', 'rejection_reason', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'total_anggaran'  => 'decimal:2',
            'total_realisasi' => 'decimal:2',
            'sisa'            => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function budgetRequest(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    public function travelReport(): BelongsTo
    {
        return $this->belongsTo(TravelReport::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LpjItem::class)->orderBy('sort_order');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    public function recalculate(): void
    {
        $realisasi = $this->items()->sum('realisasi');
        $this->update([
            'total_realisasi' => $realisasi,
            'sisa'            => $this->total_anggaran - $realisasi,
        ]);
    }
}
