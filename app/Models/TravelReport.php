<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'surat_tugas_date' => 'date',
        'recommendations' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    public function activities()
    {
        return $this->hasMany(TravelReportActivity::class)->orderBy('sort_order');
    }

    public function documents()
    {
        return $this->hasMany(TravelReportDocument::class)->orderBy('sort_order');
    }

    public function approvalLogs()
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the job position from employee.
     */
    public function getJobPositionAttribute()
    {
        return $this->employee?->position ?? 'Staff';
    }

    /**
     * Get the department name from employee.
     */
    public function getDepartmentNameAttribute()
    {
        return $this->employee?->department?->name ?? '-';
    }

    /**
     * Calculate trip duration in days.
     */
    public function getDurationDaysAttribute()
    {
        if ($this->departure_date && $this->return_date) {
            return $this->departure_date->diffInDays($this->return_date) + 1;
        }
        return 0;
    }
}
