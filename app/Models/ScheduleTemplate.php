<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTemplate extends Model
{
    protected $fillable = ['company_id', 'name', 'description'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ScheduleTemplateDay::class, 'template_id')->orderBy('day_of_week');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'schedule_template_id');
    }

    /**
     * Get the shift for a given day_of_week (1=Mon, 7=Sun)
     */
    public function getShiftForDay(int $dayOfWeek): ?Shift
    {
        $day = $this->days->firstWhere('day_of_week', $dayOfWeek);
        return $day?->shift;
    }
}
