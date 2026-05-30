<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BudgetRequest extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'title', 'description',
        'status', 'current_step', 'total_amount',
        'surat_tugas_no', 'surat_tugas_date', 'rejection_reason',
        'distance_km', 'travel_zone_id',
    ];

    protected function casts(): array
    {
        return [
            'surat_tugas_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetRequestItem::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'budget_request_participants')->withTimestamps();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RequestAttachment::class, 'attachable');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    public function travelZone()
    {
        return $this->belongsTo(TravelZone::class);
    }

    public function travelReport()
    {
        return $this->hasOne(TravelReport::class);
    }

    public function payments()
    {
        return $this->hasMany(BudgetPayment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(BudgetPayment::class)->latestOfMany();
    }

    /**
     * Recalculate total_amount from items.
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('amount'),
        ]);
    }
}
