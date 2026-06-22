<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    protected $fillable = [
        'company_id', 'name', 'start_time', 'end_time',
        'color', 'is_off', 'is_overnight', 'sort_order',
        'work_hours', 'auto_overtime',
    ];

    protected function casts(): array
    {
        return [
            'is_off'        => 'boolean',
            'is_overnight'  => 'boolean',
            'auto_overtime' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function getTimeRangeAttribute(): string
    {
        if ($this->is_off) return 'Libur';
        $end = ($this->end_time ? substr($this->end_time, 0, 5) : '');
        if ($this->is_overnight && $end) $end .= ' +1';
        return ($this->start_time ? substr($this->start_time, 0, 5) : '') . ' - ' . $end;
    }

    /**
     * Hitung jumlah menit lembur otomatis untuk shift ini.
     * Misalnya: shift 12 jam dengan work_hours = 8 → return 240 menit (4 jam).
     * Return 0 jika auto_overtime tidak aktif atau data tidak cukup.
     */
    public function getOvertimeMinutes(): int
    {
        if (!$this->auto_overtime || !$this->work_hours || !$this->start_time || !$this->end_time) {
            return 0;
        }

        $start = Carbon::parse($this->start_time);
        $end   = Carbon::parse($this->end_time);

        // Tangani overnight shift (mis. 22:00 - 06:00)
        if ($end->lte($start)) {
            $end->addDay();
        }

        $totalMinutes    = (int) $start->diffInMinutes($end);
        $standardMinutes = $this->work_hours * 60;

        return max(0, $totalMinutes - $standardMinutes);
    }

    /**
     * Hitung total durasi shift dalam menit.
     */
    public function getShiftDurationMinutes(): int
    {
        if (!$this->start_time || !$this->end_time) return 0;

        $start = Carbon::parse($this->start_time);
        $end   = Carbon::parse($this->end_time);
        if ($end->lte($start)) $end->addDay();

        return (int) $start->diffInMinutes($end);
    }
}
