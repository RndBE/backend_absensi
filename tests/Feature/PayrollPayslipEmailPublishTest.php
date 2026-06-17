<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Mail\PayslipPublishedMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollPayslipEmailPublishTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPayrollPublishSchema();
    }

    public function test_publish_payroll_queues_payslip_email_for_each_employee_detail(): void
    {
        Queue::fake();

        $company = Company::create(['name' => 'PT Payroll Email']);
        $admin = Employee::create([
            'employee_code' => 'ADM-EMAIL',
            'company_id' => $company->id,
            'full_name' => 'Admin Payroll',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'employee_code' => 'EMP-EMAIL',
            'company_id' => $company->id,
            'full_name' => 'Employee Payslip',
            'email' => 'employee@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);
        session(['admin_id' => $admin->id]);

        $run = PayrollRun::create([
            'period' => '2026-05',
            'status' => 'finalized',
            'finalized_at' => now(),
            'created_by' => $admin->id,
        ]);

        PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'total_earning' => 5500000,
            'total_deduction' => 500000,
            'net_salary' => 5000000,
            'components' => [
                ['name' => 'Gaji Pokok', 'type' => 'earning', 'amount' => 5000000],
                ['name' => 'Tunjangan', 'type' => 'earning', 'amount' => 500000],
                ['name' => 'Potongan', 'type' => 'deduction', 'amount' => 500000],
            ],
        ]);

        (new PayrollRunController)->publish($run->id);

        $this->assertSame('published', $run->fresh()->status);
        Queue::assertPushed(\App\Jobs\SendPayslipEmailJob::class, 1);
    }

    public function test_payslip_email_contains_period_and_pdf_attachment(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'PT Payroll Email']);
        $employee = Employee::create([
            'employee_code' => 'EMP-EMAIL',
            'company_id' => $company->id,
            'full_name' => 'Employee Payslip',
            'email' => 'employee@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $run = PayrollRun::create([
            'period' => '2026-05',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $detail = PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'total_earning' => 5000000,
            'total_deduction' => 0,
            'net_salary' => 5000000,
            'components' => [],
        ])->load(['employee', 'payrollRun']);

        Mail::to($employee->email)->send(new PayslipPublishedMail($detail, $company, 'fake-pdf-binary'));

        Mail::assertSent(PayslipPublishedMail::class, function (PayslipPublishedMail $mail) use ($employee) {
            $mail->build();

            return $mail->hasTo($employee->email)
                && $mail->hasSubject('Slip Gaji Mei 2026')
                && $mail->hasAttachedData('fake-pdf-binary', 'Payslip_EMP-EMAIL_2026-05.pdf', ['mime' => 'application/pdf']);
        });
    }

    public function test_payslip_email_header_uses_text_brand_without_remote_image(): void
    {
        $company = Company::create(['name' => 'PT Payroll Email']);
        $employee = Employee::create([
            'employee_code' => 'EMP-EMAIL',
            'company_id' => $company->id,
            'full_name' => 'Employee Payslip',
            'email' => 'employee@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $run = PayrollRun::create([
            'period' => '2026-05',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $detail = PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'total_earning' => 5000000,
            'total_deduction' => 0,
            'net_salary' => 5000000,
            'components' => [],
        ])->load(['employee', 'payrollRun']);

        $html = (new PayslipPublishedMail($detail, $company, 'fake-pdf-binary'))->render();

        $this->assertStringContainsString('HRIS Beacon', $html);
        $this->assertStringContainsString('PT Payroll Email', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('logo_be2.png', $html);
    }

    public function test_payslip_pdf_hides_benefits_when_email_flag_is_enabled(): void
    {
        $company = Company::create(['name' => 'PT Payroll Email']);
        $employee = Employee::create([
            'employee_code' => 'EMP-BENEFIT',
            'company_id' => $company->id,
            'full_name' => 'Employee Benefit',
            'email' => 'benefit@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $run = PayrollRun::create([
            'period' => '2026-05',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $detail = PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'total_earning' => 5000000,
            'total_deduction' => 0,
            'net_salary' => 5000000,
            'components' => [],
        ])->load(['employee', 'payrollRun']);
        $bpjsData = [
            'items' => [
                ['label' => 'BPJS Kesehatan Perusahaan', 'amount' => 200000, 'is_basis' => false],
            ],
            'total' => 200000,
        ];
        $loanSummary = ['items' => [], 'total' => 0];
        $logoBase64 = null;

        $regularPdfHtml = Blade::render(
            file_get_contents(resource_path('views/admin/payslips/pdf.blade.php')),
            compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary')
        );

        $hideBenefits = true;
        $emailPdfHtml = Blade::render(
            file_get_contents(resource_path('views/admin/payslips/pdf.blade.php')),
            compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary', 'hideBenefits')
        );

        $this->assertStringContainsString('Benefits*', $regularPdfHtml);
        $this->assertStringNotContainsString('Benefits*', $emailPdfHtml);
        $this->assertStringNotContainsString('BPJS Kesehatan Perusahaan', $emailPdfHtml);
    }

    public function test_payslip_pdf_uses_legacy_layout_with_indonesian_income_and_expense_labels(): void
    {
        $company = Company::create(['name' => 'PT Payroll Email']);
        $employee = Employee::create([
            'employee_code' => 'EMP-LAYOUT',
            'company_id' => $company->id,
            'department_id' => null,
            'full_name' => 'Employee Layout',
            'email' => 'layout@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $run = PayrollRun::create([
            'period' => '2026-05',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $detail = PayrollRunDetail::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'total_earning' => 5500000,
            'total_deduction' => 650000,
            'net_salary' => 4850000,
            'components' => [
                ['name' => 'Tunjangan', 'type' => 'earning', 'amount' => 500000],
                ['name' => 'Potongan', 'type' => 'deduction', 'amount' => 500000],
                ['name' => 'BPJS Kesehatan', 'type' => 'deduction', 'amount' => 50000],
                ['name' => 'JHT Karyawan', 'type' => 'deduction', 'amount' => 100000],
            ],
        ])->load(['employee', 'payrollRun']);
        $bpjsData = ['items' => [], 'total' => 0];
        $loanSummary = ['items' => [], 'total' => 0];
        $logoBase64 = null;

        $html = Blade::render(
            file_get_contents(resource_path('views/admin/payslips/pdf.blade.php')),
            compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary')
        );

        $this->assertStringContainsString('Payroll cut off', $html);
        $this->assertStringContainsString('doc-title', $html);
        $this->assertStringContainsString('split-panels', $html);
        $this->assertStringContainsString('panel-left', $html);
        $this->assertStringContainsString('panel-right', $html);
        $this->assertStringContainsString('pay-panel', $html);
        $this->assertStringContainsString('>Pemasukan</th>', $html);
        $this->assertStringContainsString('>Pengeluaran</th>', $html);
        $this->assertStringContainsString('>Nominal</th>', $html);
        $this->assertStringContainsString('panel-total', $html);
        $this->assertStringContainsString('blank-row', $html);
        $this->assertStringContainsString('Take Home Pay', $html);
        $this->assertStringContainsString('ID / Name', $html);
        $this->assertStringNotContainsString('<th style="width:40%; border-right:none;">Pemasukan</th>', $html);
        $this->assertStringNotContainsString('class="divider">Total pengeluaran', $html);
        $this->assertStringNotContainsString('section-title">Pemasukan', $html);
        $this->assertStringNotContainsString('>Earnings<', $html);
        $this->assertStringNotContainsString('>Deductions<', $html);
    }

    private function createPayrollPublishSchema(): void
    {
        foreach ([
            'payroll_run_details',
            'payroll_logs',
            'payroll_runs',
            'employee_payrolls',
            'employees',
            'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
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

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('ptkp_status')->nullable();
            $table->string('npwp')->nullable();
            $table->string('bpjs_kesehatan')->nullable();
            $table->string('bpjs_ketenagakerjaan')->nullable();
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
