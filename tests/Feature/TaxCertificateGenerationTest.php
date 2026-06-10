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
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('payroll_run_details');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('npwp')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

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

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('npwp')->nullable();
            $table->string('ptkp_status')->nullable();
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

        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Test',
            'npwp' => '0000000000000000',
            'address' => 'Test Address',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        $this->assertSame(['2026-01', '2026-05'], array_keys(json_decode($certificate->monthly_details, true)['months']));
    }

    public function test_generate_bukti_potong_sums_split_employee_bpjs_components(): void
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

        $this->createPayrollDetail(1, '2026-01', 'published', 2, 10000000, [
            ['name' => 'BPJS Kesehatan', 'type' => 'deduction', 'amount' => 100000],
            ['name' => 'JHT Karyawan', 'type' => 'deduction', 'amount' => 200000],
            ['name' => 'JP Karyawan', 'type' => 'deduction', 'amount' => 300000],
            ['name' => 'JKK Perusahaan', 'type' => 'info', 'amount' => 24000],
            ['name' => 'PPh 21', 'type' => 'deduction', 'amount' => 250000],
        ]);

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

        $monthly = json_decode($certificate->monthly_details, true);

        $this->assertEquals(600000.0, (float) $certificate->bpjs_annual);
        $this->assertEquals(250000.0, (float) $certificate->tax_annual);
        $this->assertSame([
            'BPJS Kesehatan',
            'JHT Karyawan',
            'JP Karyawan',
        ], array_column($monthly['months']['2026-01']['bpjs_components'], 'name'));
        $this->assertSame('draft', $certificate->status);
    }

    public function test_finalize_bukti_potong_changes_status_to_final(): void
    {
        DB::table('employees')->insert([
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
        ]);

        DB::table('tax_certificates')->insert([
            'id' => 10,
            'employee_id' => 1,
            'tax_year' => 2026,
            'certificate_number' => '1.1-000001-2026',
            'gross_annual' => 10000000,
            'tax_annual' => 250000,
            'bpjs_annual' => 600000,
            'nett_annual' => 9150000,
            'monthly_details' => json_encode(['months' => []]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['admin_id' => 1])
            ->from(route('admin.tax.bukti-potong', ['year' => 2026]))
            ->post(route('admin.tax.finalize-bukti-potong', 10))
            ->assertRedirect(route('admin.tax.bukti-potong', ['year' => 2026]));

        $this->assertSame('final', DB::table('tax_certificates')->where('id', 10)->value('status'));
    }

    private function createPayrollDetail(int $runId, string $period, string $status, int $employeeId, int $earning, array $components = []): void
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
            'components' => json_encode($components),
            'is_manual_edited' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
