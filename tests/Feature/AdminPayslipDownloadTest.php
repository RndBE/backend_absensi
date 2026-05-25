<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPayslipDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_pdf_sanitizes_employee_code_in_filename(): void
    {
        [$admin, , $detail] = $this->payslipDetailWithSlashedEmployeeCode();

        $response = $this
            ->withoutExceptionHandling()
            ->withSession(['admin_id' => $admin->id])
            ->get(route('admin.payslips.download', $detail->id));

        $response->assertOk();
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=Payslip_001-DIR-I-2013_2026-05.pdf'
        );
    }

    public function test_api_download_pdf_sanitizes_employee_code_in_filename(): void
    {
        [, $employee, $detail] = $this->payslipDetailWithSlashedEmployeeCode();

        Sanctum::actingAs($employee);

        $response = $this
            ->withoutExceptionHandling()
            ->get('/api/payslips/' . $detail->id . '/download');

        $response->assertOk();
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=Payslip_001-DIR-I-2013_2026-05.pdf'
        );
    }

    private function payslipDetailWithSlashedEmployeeCode(): array
    {
        $company = Company::create(['name' => 'Test Company']);

        $admin = Employee::create([
            'employee_code' => 'ADM-001',
            'company_id' => $company->id,
            'full_name' => 'Admin User',
            'email' => 'admin-payslip@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
        ]);

        $employee = Employee::create([
            'employee_code' => '001/DIR/I/2013',
            'company_id' => $company->id,
            'full_name' => 'Payroll Employee',
            'email' => 'employee-payslip@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'employee',
        ]);

        $run = PayrollRun::create([
            'period' => '2026-05',
            'status' => 'published',
            'total_earning' => 5_000_000,
            'total_deduction' => 250_000,
            'total_net' => 4_750_000,
        ]);

        $detail = PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5_000_000,
            'total_earning' => 5_000_000,
            'total_deduction' => 250_000,
            'net_salary' => 4_750_000,
            'components' => [],
        ]);

        return [$admin, $employee, $detail];
    }
}
