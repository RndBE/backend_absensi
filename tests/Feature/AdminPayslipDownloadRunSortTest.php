<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayslipController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AdminPayslipDownloadRunSortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('payroll_run_details');
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->integer('job_level')->nullable();
            $table->date('join_date')->nullable();
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->date('effective_date')->nullable();
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

    public function test_download_run_join_date_sort_groups_by_level_then_oldest_join_date(): void
    {
        DB::table('companies')->insert(['id' => 1, 'name' => 'PT Test', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('departments')->insert(['id' => 1, 'name' => 'Ops', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_runs')->insert([
            'id' => 10,
            'period' => '2026-06',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertEmployee(1, 'Admin', 1, '2026-01-01');
        $this->insertEmployee(2, 'Staff Oldest Overall', 4, '2020-01-01');
        $this->insertEmployee(3, 'Director Newer', 1, '2024-01-01');
        $this->insertEmployee(4, 'Manager Newer', 2, '2021-01-01');
        $this->insertEmployee(5, 'Manager Oldest In Level', 2, '2019-01-01');

        foreach ([2, 3, 4, 5] as $employeeId) {
            DB::table('payroll_run_details')->insert([
                'payroll_run_id' => 10,
                'employee_id' => $employeeId,
                'basic_salary' => 100,
                'total_earning' => 100,
                'total_deduction' => 0,
                'net_salary' => 100,
                'components' => json_encode([]),
                'is_manual_edited' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        session(['admin_id' => 1]);

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('A4', 'portrait')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('payslips_2026-06.pdf')->andReturn(new Response('downloaded'));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('admin.payslips.pdf-bulk', Mockery::on(function (array $data) {
                $names = collect($data['payslips'])
                    ->pluck('detail.employee.full_name')
                    ->all();

                return $names === [
                    'Director Newer',
                    'Manager Oldest In Level',
                    'Manager Newer',
                    'Staff Oldest Overall',
                ];
            }))
            ->andReturn($pdf);

        $response = (new PayslipController())->downloadRunBundle(
            Request::create('/admin/payslips/run/10/download', 'GET', ['sort' => 'join_date']),
            10
        );

        $this->assertSame('downloaded', $response->getContent());
    }

    private function insertEmployee(int $id, string $name, int $level, string $joinDate): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'company_id' => 1,
            'department_id' => 1,
            'employee_code' => 'EMP'.$id,
            'email' => 'employee-'.$id.'@example.test',
            'password' => 'secret',
            'full_name' => $name,
            'position' => 'Role '.$level,
            'job_level' => $level,
            'join_date' => $joinDate,
            'role' => $id === 1 ? 'admin' : 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
