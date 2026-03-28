<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'pre_shift_duration', 'pre_shift_break',
        'post_shift_duration', 'post_shift_break', 'total_duration',
        'reason', 'status', 'current_step',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RequestAttachment::class, 'attachable');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    public function getTotalDurationFormattedAttribute(): string
    {
        $hours = intdiv($this->total_duration, 60);
        $minutes = $this->total_duration % 60;
        return "{$hours}j {$minutes}m";
    }
}
