<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateAnnualLeave extends Command
{
    protected $signature = 'leave:generate-annual {year?} {--company=}';
    protected $description = 'Generate annual leave balances based on leave policies';

    public function handle(): int
    {
        $year = $this->argument('year') ?? now()->year;
        $companyId = $this->option('company');

        $this->info("🔄 Generating leave balances for year {$year}...");

        $companies = $companyId
            ? Company::where('id', $companyId)->get()
            : Company::all();

        $totalGenerated = 0;
        $totalSkipped = 0;

        foreach ($companies as $company) {
            $policies = LeavePolicy::where('company_id', $company->id)
                ->where('is_active', true)
                ->with(['leaveType', 'eligibleEmployees:id'])
                ->get();

            if ($policies->isEmpty()) {
                $this->warn("⚠️  Company #{$company->id} ({$company->name}): No active policies.");
                continue;
            }

            foreach ($policies as $policy) {
                $employeeQuery = Employee::where('company_id', $company->id)
                    ->where('is_active', true);

                if (! $policy->appliesToAllEmployees()) {
                    $eligibleEmployeeIds = $policy->eligibleEmployees->pluck('id');

                    if ($eligibleEmployeeIds->isEmpty()) {
                        continue;
                    }

                    $employeeQuery->whereIn('id', $eligibleEmployeeIds);
                }

                foreach ($employeeQuery->get() as $emp) {
                    // Check if balance already exists for this year
                    $exists = LeaveBalance::where('employee_id', $emp->id)
                        ->where('leave_type_id', $policy->leave_type_id)
                        ->where('year', $year)
                        ->exists();

                    if ($exists) {
                        $totalSkipped++;
                        continue;
                    }

                    // Check minimum tenure
                    $joinDate = Carbon::parse($emp->join_date);
                    $tenureMonths = $joinDate->diffInMonths(Carbon::create($year, 1, 1));

                    if ($tenureMonths < $policy->min_tenure_months) {
                        // Check if prorated applies (employee will reach tenure during this year)
                        if ($policy->is_prorated) {
                            $eligibleDate = $joinDate->copy()->addMonths($policy->min_tenure_months);
                            if ($eligibleDate->year == $year) {
                                // Prorate: remaining months in the year after becoming eligible
                                $remainingMonths = 12 - $eligibleDate->month + 1;
                                $proratedDays = (int) round($policy->days_per_year * $remainingMonths / 12);

                                if ($proratedDays > 0) {
                                    $this->createBalance($emp, $policy, $year, $proratedDays);
                                    $totalGenerated++;
                                }
                            }
                        }
                        continue;
                    }

                    // Calculate carry over from previous year
                    $carryOver = 0;
                    if ($policy->max_carry_over > 0) {
                        $prevBalance = LeaveBalance::where('employee_id', $emp->id)
                            ->where('leave_type_id', $policy->leave_type_id)
                            ->where('year', $year - 1)
                            ->first();

                        if ($prevBalance && $prevBalance->remaining_days > 0) {
                            $carryOver = min($prevBalance->remaining_days, $policy->max_carry_over);
                        }
                    }

                    $this->createBalance($emp, $policy, $year, $policy->days_per_year, $carryOver);
                    $totalGenerated++;
                }
            }

            $this->info("✅ Company #{$company->id} ({$company->name}): processed.");
        }

        $this->info("🎉 Done! Generated: {$totalGenerated} | Skipped (already exists): {$totalSkipped}");
        return Command::SUCCESS;
    }

    private function createBalance(Employee $emp, LeavePolicy $policy, int $year, int $allocatedDays, int $carryOver = 0): void
    {
        LeaveBalance::create([
            'employee_id' => $emp->id,
            'leave_type_id' => $policy->leave_type_id,
            'year' => $year,
            'total_days' => $allocatedDays,
            'carry_over' => $carryOver,
            'used_days' => 0,
            'remaining_days' => $allocatedDays + $carryOver,
        ]);
    }
}
