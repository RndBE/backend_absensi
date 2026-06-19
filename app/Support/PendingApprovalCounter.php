<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class PendingApprovalCounter
{
    private const PENDING_STATUSES = ['pending', 'in_review'];

    private const REQUEST_TABLES = [
        'leave' => 'leave_requests',
        'overtime' => 'overtime_requests',
        'attendance' => 'attendance_requests',
    ];

    public function countForApprover(?Employee $approver): int
    {
        if (! $approver) {
            return 0;
        }

        return $approver->role === 'superadmin'
            ? $this->countCompanyPending($approver)
            : $this->countPersonalPending($approver);
    }

    private function countPersonalPending(Employee $approver): int
    {
        $total = 0;

        foreach (self::REQUEST_TABLES as $requestType => $tableName) {
            $total += DB::table($tableName)
                ->join('employee_approvers', function ($join) use ($tableName, $requestType, $approver) {
                    $join->on('employee_approvers.employee_id', '=', "{$tableName}.employee_id")
                        ->on('employee_approvers.step_order', '=', "{$tableName}.current_step")
                        ->where('employee_approvers.request_type', $requestType)
                        ->where('employee_approvers.approver_id', $approver->id);
                })
                ->join('employees', 'employees.id', '=', "{$tableName}.employee_id")
                ->where('employees.company_id', $approver->company_id)
                ->whereIn("{$tableName}.status", self::PENDING_STATUSES)
                ->count();
        }

        return $total;
    }

    private function countCompanyPending(Employee $admin): int
    {
        $total = 0;

        foreach (self::REQUEST_TABLES as $tableName) {
            $total += DB::table($tableName)
                ->join('employees', 'employees.id', '=', "{$tableName}.employee_id")
                ->where('employees.company_id', $admin->company_id)
                ->whereIn("{$tableName}.status", self::PENDING_STATUSES)
                ->count();
        }

        return $total;
    }
}
