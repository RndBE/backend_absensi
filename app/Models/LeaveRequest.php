<?php

namespace App\Models;

use App\Jobs\SyncLeaveToDailyJob;
use App\Support\AttendanceLeaveSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;

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

            $becameApproved = $leave->status === 'approved';
            $leftApproved = ! $becameApproved && $leave->getOriginal('status') === 'approved';

            if ($becameApproved) {
                AttendanceLeaveSync::apply($leave);
            } elseif ($leftApproved) {
                // Sebelumnya approved, kini bukan (ditolak/dibatalkan) -> kembalikan.
                AttendanceLeaveSync::revert($leave);
            }

            if ($becameApproved || $leftApproved) {
                self::queueDailySync($leave);
            }
        });
    }

    /**
     * Teruskan hasil approval ke DailyCloseApp supaya hari cuti/sakit tidak ditagih
     * laporan harian di sana.
     *
     * Dispatch-nya dibungkus try/catch dengan sengaja: dengan QUEUE_CONNECTION=sync
     * job dieksekusi saat dispatch, dan Daily yang sedang tidak bisa dihubungi tidak
     * boleh ikut menggagalkan proses ACC-nya. Di queue sungguhan, kegagalan pengiriman
     * ditangani job itu sendiri lewat retry.
     */
    private static function queueDailySync(LeaveRequest $leave): void
    {
        try {
            SyncLeaveToDailyJob::dispatch($leave->id);
        } catch (\Throwable $exception) {
            Log::warning('Gagal mengantre sinkron cuti ke Daily.', [
                'leave_request_id' => $leave->id,
                'message' => $exception->getMessage(),
            ]);
        }
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
