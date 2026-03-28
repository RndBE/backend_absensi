<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = ['company_id', 'name', 'start_time', 'end_time', 'color', 'is_off', 'sort_order'];

    protected function casts(): array
    {
        return ['is_off' => 'boolean'];
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
        return ($this->start_time ? substr($this->start_time, 0, 5) : '') . ' - ' . ($this->end_time ? substr($this->end_time, 0, 5) : '');
    }
}
