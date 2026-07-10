<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

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
     * Jika jam masuk diisi, keterlambatan (is_late) dihitung ulang sesuai jadwal hari itu.
     */
    public function applyToAttendance(): void
    {
        $values = ['status' => 'present'];

        if ($this->clock_in) {
            $values['clock_in'] = $this->clock_in;

            // Hitung ulang keterlambatan berdasarkan jam mulai shift pada tanggal tsb.
            $shiftStart = $this->shiftStartTime();
            if ($shiftStart) {
                $clockInMinute = Carbon::parse($this->clock_in)->startOfMinute();
                $startMinute = Carbon::parse($shiftStart)->startOfMinute();
                $values['is_late'] = $clockInMinute->gt($startMinute);
            } else {
                // Tidak ada jadwal masuk (libur/off/tanpa jadwal) → tidak dihitung terlambat.
                $values['is_late'] = false;
            }
        }
        if ($this->clock_out) {
            $values['clock_out'] = $this->clock_out;
        }

        Attendance::updateOrCreate(
            ['employee_id' => $this->employee_id, 'date' => $this->date->toDateString()],
            $values,
        );
    }

    /**
     * Jam mulai shift karyawan pada tanggal pengajuan ini.
     * Mengikuti urutan resolusi yang sama dengan presensi normal:
     * override per-tanggal (schedule_assignments) → libur → template mingguan → work schedule legacy.
     * Null = tidak ada jadwal masuk (off/libur).
     */
    private function shiftStartTime(): ?string
    {
        $employee = $this->employee()->first();
        if (! $employee) {
            return null;
        }

        $date = $this->date;
        $dateStr = $date->toDateString();

        // 1. Override per tanggal.
        $override = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('date', $dateStr)
            ->first();
        if ($override?->shift) {
            return $override->shift->is_off ? null : $override->shift->start_time;
        }

        // Libur perusahaan.
        $isHoliday = Holiday::where('company_id', $employee->company_id)
            ->where('date', $dateStr)
            ->exists();
        if ($isHoliday) {
            return null;
        }

        // 2. Template mingguan yang berlaku pada tanggal itu (riwayat).
        $shift = $employee->scheduleTemplateOn($date)?->getShiftForDay($date->dayOfWeekIso);
        if ($shift && ! $shift->is_off) {
            return $shift->start_time;
        }

        // 3. Work schedule legacy.
        if ($employee->work_schedule_id) {
            $employee->loadMissing('workSchedule');

            return $employee->workSchedule?->start_time;
        }

        return null;
    }
}
