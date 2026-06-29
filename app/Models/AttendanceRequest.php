<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AttendanceRequest extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'clock_in', 'clock_out', 'reason', 'status', 'current_step',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
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
     * Terapkan pengajuan presensi yang sudah disetujui ke tabel Attendance:
     * buat/perbarui record presensi pada tanggal terkait dengan jam yang diajukan.
     * Hanya menulis jam yang diisi agar tidak menimpa data lama dengan null.
     */
    public function applyToAttendance(): void
    {
        $values = ['status' => 'present'];

        if ($this->clock_in) {
            $values['clock_in'] = $this->clock_in;
        }
        if ($this->clock_out) {
            $values['clock_out'] = $this->clock_out;
        }

        Attendance::updateOrCreate(
            ['employee_id' => $this->employee_id, 'date' => $this->date->toDateString()],
            $values,
        );
    }
}
