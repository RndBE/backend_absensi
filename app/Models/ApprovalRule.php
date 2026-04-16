<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRule extends Model
{
    protected $fillable = [
        'company_id', 'request_type', 'requester_min_level', 'requester_max_level',
        'step_order', 'name', 'min_approver_level', 'approver_role', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get active rules for a request type and requester's level, ordered by step.
     */
    public static function getStepsForRequester(int $companyId, string $requestType, int $requesterLevel)
    {
        return static::where('company_id', $companyId)
            ->where('request_type', $requestType)
            ->where('is_active', true)
            ->where(function ($q) use ($requesterLevel) {
                $q->where(function ($q2) use ($requesterLevel) {
                    // Match rules that target this requester level
                    $q2->where('requester_min_level', '<=', $requesterLevel)
                        ->where('requester_max_level', '>=', $requesterLevel);
                })->orWhere(function ($q2) {
                    // Or rules with no level filter (apply to everyone)
                    $q2->whereNull('requester_min_level')
                        ->whereNull('requester_max_level');
                });
            })
            ->orderBy('step_order')
            ->get();
    }

    /**
     * Get total steps for a specific requester level.
     */
    public static function totalStepsForRequester(int $companyId, string $requestType, int $requesterLevel): int
    {
        return static::getStepsForRequester($companyId, $requestType, $requesterLevel)->count();
    }

    /**
     * Get the rule for a specific step, considering requester level.
     */
    public static function getStepRuleForRequester(int $companyId, string $requestType, int $stepOrder, int $requesterLevel): ?self
    {
        $steps = static::getStepsForRequester($companyId, $requestType, $requesterLevel);
        // Steps are ordered, find the Nth one
        return $steps->values()->get($stepOrder - 1);
    }

    /**
     * Legacy methods for backward compatibility (used when requester level is unknown).
     */
    public static function getSteps(int $companyId, string $requestType)
    {
        return static::where('company_id', $companyId)
            ->where('request_type', $requestType)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->get();
    }

    public static function totalSteps(int $companyId, string $requestType): int
    {
        return static::getSteps($companyId, $requestType)->count();
    }

    public static function getStepRule(int $companyId, string $requestType, int $stepOrder): ?self
    {
        return static::where('company_id', $companyId)
            ->where('request_type', $requestType)
            ->where('step_order', $stepOrder)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if an employee can approve this step.
     */
    public function canBeApprovedBy(Employee $employee): bool
    {
        // Check role
        if ($this->approver_role !== 'any' && $employee->role !== $this->approver_role) {
            return false;
        }

        // Check level (approver's level must be <= min_approver_level)
        if ($this->min_approver_level !== null && $employee->job_level !== null) {
            if ($employee->job_level > $this->min_approver_level) {
                return false;
            }
        }

        return true;
    }
}
