<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeApprover extends Model
{
    protected $fillable = [
        'employee_id', 'request_type', 'step_order', 'approver_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    /**
     * Get ordered approval chain for an employee + request type.
     */
    public static function getChain(int $employeeId, string $requestType)
    {
        return static::where('employee_id', $employeeId)
            ->where('request_type', $requestType)
            ->orderBy('step_order')
            ->with('approver:id,full_name,position,job_level,department_id,photo')
            ->get();
    }

    /**
     * Get the approver at a specific step.
     */
    public static function getApproverAt(int $employeeId, string $requestType, int $step): ?Employee
    {
        $record = static::where('employee_id', $employeeId)
            ->where('request_type', $requestType)
            ->where('step_order', $step)
            ->first();

        return $record ? Employee::find($record->approver_id) : null;
    }

    /**
     * Get total steps for an employee + request type.
     */
    public static function totalSteps(int $employeeId, string $requestType): int
    {
        return static::where('employee_id', $employeeId)
            ->where('request_type', $requestType)
            ->count();
    }

    /**
     * Save a full chain (replace all steps for employee + type).
     */
    public static function saveChain(int $employeeId, string $requestType, array $approverIds): void
    {
        // Delete existing
        static::where('employee_id', $employeeId)
            ->where('request_type', $requestType)
            ->delete();

        // Insert new
        foreach ($approverIds as $index => $approverId) {
            if ($approverId) {
                static::create([
                    'employee_id' => $employeeId,
                    'request_type' => $requestType,
                    'step_order' => $index + 1,
                    'approver_id' => $approverId,
                ]);
            }
        }
    }
}
