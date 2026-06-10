<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EmployeePayrollController;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeePayrollPenaltySettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createEmployeePayrollSchema();
    }

    public function test_payroll_update_saves_penalty_exemption_settings(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP-PAY-001',
            'company_id' => 1,
            'full_name' => 'Payroll Settings Tester',
            'email' => 'payroll-settings@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 5000000,
            'payment_schedule' => 'monthly',
            'payment_method' => 'transfer',
            'effective_date' => '2026-06-01',
            'is_active' => true,
        ]);

        $request = Request::create('/admin/employee-payrolls/'.$employee->id, 'PUT', [
            'basic_salary' => 6000000,
            'payment_schedule' => 'monthly',
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Payroll Settings Tester',
            'npwp' => '09.123.456.7-890.000',
            'ptkp_status' => 'TK/0',
            'bpjs_kesehatan' => 'BPJS-KES',
            'bpjs_ketenagakerjaan' => 'BPJS-TK',
            'effective_date' => '2026-07-01',
            'is_exempt_penalty' => '1',
            'late_penalty_per_day' => '50000',
            'overtime_multiplier' => '1',
        ]);

        (new EmployeePayrollController)->updatePayroll($request, $employee->id);

        $payroll = EmployeePayroll::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($payroll->is_exempt_penalty);
        $this->assertSame('50000.00', $payroll->late_penalty_per_day);
        $this->assertSame('1.00', $payroll->overtime_multiplier);
    }

    private function createEmployeePayrollSchema(): void
    {
        foreach (['employee_payrolls', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
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
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('npwp')->nullable();
            $table->string('ptkp_status')->nullable();
            $table->string('bpjs_kesehatan')->nullable();
            $table->string('bpjs_ketenagakerjaan')->nullable();
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_exempt_penalty')->default(false);
            $table->decimal('late_penalty_per_day', 15, 2)->default(50000);
            $table->decimal('overtime_multiplier', 5, 2)->default(1);
            $table->timestamps();
        });
    }
}
