<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    protected $fillable = ['company_id', 'name', 'work_days', 'start_time', 'end_time'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
