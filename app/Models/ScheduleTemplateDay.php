<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTemplateDay extends Model
{
    protected $fillable = ['template_id', 'day_of_week', 'shift_id'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class, 'template_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
