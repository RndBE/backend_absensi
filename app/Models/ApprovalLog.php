<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalLog extends Model
{
    protected $fillable = [
        'approvable_type', 'approvable_id', 'approver_id', 'acted_by_id',
        'action', 'step_order', 'approval_rule_id', 'notes',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    /**
     * The user who actually performed the action when it differs from the
     * configured approver (e.g. a superadmin acting on the approver's behalf).
     */
    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acted_by_id');
    }

    /**
     * Nama pelaku sebenarnya bila berbeda dari approver yang tercatat
     * (kasus superadmin approve menggantikan approver asli). Null jika
     * approver asli yang menekan sendiri.
     */
    public function getViaLabelAttribute(): ?string
    {
        if ($this->acted_by_id && $this->acted_by_id !== $this->approver_id) {
            return $this->actedBy?->full_name ?? 'Superadmin';
        }

        return null;
    }

    public function approvalRule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class);
    }
}
