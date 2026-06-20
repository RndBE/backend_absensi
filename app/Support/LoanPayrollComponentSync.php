<?php

namespace App\Support;

use App\Models\EmployeePayrollComponent;
use App\Models\LoanRequest;
use App\Models\PayrollComponent;

class LoanPayrollComponentSync
{
    public static function syncEmployee(int $employeeId): void
    {
        $component = self::loanComponent();
        if (! $component) {
            return;
        }

        $loans = LoanRequest::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->orderByRaw('case when start_period is null then 1 else 0 end')
            ->orderBy('start_period')
            ->orderBy('id')
            ->get();

        $amount = $loans->sum(fn (LoanRequest $loan) => min(
            (float) $loan->monthly_installment,
            (float) $loan->remaining_amount
        ));

        if ($amount <= 0) {
            EmployeePayrollComponent::where('employee_id', $employeeId)
                ->where('payroll_component_id', $component->id)
                ->update(['is_active' => false]);
            return;
        }

        $startDate = self::startDate($loans->first());

        EmployeePayrollComponent::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'payroll_component_id' => $component->id,
            ],
            [
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => null,
                'is_active' => true,
            ]
        );
    }

    public static function isLoanComponentName(?string $name): bool
    {
        return in_array(self::normalize($name), ['pinjaman', 'potongan_pinjaman'], true);
    }

    private static function loanComponent(): ?PayrollComponent
    {
        return PayrollComponent::query()
            ->get()
            ->first(fn (PayrollComponent $component) => self::isLoanComponentName($component->name)
                && (! isset($component->is_active) || (bool) $component->is_active));
    }

    private static function startDate(?LoanRequest $loan): string
    {
        if ($loan && $loan->start_period) {
            return $loan->start_period.'-01';
        }

        return now()->startOfMonth()->toDateString();
    }

    private static function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim($value, '_');
    }
}
