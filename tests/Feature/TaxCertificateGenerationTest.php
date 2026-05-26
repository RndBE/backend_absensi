<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaxCertificateGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tax_certificates');
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

        Schema::create('tax_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->integer('tax_year');
            $table->string('certificate_number')->nullable();
            $table->decimal('gross_annual', 15, 2)->default(0);
            $table->decimal('tax_annual', 15, 2)->default(0);
            $table->decimal('bpjs_annual', 15, 2)->default(0);
            $table->decimal('nett_annual', 15, 2)->default(0);
            $table->json('monthly_details')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['employee_id', 'tax_year']);
        });
    }

    public function test_generate_bukti_potong_only_uses_payroll_details_from_selected_tax_year(): void
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
                'employee_code' => 'EMP',
                'email' => 'employee@example.test',
                'password' => 'secret',
                'full_name' => 'Employee',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->createPayrollDetail(1, '2025-12', 'published', 2, 100);
        $this->createPayrollDetail(2, '2026-01', 'published', 2, 200);
        $this->createPayrollDetail(3, '2026-05', 'published', 2, 300);

        $this->withSession(['admin_id' => 1])
            ->from(route('admin.tax.bukti-potong', ['year' => 2026]))
            ->post(route('admin.tax.generate-bukti-potong'), [
                'employee_id' => 2,
                'tax_year' => 2026,
            ])
            ->assertRedirect(route('admin.tax.bukti-potong', ['year' => 2026]));

        $certificate = DB::table('tax_certificates')
            ->where('employee_id', 2)
            ->where('tax_year', 2026)
            ->first();

        $this->assertNotNull($certificate);
        $this->assertEquals(500.0, (float) $certificate->gross_annual);
        $this->assertSame(['2026-01', '2026-05'], array_keys(json_decode($certificate->monthly_details, true)));
    }

    private function createPayrollDetail(int $runId, string $period, string $status, int $employeeId, int $earning): void
    {
        DB::table('payroll_runs')->insert([
            'id' => $runId,
            'period' => $period,
            'status' => $status,
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
