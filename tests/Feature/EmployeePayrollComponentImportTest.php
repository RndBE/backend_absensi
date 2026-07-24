<?php

namespace Tests\Feature;

use App\Support\SimpleXlsxExporter;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeePayrollComponentImportTest extends TestCase
{
    private array $tempPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'loan_requests',
            'employee_payroll_components',
            'employee_payrolls',
            'payroll_components',
            'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('employee_code')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('category')->default('fixed');
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_auto')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('payment_schedule')->default('monthly');
            $table->string('payment_method')->default('transfer');
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
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

        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_repayable', 15, 2)->default(0);
            $table->unsignedSmallInteger('installment_count');
            $table->decimal('monthly_installment', 15, 2);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('start_period', 7)->nullable();
            $table->text('purpose')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_import_salary_master_components_as_employee_assignments(): void
    {
        $file = $this->uploadedSalaryMasterXlsx([
            ['PT Arta Teknologi Comunindo'],
            ['Salary Report 3 2026'],
            ['Employee ID', 'Full Name', 'Job Position', 'Organization', 'Basic Salary', 'Allowance', '', '', 'Deduction', ''],
            ['', '', '', '', '', 'Tunjangan Profesi', 'Tunjangan Fasilitas Kesehatan', 'Tunjangan Tetap Lainnya', 'BPJS K Employee', 'JHT Employees'],
            ['004/SOFTW/XII/2025', 'Shandy Bagus', 'Software', 'Software', 2624387, 520000, 0, 225000, 40993.87, 81987.74],
            ['GRAND TOTAL', '', '', '', 2624387, 520000, 0, 225000, 40993.87, 81987.74],
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.payroll-components.import-assignments'), [
                'effective_date' => '2026-06-01',
                'component_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import komponen payroll selesai: 1 karyawan, 5 assignment dibuat/diperbarui, 0 dilewati.');

        $this->assertDatabaseHas('employee_payrolls', [
            'employee_id' => 2,
            'basic_salary' => 2624387,
            'is_active' => true,
        ]);
        $this->assertStringStartsWith('2026-06-01', (string) DB::table('employee_payrolls')->where('employee_id', 2)->value('effective_date'));

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 10,
            'amount' => 520000,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 11,
            'amount' => 0,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 20,
            'amount' => 40993.87,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 21,
            'amount' => 81987.74,
            'is_active' => true,
        ]);
        $this->assertSame(5, DB::table('employee_payroll_components')->where('employee_id', 2)->where('start_date', 'like', '2026-06-01%')->count());
    }

    public function test_imported_pinjaman_component_creates_visible_loan_request(): void
    {
        $file = $this->uploadedSalaryMasterXlsx([
            ['PT Arta Teknologi Comunindo'],
            ['Salary Report 3 2026'],
            ['Employee ID', 'Full Name', 'Job Position', 'Organization', 'Basic Salary', 'Deduction'],
            ['', '', '', '', '', 'Pinjaman'],
            ['004/SOFTW/XII/2025', 'Shandy Bagus', 'Software', 'Software', 2624387, 400000],
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.payroll-components.import-assignments'), [
                'effective_date' => '2026-06-01',
                'component_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import komponen payroll selesai: 1 karyawan, 1 assignment dibuat/diperbarui, 0 dilewati.');

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 30,
            'amount' => 400000,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('loan_requests', [
            'employee_id' => 2,
            'amount' => 400000,
            'total_repayable' => 400000,
            'installment_count' => 1,
            'monthly_installment' => 400000,
            'remaining_amount' => 400000,
            'start_period' => '2026-06',
            'status' => 'active',
        ]);
    }

    public function test_payroll_component_index_exposes_assignment_import_modal(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-components/index.blade.php'));

        $this->assertStringContainsString("route('admin.payroll-components.import-assignments')", $view);
        $this->assertStringContainsString('data-component-import-open', $view);
        $this->assertStringContainsString('name="component_file"', $view);
        $this->assertStringContainsString('KOMPONEN MASTER PAYROL FIX', $view);
    }

    public function test_employee_payroll_index_does_not_show_component_import_modal(): void
    {
        $view = file_get_contents(resource_path('views/admin/employee-payrolls/index.blade.php'));

        $this->assertStringNotContainsString("route('admin.employee-payrolls.import-components')", $view);
        $this->assertStringNotContainsString('data-component-import-open', $view);
    }

    public function test_assignment_edit_form_exposes_start_date_field(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-components/employees.blade.php'));

        $this->assertStringContainsString('name="start_date"', $view);
        $this->assertStringContainsString("optional(\$assign->start_date)->format('Y-m-d')", $view);
        $this->assertStringContainsString('Tanggal mulai', $view);
    }

    public function test_assign_employee_defaults_start_date_to_today_when_empty(): void
    {
        Carbon::setTestNow('2026-07-24 09:00:00');

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.payroll-components.assign-employee', 20), [
                'employee_ids' => [2],
                'amount' => 150000,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employee_payroll_components', [
            'employee_id' => 2,
            'payroll_component_id' => 20,
            'amount' => 150000,
            'start_date' => '2026-07-24 00:00:00',
            'is_active' => true,
        ]);
    }

    private function uploadedSalaryMasterXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'payroll-components-import-');
        file_put_contents($path, SimpleXlsxExporter::make($rows[0] ?? [], array_slice($rows, 1), 'KOMPONEN MASTER PAYROL FIX'));
        $this->tempPaths[] = $path;

        return new UploadedFile($path, 'komponen-master-payroll.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function seedFixtures(): void
    {
        DB::table('employees')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'employee_code' => 'ADM001',
                'email' => 'admin@example.test',
                'password' => 'secret',
                'full_name' => 'Admin Payroll',
                'role' => 'payroll_admin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'employee_code' => '004/SOFTW/XII/2025',
                'email' => 'shandy@example.test',
                'password' => 'secret',
                'full_name' => 'Shandy Bagus',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([
            ['id' => 10, 'name' => 'Tunjangan Profesi', 'type' => 'earning'],
            ['id' => 11, 'name' => 'Tunjangan Fasilitas Kesehatan', 'type' => 'earning'],
            ['id' => 12, 'name' => 'Tunjangan Tetap Lainnya', 'type' => 'earning'],
            ['id' => 20, 'name' => 'BPJS Kesehatan', 'type' => 'deduction'],
            ['id' => 21, 'name' => 'BPJS Ketenagakerjaan', 'type' => 'deduction'],
            ['id' => 30, 'name' => 'Pinjaman', 'type' => 'deduction'],
        ] as $component) {
            DB::table('payroll_components')->insert([
                'id' => $component['id'],
                'name' => $component['name'],
                'type' => $component['type'],
                'category' => 'fixed',
                'default_amount' => 0,
                'is_taxable' => $component['type'] === 'earning',
                'is_auto' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
