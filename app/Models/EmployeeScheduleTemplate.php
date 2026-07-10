<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu periode berlakunya sebuah template jadwal bagi seorang karyawan.
 * Batas akhirnya implisit: baris berikutnya (effective_from lebih besar) menggantikannya.
 *
 * @see \App\Models\Employee::scheduleTemplateOn()
 */
class EmployeeScheduleTemplate extends Model
{
    protected $fillable = ['employee_id', 'template_id', 'effective_from'];

    protected function casts(): array
    {
        return ['effective_from' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class, 'template_id');
    }
}
