<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPayrollReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_report_filters_payroll_details_by_employee_company(): void
    {
        $company = Company::create(['name' => 'Admin Company']);
        $otherCompany = Company::create(['name' => 'Other Company']);

        $admin = $this->employee($company, 'ADM-001', 'Admin User', 'admin-payroll-report@example.test', 'superadmin');
        $ownEmployee = $this->employee($company, 'EMP-001', 'Own Payroll Employee', 'own-payroll@example.test');
        $otherEmployee = $this->employee($otherCompany, 'EMP-999', 'Other Payroll Employee', 'other-payroll@example.test');

        $this->payrollDetail($ownEmployee, '2026-05', 5_000_000, 500_000, 4_500_000);
        $this->payrollDetail($otherEmployee, '2026-05', 8_000_000, 800_000, 7_200_000);

        $response = $this
            ->withoutExceptionHandling()
            ->withSession(['admin_id' => $admin->id])
            ->get(route('admin.reports.payroll', ['period' => '2026-05']));

        $response->assertOk();
        $response->assertSee('Own Payroll Employee');
        $response->assertDontSee('Other Payroll Employee');
        $response->assertSee('Rp 5.000.000');
        $response->assertDontSee('Rp 8.000.000');
    }

    private function employee(
        Company $company,
        string $code,
        string $name,
        string $email,
        string $role = 'employee'
    ): Employee {
        return Employee::create([
            'employee_code' => $code,
            'company_id' => $company->id,
            'full_name' => $name,
            'email' => $email,
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => $role,
        ]);
    }

    private function payrollDetail(Employee $employee, string $period, int $earning, int $deduction, int $net): void
    {
        $run = PayrollRun::create([
            'period' => $period,
            'status' => 'published',
            'total_earning' => $earning,
            'total_deduction' => $deduction,
            'total_net' => $net,
        ]);

        PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => $earning,
            'total_earning' => $earning,
            'total_deduction' => $deduction,
            'net_salary' => $net,
            'components' => [],
        ]);
    }
}
