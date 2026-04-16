<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'overtime_type',
        'planned_start', 'planned_end',
        'pre_shift_duration', 'pre_shift_break',
        'post_shift_duration', 'post_shift_break',
        'break_duration', 'total_duration',
        'approved_duration', 'approved_break',
        'actual_duration', 'shift_end_time',
        'actual_clock_in', 'actual_clock_out',
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

    /**
     * Durasi yang harus dibayar di payroll.
     * Prioritas: actual_duration → (approved_duration - approved_break) → (total_duration - break_duration)
     */
    public function getPayableDuration(): int
    {
        if (!is_null($this->actual_duration)) {
            return $this->actual_duration;
        }

        if (!is_null($this->approved_duration)) {
            $break = $this->approved_break ?? $this->break_duration ?? 0;
            return max(0, $this->approved_duration - $break);
        }

        $break = $this->break_duration ?? 0;
        return max(0, $this->total_duration - $break);
    }

    public function getTotalDurationFormattedAttribute(): string
    {
        $hours = intdiv($this->total_duration, 60);
        $minutes = $this->total_duration % 60;
        return "{$hours}j {$minutes}m";
    }

    public function getActualDurationFormattedAttribute(): string
    {
        if (is_null($this->actual_duration)) return '-';
        $hours = intdiv($this->actual_duration, 60);
        $minutes = $this->actual_duration % 60;
        return "{$hours}j {$minutes}m";
    }

    public function getPayableDurationFormattedAttribute(): string
    {
        $payable = $this->getPayableDuration();
        $hours = intdiv($payable, 60);
        $minutes = $payable % 60;
        return "{$hours}j {$minutes}m";
    }
}
