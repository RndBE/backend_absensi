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
