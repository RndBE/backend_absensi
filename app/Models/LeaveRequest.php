<?php

namespace App\Models;

use App\Support\AttendanceLeaveSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'total_days', 'reason', 'delegate_to', 'status', 'current_step',
    ];

    protected static function booted(): void
    {
        // Sinkronkan status absensi untuk izin parsial (datang terlambat / pulang cepat)
        // setiap kali status izin berubah, dari jalur approve manapun.
        static::saved(function (LeaveRequest $leave) {
            if (! $leave->wasChanged('status')) {
                return;
            }

            if ($leave->status === 'approved') {
                AttendanceLeaveSync::apply($leave);
            } elseif ($leave->getOriginal('status') === 'approved') {
                // Sebelumnya approved, kini bukan (ditolak/dibatalkan) -> kembalikan.
                AttendanceLeaveSync::revert($leave);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'total_days' => 'decimal:1',
        ];
    }

    public function getTotalDaysLabelAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->total_days, 1, '.', ''), '0'), '.');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delegate_to');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RequestAttachment::class, 'attachable');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }
}
