<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePayrollComponent;
use App\Models\LoanRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class PayrollLoanDeductionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPayrollLoanSchema();
        Http::fake();
        session(['admin_id' => 1]);
    }

    public function test_payroll_run_adds_active_loan_as_auto_deduction_component(): void
    {
        $company = Company::create(['name' => 'PT Payroll Loan']);
        $admin = Employee::create([
            'employee_code' => 'ADM-001',
            'company_id' => $company->id,
            'full_name' => 'Admin Payroll',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-001',
            'company_id' => $company->id,
            'full_name' => 'Employee Loan',
            'email' => 'employee@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        $loan = LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 5000000,
            'installment_count' => 10,
            'monthly_installment' => 500000,
            'remaining_amount' => 2500000,
            'start_period' => '2026-06',
            'status' => 'active',
            'purpose' => 'Pinjaman keluarga',
        ]);

        $run = PayrollRun::create([
            'period' => '2026-06',
            'created_by' => $admin->id,
        ]);

        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $components = collect($detail->components);
        $loanComponent = $components->firstWhere('name', 'Potongan Pinjaman');

        $this->assertNotNull($loanComponent);
        $this->assertSame('deduction', $loanComponent['type']);
        $this->assertSame(500000.0, (float) $loanComponent['amount']);
        $this->assertTrue($loanComponent['is_auto']);
        $this->assertSame($loan->id, $loanComponent['loan']['id']);
        $this->assertSame(6, $loanComponent['loan']['installment_number']);
        $this->assertSame(10, $loanComponent['loan']['installment_count']);
        $this->assertSame(2000000.0, (float) $loanComponent['loan']['remaining_amount']);
        $this->assertGreaterThanOrEqual(500000, (float) $detail->total_deduction);
    }

    public function test_payroll_run_skips_all_auto_bpjs_when_bpjs_numbers_are_empty(): void
    {
        [$employee, $admin] = $this->seedBpjsScenario('bpjs-empty', null, null);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $componentNames = collect(PayrollRunDetail::where('payroll_run_id', $run->id)->firstOrFail()->components)
            ->pluck('name');

        $this->assertFalse($componentNames->contains('BPJS Kesehatan'));
        $this->assertFalse($componentNames->contains('BPJS Kesehatan Perusahaan'));
        $this->assertFalse($componentNames->contains('JHT Karyawan'));
        $this->assertFalse($componentNames->contains('JHT Perusahaan'));
        $this->assertFalse($componentNames->contains('JKK Perusahaan'));
        $this->assertFalse($componentNames->contains('JKM Perusahaan'));
        $this->assertFalse($componentNames->contains('JP Karyawan'));
        $this->assertFalse($componentNames->contains('JP Perusahaan'));
    }

    public function test_payroll_run_calculates_only_health_bpjs_when_health_number_is_filled(): void
    {
        [$employee, $admin] = $this->seedBpjsScenario('bpjs-health', 'KES-001', null);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $components = collect(PayrollRunDetail::where('payroll_run_id', $run->id)->firstOrFail()->components);

        $this->assertNotNull($components->firstWhere('name', 'BPJS Kesehatan'));
        $this->assertNotNull($components->firstWhere('name', 'BPJS Kesehatan Perusahaan'));
        $this->assertNull($components->firstWhere('name', 'JHT Karyawan'));
        $this->assertNull($components->firstWhere('name', 'JHT Perusahaan'));
        $this->assertNull($components->firstWhere('name', 'JKK Perusahaan'));
        $this->assertNull($components->firstWhere('name', 'JKM Perusahaan'));
        $this->assertNull($components->firstWhere('name', 'JP Karyawan'));
        $this->assertNull($components->firstWhere('name', 'JP Perusahaan'));
    }

    public function test_payroll_run_calculates_only_employment_bpjs_when_employment_number_is_filled(): void
    {
        [$employee, $admin] = $this->seedBpjsScenario('bpjs-tk', null, 'TK-001');

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $components = collect(PayrollRunDetail::where('payroll_run_id', $run->id)->firstOrFail()->components);

        $this->assertNull($components->firstWhere('name', 'BPJS Kesehatan'));
        $this->assertNull($components->firstWhere('name', 'BPJS Kesehatan Perusahaan'));
        $this->assertNotNull($components->firstWhere('name', 'JHT Karyawan'));
        $this->assertNotNull($components->firstWhere('name', 'JHT Perusahaan'));
        $this->assertNotNull($components->firstWhere('name', 'JKK Perusahaan'));
        $this->assertNotNull($components->firstWhere('name', 'JKM Perusahaan'));
        $this->assertNotNull($components->firstWhere('name', 'JP Karyawan'));
        $this->assertNotNull($components->firstWhere('name', 'JP Perusahaan'));
    }

    public function test_employee_joining_after_bpjs_cutoff_skips_bpjs_in_join_month_only(): void
    {
        [$employee, $admin] = $this->seedBpjsScenario('bpjs-cutoff', 'KES-001', 'TK-001');
        $employee->update(['join_date' => '2026-06-24']);

        $juneRun = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$juneRun, [$employee->id]]);
        $juneComponents = collect(PayrollRunDetail::where('payroll_run_id', $juneRun->id)->firstOrFail()->components);

        $this->assertNull($juneComponents->firstWhere('name', 'BPJS Kesehatan'));
        $this->assertNull($juneComponents->firstWhere('name', 'JHT Karyawan'));
        $this->assertNull($juneComponents->firstWhere('name', 'BPJS Kesehatan Perusahaan'));
        $this->assertNull($juneComponents->firstWhere('name', 'JHT Perusahaan'));

        $julyRun = PayrollRun::create(['period' => '2026-07', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$julyRun, [$employee->id]]);
        $julyComponents = collect(PayrollRunDetail::where('payroll_run_id', $julyRun->id)->firstOrFail()->components);

        $this->assertNotNull($julyComponents->firstWhere('name', 'BPJS Kesehatan'));
        $this->assertNotNull($julyComponents->firstWhere('name', 'JHT Karyawan'));
        $this->assertNotNull($julyComponents->firstWhere('name', 'BPJS Kesehatan Perusahaan'));
        $this->assertNotNull($julyComponents->firstWhere('name', 'JHT Perusahaan'));
    }

    public function test_scheduled_installment_overrides_default_for_that_period(): void
    {
        [$employee, $admin] = $this->seedLoanScenario('sched-a');

        $loan = LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 5000000,
            'installment_count' => 10,
            'monthly_installment' => 500000, // default bulan biasa
            'installment_schedule' => ['2026-06' => 800000], // override Juni
            'remaining_amount' => 5000000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $loanComponent = collect(PayrollRunDetail::where('payroll_run_id', $run->id)->firstOrFail()->components)
            ->firstWhere('name', 'Potongan Pinjaman');

        $this->assertSame(800000.0, (float) $loanComponent['amount']);
        $this->assertSame($loan->id, $loanComponent['loan']['id']);
    }

    public function test_scheduled_loan_uses_default_for_unlisted_period(): void
    {
        [$employee, $admin] = $this->seedLoanScenario('sched-b');

        LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 5000000,
            'installment_count' => 10,
            'monthly_installment' => 500000, // default
            'installment_schedule' => ['2026-06' => 800000], // hanya Juni yang di-override
            'remaining_amount' => 5000000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);

        // Periode Juli tidak ada di jadwal → pakai default 500000.
        $run = PayrollRun::create(['period' => '2026-07', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $loanComponent = collect(PayrollRunDetail::where('payroll_run_id', $run->id)->firstOrFail()->components)
            ->firstWhere('name', 'Potongan Pinjaman');

        $this->assertSame(500000.0, (float) $loanComponent['amount']);
    }

    /**
     * Buat company + admin + employee + payroll aktif; kembalikan [employee, admin].
     */
    private function seedLoanScenario(string $suffix): array
    {
        $company = Company::create(['name' => 'PT Loan '.$suffix]);
        $admin = Employee::create([
            'employee_code' => 'ADM-'.$suffix,
            'company_id' => $company->id,
            'full_name' => 'Admin '.$suffix,
            'email' => 'admin-'.$suffix.'@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-'.$suffix,
            'company_id' => $company->id,
            'full_name' => 'Employee '.$suffix,
            'email' => 'employee-'.$suffix.'@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        return [$employee, $admin];
    }

    public function test_resigned_employee_drops_jht_jkk_jkm_in_resign_month(): void
    {
        [$employee, $admin] = $this->seedBpjsScenario('bpjs-resign', 'KES-001', 'TK-001');
        // Keluar di bulan periode (Juni).
        $employee->update(['resign_date' => '2026-06-10', 'last_working_date' => '2026-06-10']);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $names = collect(PayrollRunDetail::where('payroll_run_id', $run->id)->firstOrFail()->components)->pluck('name');

        // JHT/JKK/JKM dihilangkan di bulan resign.
        $this->assertFalse($names->contains('JHT Karyawan'));
        $this->assertFalse($names->contains('JHT Perusahaan'));
        $this->assertFalse($names->contains('JKK Perusahaan'));
        $this->assertFalse($names->contains('JKM Perusahaan'));
        // BPJS Kesehatan & JP tetap ada.
        $this->assertTrue($names->contains('BPJS Kesehatan'));
        $this->assertTrue($names->contains('JP Karyawan'));
    }

    public function test_update_detail_saves_newly_added_component(): void
    {
        $company = Company::create(['name' => 'PT Edit']);
        $admin = Employee::create([
            'employee_code' => 'ADM-EDIT', 'company_id' => $company->id, 'full_name' => 'Admin',
            'email' => 'admin-edit@example.test', 'password' => 'secret', 'role' => 'admin', 'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);
        $employee = Employee::create([
            'employee_code' => 'EMP-EDIT', 'company_id' => $company->id, 'full_name' => 'Employee',
            'email' => 'emp-edit@example.test', 'password' => 'secret', 'role' => 'employee', 'is_active' => true, 'ptkp' => 'TK/0',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id, 'status' => 'draft']);
        $detail = PayrollRunDetail::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'basic_salary' => 5000000, 'total_earning' => 5100000, 'total_deduction' => 0, 'net_salary' => 5100000,
            'components' => [
                ['name' => 'Tunjangan Lama', 'type' => 'earning', 'amount' => 100000, 'category' => 'recurring', 'is_taxable' => 0, 'is_auto' => 0, 'detail' => ''],
            ],
        ]);

        // Meniru submit form: komponen lama + 1 komponen BARU.
        $request = \Illuminate\Http\Request::create('/x', 'PUT', [
            'components' => [
                ['name' => 'Tunjangan Lama', 'type' => 'earning', 'amount' => '100000', 'category' => 'recurring', 'is_taxable' => '0', 'is_auto' => '0', 'detail' => ''],
                ['name' => 'Tunjangan Baru', 'type' => 'earning', 'amount' => '250000', 'category' => 'recurring', 'is_taxable' => '0', 'is_auto' => '0', 'detail' => ''],
            ],
        ]);

        (new PayrollRunController)->updateDetail($request, $run->id, $detail->id);

        $names = collect($detail->fresh()->components)->pluck('name');
        $this->assertTrue($names->contains('Tunjangan Baru'), 'Komponen baru harus tersimpan.');
        $this->assertSame(5350000.0, (float) $detail->fresh()->total_earning); // 5.000.000 + 100.000 + 250.000
    }

    public function test_update_detail_allows_negative_component_and_still_adds_new_one(): void
    {
        $company = Company::create(['name' => 'PT Neg']);
        $admin = Employee::create([
            'employee_code' => 'ADM-NEG', 'company_id' => $company->id, 'full_name' => 'Admin',
            'email' => 'admin-neg@example.test', 'password' => 'secret', 'role' => 'admin', 'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);
        $employee = Employee::create([
            'employee_code' => 'EMP-NEG', 'company_id' => $company->id, 'full_name' => 'Supri',
            'email' => 'supri@example.test', 'password' => 'secret', 'role' => 'employee', 'is_active' => false,
            'resign_date' => '2026-06-10', 'ptkp' => 'TK/0',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id, 'status' => 'draft']);
        $detail = PayrollRunDetail::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'basic_salary' => 5000000, 'total_earning' => 4910868, 'total_deduction' => 0, 'net_salary' => 4910868,
            'components' => [
                ['name' => 'Tax Allowance', 'type' => 'earning', 'amount' => -89132, 'category' => 'recurring', 'is_taxable' => 0, 'is_auto' => 1, 'detail' => ''],
            ],
        ]);

        // Meniru submit: komponen NEGATIF (Tax Allowance -89.132) + 1 komponen baru.
        $request = \Illuminate\Http\Request::create('/x', 'PUT', [
            'components' => [
                ['name' => 'Tax Allowance', 'type' => 'earning', 'amount' => '-89132', 'category' => 'recurring', 'is_taxable' => '0', 'is_auto' => '1', 'detail' => ''],
                ['name' => 'Tunjangan Baru', 'type' => 'earning', 'amount' => '250000', 'category' => 'recurring', 'is_taxable' => '0', 'is_auto' => '0', 'detail' => ''],
            ],
        ]);

        (new PayrollRunController)->updateDetail($request, $run->id, $detail->id);

        $names = collect($detail->fresh()->components)->pluck('name');
        $this->assertTrue($names->contains('Tunjangan Baru'), 'Komponen baru harus tersimpan meski ada komponen negatif.');
        $this->assertTrue($names->contains('Tax Allowance'));
    }

    private function seedBpjsScenario(string $suffix, ?string $bpjsKesehatan, ?string $bpjsKetenagakerjaan): array
    {
        $this->seedBpjsSettings();

        $company = Company::create(['name' => 'PT '.$suffix]);
        $admin = Employee::create([
            'employee_code' => 'ADM-'.$suffix,
            'company_id' => $company->id,
            'full_name' => 'Admin '.$suffix,
            'email' => 'admin-'.$suffix.'@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-'.$suffix,
            'company_id' => $company->id,
            'full_name' => 'Employee '.$suffix,
            'email' => 'employee-'.$suffix.'@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
            'bpjs_kesehatan' => $bpjsKesehatan,
            'bpjs_ketenagakerjaan' => $bpjsKetenagakerjaan,
        ]);

        return [$employee, $admin];
    }

    private function seedBpjsSettings(): void
    {
        $settings = [
            ['key' => 'kes_rate', 'value' => ['company' => 4, 'employee' => 1]],
            ['key' => 'kes_cap', 'value' => ['salary_cap' => 12000000]],
            ['key' => 'jht_rate', 'value' => ['company' => 3.7, 'employee' => 2]],
            ['key' => 'jkk_rate', 'value' => ['company' => 0.24, 'employee' => 0]],
            ['key' => 'jkm_rate', 'value' => ['company' => 0.3, 'employee' => 0]],
            ['key' => 'jp_rate', 'value' => ['company' => 2, 'employee' => 1]],
            ['key' => 'jp_cap', 'value' => ['salary_cap' => 10042300]],
        ];

        foreach ($settings as $setting) {
            DB::table('bpjs_settings')->insert([
                'key' => $setting['key'],
                'value' => json_encode($setting['value']),
                'effective_date' => '2026-01-01',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_zero_salary_record_is_ignored_and_not_treated_as_salary_revision(): void
    {
        [$employee, $admin] = $this->seedLoanScenario('zero-sal'); // sudah punya gaji 5.000.000 aktif

        // Record gaji Rp 0 effective tengah bulan (data placeholder/rusak).
        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 0,
            'effective_date' => '2026-06-24',
            'is_active' => false,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        // Gaji pokok tetap penuh — record 0 diabaikan, tidak dianggap revisi gaji.
        $this->assertSame(5000000.0, (float) $detail->basic_salary);
        $this->assertNull(collect($detail->components)->firstWhere('name', 'Revisi Gaji'));
    }

    public function test_duplicate_same_salary_record_is_not_treated_as_salary_revision(): void
    {
        [$employee, $admin] = $this->seedLoanScenario('same-sal');

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-06-24',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        $run = PayrollRun::create([
            'period' => '2026-06',
            'created_by' => $admin->id,
        ]);

        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertNull(collect($detail->components)->firstWhere('name', 'Revisi Gaji'));
    }

    public function test_resigned_employee_with_deactivated_payroll_is_generated_for_resign_month(): void
    {
        [$admin, $employee] = $this->seedResignedEmployee('res-a', '2026-07-15');

        $run = PayrollRun::create(['period' => '2026-07', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        // Karyawan resign Juli tetap tergenerate di payroll Juli meski payroll-nya nonaktif.
        $this->assertDatabaseHas('payroll_run_details', [
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_resigned_employee_is_excluded_from_later_month(): void
    {
        [$admin, $employee] = $this->seedResignedEmployee('res-b', '2026-06-20');

        // Resign Juni → tidak boleh muncul di payroll Juli.
        $run = PayrollRun::create(['period' => '2026-07', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $this->assertDatabaseMissing('payroll_run_details', [
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_prorate_and_inclusion_use_last_working_date_not_resign_date(): void
    {
        // Surat resign 30 Mei, tapi hari kerja terakhir 10 Juni → payable di JUNI, 10 hari.
        [$admin, $employee] = $this->seedResignedEmployee('lwd', '2026-05-30', '2026-06-10');

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->first();

        // Inklusi berdasar hari kerja terakhir (Juni), bukan tanggal resign (Mei).
        $this->assertNotNull($detail, 'Karyawan harus masuk payroll Juni berdasar hari kerja terakhir.');
        // Pro-rata 10/30 hari: 6.000.000 × 10/30 = 2.000.000 (bukan 1 hari dari resign_date).
        $this->assertSame(2000000.0, (float) $detail->basic_salary);
    }

    public function test_resign_month_refunds_pph_already_withheld_when_progressive_tax_is_lower(): void
    {
        [$admin, $employee] = $this->seedResignedEmployee('pph-refund', '2026-06-15');

        $previousRun = PayrollRun::create([
            'period' => '2026-05',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);
        PayrollRunDetail::create([
            'payroll_run_id' => $previousRun->id,
            'employee_id' => $employee->id,
            'basic_salary' => 6_000_000,
            'total_earning' => 6_150_000,
            'total_deduction' => 150_000,
            'net_salary' => 6_000_000,
            'components' => [
                [
                    'name' => 'Tunjangan Pajak (Gross Up)',
                    'type' => 'earning',
                    'category' => 'recurring',
                    'amount' => 150_000,
                    'is_taxable' => true,
                    'is_auto' => true,
                ],
                [
                    'name' => 'PPh 21',
                    'type' => 'deduction',
                    'category' => 'recurring',
                    'amount' => 150_000,
                    'is_taxable' => false,
                    'is_auto' => true,
                ],
            ],
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $components = collect($detail->components);
        $taxAllowance = $components->firstWhere('name', 'Tax Allowance');
        $pph21 = $components->firstWhere('name', 'PPh 21');

        $this->assertNotNull($taxAllowance);
        $this->assertSame('earning', $taxAllowance['type']);
        $this->assertTrue((bool) $taxAllowance['is_taxable']);
        $this->assertSame(-150_000.0, (float) $taxAllowance['amount']);

        $this->assertNotNull($pph21);
        $this->assertSame('deduction', $pph21['type']);
        $this->assertSame(-150_000.0, (float) $pph21['amount']);
        $this->assertStringContainsString('PPh21 sudah dipotong', $pph21['detail']);
        $this->assertSame(0.0, (float) $detail->net_salary - ((float) $detail->total_earning - (float) $detail->total_deduction));
    }

    public function test_resign_month_reads_legacy_tax_allowance_as_prior_pph_when_no_pph_deduction_exists(): void
    {
        [$admin, $employee] = $this->seedResignedEmployee('legacy-tax-allowance', '2026-06-15');

        $previousRun = PayrollRun::create([
            'period' => '2026-03',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);
        PayrollRunDetail::create([
            'payroll_run_id' => $previousRun->id,
            'employee_id' => $employee->id,
            'basic_salary' => 6_000_000,
            'total_earning' => 6_089_132,
            'total_deduction' => 0,
            'net_salary' => 6_089_132,
            'components' => [
                [
                    'name' => 'Tax Allowance',
                    'type' => 'earning',
                    'category' => 'recurring',
                    'amount' => 89_132,
                    'is_taxable' => true,
                    'is_auto' => false,
                ],
            ],
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $components = collect($detail->components);

        $this->assertSame(-89_132.0, (float) $components->firstWhere('name', 'Tax Allowance')['amount']);
        $this->assertSame(-89_132.0, (float) $components->firstWhere('name', 'PPh 21')['amount']);
    }

    /**
     * Karyawan yang sudah resign: is_active=false + resign_date, dan EmployeePayroll
     * sudah dinonaktifkan (meniru proses resign). Kembalikan [admin, employee].
     */
    private function seedResignedEmployee(string $suffix, string $resignDate, ?string $lastWorkingDate = null): array
    {
        $company = Company::create(['name' => 'PT Resign '.$suffix]);
        $admin = Employee::create([
            'employee_code' => 'ADM-'.$suffix,
            'company_id' => $company->id,
            'full_name' => 'Admin '.$suffix,
            'email' => 'admin-'.$suffix.'@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-'.$suffix,
            'company_id' => $company->id,
            'full_name' => 'Resigned '.$suffix,
            'email' => 'emp-'.$suffix.'@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => false,
            'resign_date' => $resignDate,
            'last_working_date' => $lastWorkingDate,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 6000000,
            'effective_date' => '2026-01-01',
            'is_active' => false, // dinonaktifkan saat resign
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        return [$admin, $employee];
    }

    public function test_resign_prorate_uses_scheduled_working_days_when_template_exists(): void
    {
        $company = Company::create(['name' => 'PT Resign Sched']);
        $admin = Employee::create([
            'employee_code' => 'ADM-rsched',
            'company_id' => $company->id,
            'full_name' => 'Admin Sched',
            'email' => 'admin-rsched@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        // Template Senin-Jumat.
        $shiftId = DB::table('shifts')->insertGetId(['company_id' => $company->id, 'name' => 'Pagi', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_off' => false, 'created_at' => now(), 'updated_at' => now()]);
        $templateId = DB::table('schedule_templates')->insertGetId(['company_id' => $company->id, 'name' => 'Sen-Jum', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1, 2, 3, 4, 5] as $dow) {
            DB::table('schedule_template_days')->insert(['template_id' => $templateId, 'day_of_week' => $dow, 'shift_id' => $shiftId, 'created_at' => now(), 'updated_at' => now()]);
        }

        $employee = Employee::create([
            'employee_code' => 'EMP-rsched',
            'company_id' => $company->id,
            'full_name' => 'Resign Sched',
            'email' => 'emp-rsched@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => false,
            'resign_date' => '2026-05-30',
            'last_working_date' => '2026-06-10',
            'schedule_template_id' => $templateId,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 6600000,
            'effective_date' => '2026-01-01',
            'is_active' => false,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $admin->id]);
        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($detail);
        // 8 hari kerja (1-10 Jun, Sen-Jum) dari 22 hari kerja Juni × 6.600.000 = 2.400.000.
        $this->assertSame(2400000.0, (float) $detail->basic_salary);
    }

    public function test_payroll_loan_deduction_is_capped_by_remaining_amount(): void
    {
        $company = Company::create(['name' => 'PT Payroll Loan Cap']);
        $admin = Employee::create([
            'employee_code' => 'ADM-002',
            'company_id' => $company->id,
            'full_name' => 'Admin Payroll',
            'email' => 'admin2@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-002',
            'company_id' => $company->id,
            'full_name' => 'Employee Loan Cap',
            'email' => 'employee2@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 1200000,
            'installment_count' => 3,
            'monthly_installment' => 500000,
            'remaining_amount' => 200000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);

        $run = PayrollRun::create([
            'period' => '2026-06',
            'created_by' => $admin->id,
        ]);

        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $loanComponent = collect($detail->components)->firstWhere('name', 'Potongan Pinjaman');

        $this->assertSame(200000.0, (float) $loanComponent['amount']);
        $this->assertSame(0.0, (float) $loanComponent['loan']['remaining_amount']);
    }

    public function test_payroll_loan_deduction_uses_total_repayable_when_interest_exists(): void
    {
        $company = Company::create(['name' => 'PT Payroll Loan Interest']);
        $admin = Employee::create([
            'employee_code' => 'ADM-004',
            'company_id' => $company->id,
            'full_name' => 'Admin Payroll Interest',
            'email' => 'admin4@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-004',
            'company_id' => $company->id,
            'full_name' => 'Employee Loan Interest',
            'email' => 'employee4@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        $loan = LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 4000000,
            'interest_rate' => 10,
            'interest_amount' => 400000,
            'total_repayable' => 4400000,
            'installment_count' => 4,
            'monthly_installment' => 1100000,
            'remaining_amount' => 4400000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);

        $run = PayrollRun::create([
            'period' => '2026-06',
            'created_by' => $admin->id,
        ]);

        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $loanComponent = collect($detail->components)->firstWhere('name', 'Potongan Pinjaman');

        $this->assertSame(1100000.0, (float) $loanComponent['amount']);
        $this->assertSame($loan->id, $loanComponent['loan']['id']);
        $this->assertSame(4000000.0, (float) $loanComponent['loan']['principal_amount']);
        $this->assertSame(10.0, (float) $loanComponent['loan']['interest_rate']);
        $this->assertSame(400000.0, (float) $loanComponent['loan']['interest_amount']);
        $this->assertSame(4400000.0, (float) $loanComponent['loan']['total_repayable']);
        $this->assertSame(1, $loanComponent['loan']['installment_number']);
        $this->assertSame(1100000.0, (float) $loanComponent['loan']['paid_amount']);
        $this->assertSame(3300000.0, (float) $loanComponent['loan']['remaining_amount']);
    }

    public function test_finalize_payroll_applies_loan_deductions_to_remaining_balance(): void
    {
        $company = Company::create(['name' => 'PT Payroll Loan Finalize']);
        $admin = Employee::create([
            'employee_code' => 'ADM-003',
            'company_id' => $company->id,
            'full_name' => 'Admin Payroll',
            'email' => 'admin3@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-003',
            'company_id' => $company->id,
            'full_name' => 'Employee Loan Finalize',
            'email' => 'employee3@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        $loan = LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 1200000,
            'installment_count' => 3,
            'monthly_installment' => 500000,
            'remaining_amount' => 500000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);

        $run = PayrollRun::create([
            'period' => '2026-06',
            'created_by' => $admin->id,
        ]);

        $controller = new PayrollRunController;
        $this->invokePrivate($controller, 'generateDetails', [$run, [$employee->id]]);

        $this->assertSame(500000.0, (float) $loan->fresh()->remaining_amount);

        $controller->finalize($run->id);

        $loan->refresh();
        $this->assertSame(0.0, (float) $loan->remaining_amount);
        $this->assertSame('paid', $loan->status);
        $this->assertNotNull($loan->paid_at);
    }

    public function test_synced_pinjaman_assignment_is_not_counted_twice_in_payroll(): void
    {
        $company = Company::create(['name' => 'PT Payroll Loan Sync']);
        $admin = Employee::create([
            'employee_code' => 'ADM-005',
            'company_id' => $company->id,
            'full_name' => 'Admin Payroll Sync',
            'email' => 'admin5@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $employee = Employee::create([
            'employee_code' => 'EMP-005',
            'company_id' => $company->id,
            'full_name' => 'Employee Loan Sync',
            'email' => 'employee5@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);

        $pinjaman = PayrollComponent::create([
            'name' => 'Pinjaman',
            'type' => 'deduction',
            'category' => 'recurring',
            'is_taxable' => false,
            'is_auto' => false,
        ]);

        EmployeePayrollComponent::create([
            'employee_id' => $employee->id,
            'payroll_component_id' => $pinjaman->id,
            'amount' => 500000,
            'start_date' => '2026-06-01',
            'is_active' => true,
        ]);

        LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 2000000,
            'installment_count' => 4,
            'monthly_installment' => 500000,
            'remaining_amount' => 2000000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);

        $run = PayrollRun::create([
            'period' => '2026-06',
            'created_by' => $admin->id,
        ]);

        $this->invokePrivate(new PayrollRunController, 'generateDetails', [$run, [$employee->id]]);

        $detail = PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
        $components = collect($detail->components);

        $this->assertNull($components->firstWhere('name', 'Pinjaman'));
        $this->assertCount(1, $components->where('name', 'Potongan Pinjaman'));
        $this->assertSame(500000.0, (float) $components->firstWhere('name', 'Potongan Pinjaman')['amount']);
    }

    private function createPayrollLoanSchema(): void
    {
        foreach ([
            'payroll_run_details',
            'payroll_logs',
            'payroll_runs',
            'payroll_adjustments',
            'employee_payroll_components',
            'employee_payrolls',
            'payroll_components',
            'loan_requests',
            'holidays',
            'leave_requests',
            'attendances',
            'overtime_requests',
            'schedule_assignments',
            'employees',
            'tax_settings',
            'bpjs_settings',
            'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('ptkp')->nullable();
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('payment_schedule')->default('monthly');
            $table->string('payment_method')->default('transfer');
            $table->string('ptkp_status')->nullable();
            $table->string('bpjs_kesehatan')->nullable();
            $table->string('bpjs_ketenagakerjaan')->nullable();
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_exempt_penalty')->default(false);
            $table->decimal('late_penalty_per_day', 15, 2)->default(50000);
            $table->decimal('overtime_multiplier', 8, 2)->default(1);
            $table->string('tax_method')->default('gross_up');
            $table->boolean('pph21_dtp')->default(false);
            $table->timestamps();
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('category')->default('recurring');
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_auto')->default(false);
            $table->timestamps();
        });

        Schema::create('employee_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('payroll_component_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->string('status')->default('draft');
            $table->decimal('total_earning', 18, 2)->default(0);
            $table->decimal('total_deduction', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_run_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_earning', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->json('components')->nullable();
            $table->boolean('is_manual_edited')->default(false);
            $table->timestamps();
        });

        Schema::create('payroll_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id');
            $table->string('action');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_repayable', 15, 2)->default(0);
            $table->unsignedSmallInteger('installment_count');
            $table->decimal('monthly_installment', 15, 2);
            $table->json('installment_schedule')->nullable();
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('start_period', 7)->nullable();
            $table->text('purpose')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('date');
            $table->string('name');
            $table->boolean('is_national')->default(false);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('overtime_type')->default('workday');
            $table->integer('total_duration')->default(0);
            $table->integer('break_duration')->default(0);
            $table->integer('approved_duration')->nullable();
            $table->integer('approved_break')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->date('date');
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off')->default(false);
            $table->timestamps();
        });

        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedBigInteger('shift_id');
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->time('start_time')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->string('type');
            $table->string('earning_type');
            $table->string('name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('target_period', 7);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        foreach (['tax_settings', 'bpjs_settings'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->json('value');
                $table->date('effective_date');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
