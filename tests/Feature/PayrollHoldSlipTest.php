<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Jobs\SendPayslipEmailJob;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\LoanRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollLog;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class PayrollHoldSlipTest extends TestCase
{
    private Company $company;

    private Employee $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPayrollHoldSchema();
        Http::fake();

        $this->company = Company::create(['name' => 'PT Tahan Slip']);
        $this->admin = $this->makeEmployee('ADM-HOLD', 'Admin Payroll', 'admin');
        session(['admin_id' => $this->admin->id]);
    }

    public function test_publish_skips_held_slip_and_hides_it_from_employee(): void
    {
        Queue::fake();

        [$run, $ready, $held] = $this->finalizedRunWithTwoSlips();

        $this->hold($run, $held, 'Lembur belum masuk');
        (new PayrollRunController)->publish($run->id);

        $this->assertSame('published', $run->fresh()->status);
        Queue::assertPushed(SendPayslipEmailJob::class, 1);
        Queue::assertPushed(SendPayslipEmailJob::class, fn ($job) => $job->payrollRunDetailId === $ready->id);
        $this->assertSame([$ready->id], PayrollRunDetail::visibleToEmployee()->pluck('id')->all());

        $held->refresh();
        $this->assertTrue($held->is_held);
        $this->assertSame('Lembur belum masuk', $held->hold_reason);
        $this->assertSame($this->admin->id, (int) $held->held_by);
        $this->assertNotNull($held->held_at);
        $this->assertTrue(PayrollLog::where('payroll_run_id', $run->id)->where('action', 'held')->exists());
    }

    public function test_releasing_on_a_published_run_shows_the_slip_and_sends_its_email(): void
    {
        Queue::fake();

        [$run, $ready, $held] = $this->finalizedRunWithTwoSlips();
        $this->hold($run, $held, 'Cek ulang potongan');
        (new PayrollRunController)->publish($run->id);

        (new PayrollRunController)->releaseDetail($run->id, $held->id);

        $held->refresh();
        $this->assertFalse($held->is_held);
        $this->assertNull($held->hold_reason);
        $this->assertNull($held->held_by);
        Queue::assertPushed(SendPayslipEmailJob::class, 2);
        Queue::assertPushed(SendPayslipEmailJob::class, fn ($job) => $job->payrollRunDetailId === $held->id);
        $this->assertEqualsCanonicalizing([$ready->id, $held->id], PayrollRunDetail::visibleToEmployee()->pluck('id')->all());
        $this->assertTrue(PayrollLog::where('payroll_run_id', $run->id)->where('action', 'released')->exists());
    }

    public function test_releasing_before_publish_does_not_send_email_yet(): void
    {
        Queue::fake();

        [$run, , $held] = $this->finalizedRunWithTwoSlips();
        $this->hold($run, $held, 'Menunggu data');

        (new PayrollRunController)->releaseDetail($run->id, $held->id);

        $this->assertFalse($held->fresh()->is_held);
        Queue::assertNothingPushed();
    }

    public function test_hold_on_published_run_hides_slip_that_was_already_visible(): void
    {
        Queue::fake();

        [$run, $ready, $other] = $this->finalizedRunWithTwoSlips();
        (new PayrollRunController)->publish($run->id);
        $this->assertCount(2, PayrollRunDetail::visibleToEmployee()->get());

        $this->hold($run, $other, 'Salah hitung lembur');

        $this->assertSame([$ready->id], PayrollRunDetail::visibleToEmployee()->pluck('id')->all());
    }

    public function test_hold_is_refused_on_a_locked_run_and_requires_a_reason(): void
    {
        [$run, , $held] = $this->finalizedRunWithTwoSlips();

        $this->expectValidationErrorOnHold($run, $held, '');
        $this->assertFalse($held->fresh()->is_held);

        $run->update(['status' => 'locked', 'locked_at' => now()]);
        $this->hold($run, $held, 'Terlambat');

        $this->assertFalse($held->fresh()->is_held);
    }

    public function test_lock_is_refused_while_a_slip_is_held(): void
    {
        [$run, , $held] = $this->finalizedRunWithTwoSlips();
        $this->hold($run, $held, 'Belum benar');

        (new PayrollRunController)->lock($run->id);
        $this->assertSame('finalized', $run->fresh()->status);

        (new PayrollRunController)->releaseDetail($run->id, $held->id);
        (new PayrollRunController)->lock($run->id);
        $this->assertSame('locked', $run->fresh()->status);
    }

    public function test_held_slip_can_be_edited_after_publish_but_other_slips_cannot(): void
    {
        Queue::fake();

        [$run, $ready, $held] = $this->finalizedRunWithTwoSlips();
        $this->hold($run, $held, 'Tunjangan kurang');
        (new PayrollRunController)->publish($run->id);

        $payload = fn () => Request::create('/x', 'PUT', [
            'components' => [
                ['name' => 'Tunjangan Transport', 'type' => 'earning', 'amount' => '300000', 'category' => 'recurring', 'is_taxable' => '0', 'is_auto' => '0', 'detail' => ''],
            ],
        ]);

        (new PayrollRunController)->updateDetail($payload(), $run->id, $held->id);
        (new PayrollRunController)->updateDetail($payload(), $run->id, $ready->id);

        $this->assertSame(5300000.0, (float) $held->fresh()->total_earning);
        $this->assertSame(5000000.0, (float) $ready->fresh()->total_earning, 'Slip yang sudah terbit tidak boleh berubah.');
        $this->assertSame(10300000.0, (float) $run->fresh()->total_earning);
        $this->assertTrue($held->fresh()->is_held, 'Edit tidak boleh melepas tahanan slip.');
    }

    public function test_regenerating_a_held_slip_after_finalize_touches_only_that_employee_and_deducts_loan_once(): void
    {
        Queue::fake();

        $employee = $this->makeEmployee('EMP-REGEN', 'Karyawan Ditahan');
        $other = $this->makeEmployee('EMP-OTHER', 'Karyawan Lain');
        $payroll = $this->makePayroll($employee, 5000000);
        $this->makePayroll($other, 4000000);

        $loan = LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 3000000,
            'total_repayable' => 3000000,
            'installment_count' => 3,
            'monthly_installment' => 1000000,
            'remaining_amount' => 3000000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $this->admin->id]);
        $controller = new PayrollRunController;
        $this->invokePrivate($controller, 'generateDetails', [$run, [$employee->id, $other->id]]);
        $controller->finalize($run->id);
        $this->assertSame(2000000.0, (float) $loan->fresh()->remaining_amount);

        $heldDetail = $this->detailOf($run, $employee);
        $otherDetail = $this->detailOf($run, $other);
        $this->hold($run, $heldDetail, 'Gaji pokok salah input');
        $controller->publish($run->id);

        // Gaji pokok dikoreksi di master payroll, lalu slip yang ditahan di-regenerate.
        $payroll->update(['basic_salary' => 6000000]);
        $controller->regenerateDetail($run->id, $heldDetail->id);

        $regenerated = $this->detailOf($run, $employee);
        $this->assertSame(6000000.0, (float) $regenerated->basic_salary);
        $this->assertTrue($regenerated->is_held);
        $this->assertSame('Gaji pokok salah input', $regenerated->hold_reason);
        $this->assertSame('published', $run->fresh()->status);

        // Cicilan pinjaman tetap terpotong sekali untuk periode ini.
        $this->assertSame(2000000.0, (float) $loan->fresh()->remaining_amount);
        $this->assertSame(1, DB::table('loan_repayments')->where('loan_request_id', $loan->id)->count());
        $loanComponent = collect($regenerated->components)->firstWhere('name', 'Potongan Pinjaman');
        $this->assertTrue($loanComponent['loan']['balance_applied']);
        $this->assertSame(1, (int) $loanComponent['loan']['installment_number']);

        // Slip karyawan lain tidak disentuh sama sekali.
        $this->assertSame($otherDetail->id, $this->detailOf($run, $other)->id);
        $this->assertEquals($otherDetail->updated_at, $this->detailOf($run, $other)->updated_at);

        $this->assertEqualsWithDelta(
            (float) $regenerated->net_salary + (float) $otherDetail->net_salary,
            (float) $run->fresh()->total_net,
            0.01
        );
    }

    public function test_regenerating_a_held_slip_keeps_the_final_loan_installment(): void
    {
        $employee = $this->makeEmployee('EMP-LUNAS', 'Karyawan Cicilan Terakhir');
        $this->makePayroll($employee, 5000000);
        $loan = LoanRequest::create([
            'employee_id' => $employee->id,
            'amount' => 3000000,
            'total_repayable' => 3000000,
            'installment_count' => 3,
            'monthly_installment' => 1000000,
            'remaining_amount' => 1000000,
            'start_period' => '2026-04',
            'status' => 'active',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $this->admin->id]);
        $controller = new PayrollRunController;
        $this->invokePrivate($controller, 'generateDetails', [$run, [$employee->id]]);
        $controller->finalize($run->id);
        $this->assertSame('paid', $loan->fresh()->status);

        // Finalize melunasi pinjaman. Tanpa membatalkan potongan run ini dulu, regenerate
        // melihat pinjaman sudah lunas dan slip baru kehilangan potongan cicilan terakhir.
        $detail = $this->detailOf($run, $employee);
        $this->hold($run, $detail, 'Cek cicilan');
        $controller->regenerateDetail($run->id, $detail->id);

        $regenerated = $this->detailOf($run, $employee);
        $loanComponent = collect($regenerated->components)->firstWhere('name', 'Potongan Pinjaman');
        $this->assertNotNull($loanComponent, 'Potongan cicilan terakhir harus tetap ada di slip.');
        $this->assertSame(1000000.0, (float) $loanComponent['amount']);
        $this->assertSame('paid', $loan->fresh()->status);
        $this->assertSame(0.0, (float) $loan->fresh()->remaining_amount);
        $this->assertSame(1, DB::table('loan_repayments')->where('loan_request_id', $loan->id)->count());
    }

    public function test_regenerating_a_published_slip_that_is_not_held_is_refused(): void
    {
        Queue::fake();

        $employee = $this->makeEmployee('EMP-NOHOLD', 'Karyawan Biasa');
        $payroll = $this->makePayroll($employee, 5000000);
        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $this->admin->id]);
        $controller = new PayrollRunController;
        $this->invokePrivate($controller, 'generateDetails', [$run, [$employee->id]]);
        $controller->finalize($run->id);
        $controller->publish($run->id);

        $detail = $this->detailOf($run, $employee);
        $payroll->update(['basic_salary' => 7000000]);
        $controller->regenerateDetail($run->id, $detail->id);

        $this->assertSame(5000000.0, (float) $this->detailOf($run, $employee)->basic_salary);
    }

    public function test_regenerating_a_held_slip_keeps_its_payroll_adjustment(): void
    {
        $employee = $this->makeEmployee('EMP-ADJ', 'Karyawan Rapel');
        $this->makePayroll($employee, 5000000);
        $adjustment = PayrollAdjustment::create([
            'employee_id' => $employee->id,
            'type' => 'rapel',
            'earning_type' => 'earning',
            'name' => 'Kekurangan lembur Mei',
            'amount' => 250000,
            'target_period' => '2026-06',
            'status' => 'pending',
        ]);

        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $this->admin->id]);
        $controller = new PayrollRunController;
        $this->invokePrivate($controller, 'generateDetails', [$run, [$employee->id]]);
        $controller->finalize($run->id);
        $this->assertSame('applied', $adjustment->fresh()->status);

        $detail = $this->detailOf($run, $employee);
        $this->hold($run, $detail, 'Cek rapel');
        $controller->regenerateDetail($run->id, $detail->id);

        $regenerated = $this->detailOf($run, $employee);
        $this->assertNotNull(collect($regenerated->components)->firstWhere('name', 'Rapel: Kekurangan lembur Mei'));
        $this->assertSame('applied', $adjustment->fresh()->status);
        $this->assertSame($run->id, (int) $adjustment->fresh()->payroll_run_id);
    }

    public function test_regenerating_a_held_slip_without_payroll_data_keeps_the_old_slip(): void
    {
        $employee = $this->makeEmployee('EMP-GONE', 'Karyawan Tanpa Payroll');
        $payroll = $this->makePayroll($employee, 5000000);
        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $this->admin->id]);
        $controller = new PayrollRunController;
        $this->invokePrivate($controller, 'generateDetails', [$run, [$employee->id]]);

        $detail = $this->detailOf($run, $employee);
        $this->hold($run, $detail, 'Data payroll dicek');
        $payroll->delete();

        $controller->regenerateDetail($run->id, $detail->id);

        $this->assertNotNull(PayrollRunDetail::find($detail->id), 'Slip lama tidak boleh hilang saat regenerate gagal.');
        $this->assertTrue($detail->fresh()->is_held);
    }

    public function test_whole_run_regenerate_keeps_hold_state(): void
    {
        $employee = $this->makeEmployee('EMP-DRAFT', 'Karyawan Draft');
        $this->makePayroll($employee, 5000000);
        $run = PayrollRun::create(['period' => '2026-06', 'created_by' => $this->admin->id]);
        $controller = new PayrollRunController;
        $this->invokePrivate($controller, 'generateDetails', [$run, [$employee->id]]);

        $this->hold($run, $this->detailOf($run, $employee), 'Tunggu konfirmasi');
        $controller->regenerate($run->id);

        $detail = $this->detailOf($run, $employee);
        $this->assertTrue($detail->is_held);
        $this->assertSame('Tunggu konfirmasi', $detail->hold_reason);
    }

    public function test_queued_email_job_skips_a_slip_held_after_it_was_queued(): void
    {
        Mail::fake();

        [$run, , $held] = $this->finalizedRunWithTwoSlips();
        $run->update(['status' => 'published', 'published_at' => now()]);
        $this->hold($run, $held, 'Ditahan setelah publish');

        (new SendPayslipEmailJob($held->id))->handle();

        Mail::assertNothingSent();
    }

    /** @return array{0: PayrollRun, 1: PayrollRunDetail, 2: PayrollRunDetail} */
    private function finalizedRunWithTwoSlips(): array
    {
        $run = PayrollRun::create([
            'period' => '2026-06',
            'status' => 'finalized',
            'finalized_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $details = collect(['A', 'B'])->map(function (string $suffix) use ($run) {
            $employee = $this->makeEmployee('EMP-'.$suffix, 'Karyawan '.$suffix);

            return PayrollRunDetail::create([
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'basic_salary' => 5000000,
                'total_earning' => 5000000,
                'total_deduction' => 0,
                'net_salary' => 5000000,
                'components' => [],
            ]);
        });

        return [$run, $details[0], $details[1]];
    }

    private function hold(PayrollRun $run, PayrollRunDetail $detail, string $reason): void
    {
        $request = Request::create('/x', 'POST', ['reason' => $reason]);

        (new PayrollRunController)->holdDetail($request, $run->id, $detail->id);
    }

    private function expectValidationErrorOnHold(PayrollRun $run, PayrollRunDetail $detail, string $reason): void
    {
        try {
            $this->hold($run, $detail, $reason);
            $this->fail('Tahan slip tanpa alasan harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
    }

    private function detailOf(PayrollRun $run, Employee $employee): PayrollRunDetail
    {
        return PayrollRunDetail::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
    }

    private function makeEmployee(string $code, string $name, string $role = 'employee'): Employee
    {
        return Employee::create([
            'employee_code' => $code,
            'company_id' => $this->company->id,
            'full_name' => $name,
            'email' => strtolower($code).'@example.test',
            'password' => 'secret',
            'role' => $role,
            'is_active' => true,
            'ptkp' => 'TK/0',
        ]);
    }

    private function makePayroll(Employee $employee, float $basicSalary): EmployeePayroll
    {
        return EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => $basicSalary,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'is_exempt_penalty' => true,
            'late_penalty_per_day' => 0,
            'overtime_multiplier' => 0,
            'tax_method' => 'nett',
        ]);
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }

    private function createPayrollHoldSchema(): void
    {
        foreach ([
            'payroll_run_details',
            'payroll_logs',
            'payroll_runs',
            'payroll_adjustments',
            'employee_payroll_components',
            'employee_payrolls',
            'payroll_components',
            'loan_repayments',
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
            $table->json('manual_overrides')->nullable();
            $table->boolean('is_held')->default(false);
            $table->string('hold_reason', 500)->nullable();
            $table->timestamp('held_at')->nullable();
            $table->unsignedBigInteger('held_by')->nullable();
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

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('period', 7)->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['loan_request_id', 'payroll_run_id'], 'loan_repayments_loan_run_unique');
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
}
