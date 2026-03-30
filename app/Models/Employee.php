<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'employee_code', 'company_id', 'department_id', 'work_schedule_id',
        'schedule_template_id', 'manager_id', 'approver_id', 'full_name',
        'email', 'phone', 'password',
        'birth_place', 'birth_date', 'gender', 'marital_status', 'blood_type',
        'religion', 'nik', 'postal_code', 'ktp_address', 'residential_address',
        'position', 'job_level', 'employment_status', 'join_date', 'resign_date',
        'contract_end_date', 'photo', 'is_active', 'role', 'fcm_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
            'contract_end_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function attendanceRequests(): HasMany
    {
        return $this->hasMany(AttendanceRequest::class);
    }

    public function dataChangeRequests(): HasMany
    {
        return $this->hasMany(DataChangeRequest::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function payroll(): HasMany
    {
        return $this->hasMany(EmployeePayroll::class);
    }

    public function activePayroll()
    {
        return $this->hasOne(EmployeePayroll::class)->where('is_active', true)->latest('effective_date');
    }

    public function payrollComponents(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class);
    }

    public function getMasaKerjaAttribute(): string
    {
        if (!$this->join_date) return '-';
        $diff = $this->join_date->diff(now());
        return "{$diff->y} Tahun {$diff->m} Bulan {$diff->d} Hari";
    }
}
