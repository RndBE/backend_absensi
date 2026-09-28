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
        'submission_deadline' => 'date',
        'is_late' => 'boolean',
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

    public function travelZone()
    {
        return $this->belongsTo(TravelZone::class);
    }

    public function getMealAllowanceTotalAttribute(): float
    {
        if (! $this->travelZone || ! $this->duration_days) {
            return 0;
        }

        return (float) $this->travelZone->meal_allowance * $this->duration_days;
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

    /** Log penolakan terakhir — sumber alasan yang ditampilkan ke karyawan dan approver. */
    public function latestRejection()
    {
        return $this->morphOne(ApprovalLog::class, 'approvable')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('action', 'rejected'));
    }

    /** LHP ditolak yang digantikan oleh LHP ini (pengajuan ulang). */
    public function resubmissionOf()
    {
        return $this->belongsTo(TravelReport::class, 'resubmission_of_id');
    }

    /** LHP pengganti, bila LHP ini ditolak lalu diajukan ulang. */
    public function resubmission()
    {
        return $this->hasOne(TravelReport::class, 'resubmission_of_id');
    }

    /**
     * LHP ditolak milik karyawan untuk anggaran ini yang belum diajukan ulang. LHP baru
     * untuk anggaran yang sama otomatis merujuk ke sini, dari kanal mana pun dibuatnya.
     */
    public static function rejectedAwaitingResubmission(int $employeeId, ?int $budgetRequestId): ?self
    {
        if (! $budgetRequestId) {
            return null;
        }

        return static::where('employee_id', $employeeId)
            ->where('budget_request_id', $budgetRequestId)
            ->where('status', 'rejected')
            ->whereDoesntHave('resubmission')
            ->latest('id')
            ->first();
    }

    public function canBeResubmitted(): bool
    {
        if ($this->status !== 'rejected') {
            return false;
        }

        // Pakai hasil withExists/eager load bila ada, supaya daftar tidak query per baris.
        $hasResubmission = array_key_exists('resubmission_exists', $this->getAttributes())
            ? (bool) $this->resubmission_exists
            : ($this->relationLoaded('resubmission') ? $this->resubmission !== null : $this->resubmission()->exists());

        return ! $hasResubmission;
    }

    public function attachments()
    {
        return $this->morphMany(RequestAttachment::class, 'attachable');
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
