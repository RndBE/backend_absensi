<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use App\Models\TaxCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTaxCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_bukti_potong_only_uses_payroll_runs_from_selected_year(): void
    {
        [$admin, $employee] = $this->employees();

        $this->payrollDetail($employee, '2025-12', 'published', 7_000_000, 350_000, 140_000, 6_510_000);
        $this->payrollDetail($employee, '2026-01', 'published', 2_000_000, 100_000, 40_000, 1_860_000);
        $this->payrollDetail($employee, '2026-05', 'published', 3_000_000, 150_000, 60_000, 2_790_000);

        $response = $this
            ->withSession(['admin_id' => $admin->id])
            ->from(route('admin.tax.bukti-potong', ['year' => 2026]))
            ->post(route('admin.tax.generate-bukti-potong'), [
                'employee_id' => $employee->id,
                'tax_year' => 2026,
            ]);

        $response->assertRedirect(route('admin.tax.bukti-potong', ['year' => 2026]));
        $response->assertSessionHasNoErrors();

        $certificate = TaxCertificate::where('employee_id', $employee->id)
            ->where('tax_year', 2026)
            ->firstOrFail();

        $this->assertEquals(5_000_000, $certificate->gross_annual);
        $this->assertEquals(250_000, $certificate->tax_annual);
        $this->assertEquals(100_000, $certificate->bpjs_annual);
        $this->assertEquals(4_650_000, $certificate->nett_annual);
        $this->assertSame(['2026-01', '2026-05'], array_keys($certificate->monthly_details));
    }

    private function employees(): array
    {
        $company = Company::create(['name' => 'Test Company']);

        $admin = Employee::create([
            'employee_code' => 'ADM-001',
            'company_id' => $company->id,
            'full_name' => 'Admin User',
            'email' => 'admin-tax@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
        ]);

        $employee = Employee::create([
            'employee_code' => 'EMP-001',
            'company_id' => $company->id,
            'full_name' => 'Payroll Employee',
            'email' => 'employee-tax@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'employee',
        ]);

        return [$admin, $employee];
    }

    private function payrollDetail(
        Employee $employee,
        string $period,
        string $status,
        int $gross,
        int $tax,
        int $bpjs,
        int $net
    ): void {
        $run = PayrollRun::create([
            'period' => $period,
            'status' => $status,
            'total_earning' => $gross,
            'total_deduction' => $tax + $bpjs,
            'total_net' => $net,
        ]);

        PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => $gross,
            'total_earning' => $gross,
            'total_deduction' => $tax + $bpjs,
            'net_salary' => $net,
            'components' => [
                ['name' => 'PPh 21', 'type' => 'deduction', 'amount' => $tax],
                ['name' => 'BPJS (Karyawan)', 'type' => 'deduction', 'amount' => $bpjs],
            ],
        ]);
    }
}
