<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollReportQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('payroll_run_details');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('employee_code')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->string('status')->default('draft');
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
    }

    public function test_payroll_report_filters_by_period_and_admin_company(): void
    {
        DB::table('employees')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'employee_code' => 'ADM',
                'email' => 'admin@example.test',
                'password' => 'secret',
                'full_name' => 'Admin',
                'role' => 'admin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'employee_code' => 'EMP1',
                'email' => 'employee-1@example.test',
                'password' => 'secret',
                'full_name' => 'Employee One',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'company_id' => 2,
                'employee_code' => 'EMP2',
                'email' => 'employee-2@example.test',
                'password' => 'secret',
                'full_name' => 'Employee Two',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->createPayrollDetail(1, '2026-05', 2, 200);
        $this->createPayrollDetail(2, '2026-05', 3, 300);
        $this->createPayrollDetail(3, '2026-04', 2, 400);

        session(['admin_id' => 1]);

        $response = (new ReportController())->payroll(Request::create('/admin/reports/payroll', 'GET', [
            'period' => '2026-05',
        ]));

        $this->assertInstanceOf(View::class, $response);
        $details = $response->getData()['details'];
        $totals = $response->getData()['totals'];

        $this->assertSame([2], $details->pluck('employee_id')->all());
        $this->assertSame(1, $totals['count']);
        $this->assertEquals(200.0, (float) $totals['earning']);
    }

    private function createPayrollDetail(int $runId, string $period, int $employeeId, int $earning): void
    {
        DB::table('payroll_runs')->insert([
            'id' => $runId,
            'period' => $period,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payroll_run_details')->insert([
            'payroll_run_id' => $runId,
            'employee_id' => $employeeId,
            'basic_salary' => $earning,
            'total_earning' => $earning,
            'total_deduction' => 0,
            'net_salary' => $earning,
            'components' => json_encode([]),
            'is_manual_edited' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
