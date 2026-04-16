<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BudgetRequestItem extends Model
{
    protected $fillable = [
        'budget_request_id', 'type', 'description', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function budgetRequest(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RequestAttachment::class, 'attachable');
    }

    /**
     * Human-readable type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'transport' => 'Transportasi',
            'meal' => 'Makan',
            'lumpsum' => 'Lumpsum',
            'entertain' => 'Entertain',
            'operasional' => 'Operasional',
            'lainnya' => 'Lainnya',
            default => ucfirst($this->type),
        };
    }
}
