<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EmployeeController;
use App\Models\BpjsSetting;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\TaxSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResignPph21PreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('ptkp_status')->nullable();
            $table->string('tax_method')->default('gross');
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (['tax_settings', 'bpjs_settings'] as $tableName) {
            Schema::dropIfExists($tableName);
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

        $this->seedRates();
    }

    public function test_resign_preview_bruto_includes_employer_kesehatan_jkk_jkm(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP-RSG-PRV',
            'company_id' => 1,
            'full_name' => 'Employee Resign Preview',
            'email' => 'resign-preview@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
            'join_date' => '2026-01-01',
        ]);

        EmployeePayroll::create([
            'employee_id' => $employee->id,
            'basic_salary' => 10_000_000,
            'ptkp_status' => 'TK/0',
            'tax_method' => 'gross',
            'effective_date' => '2026-01-01',
            'is_active' => true,
        ]);

        session(['admin_id' => $employee->id]);

        $response = (new EmployeeController)->resign($employee->id);
        $preview = $response->getData()['pph21Preview'];

        // Bruto must include employer Kesehatan 4% (400k) + JKK 0.24% (24k) + JKM 0.3% (30k) = 454k.
        $this->assertSame(10_454_000.0, (float) $preview['avg_bruto_monthly']);
    }

    private function seedRates(): void
    {
        foreach ([
            'kes_rate' => ['company' => 4, 'employee' => 1],
            'jht_rate' => ['company' => 3.7, 'employee' => 2],
            'jkk_rate' => ['company' => 0.24, 'employee' => 0],
            'jkm_rate' => ['company' => 0.3, 'employee' => 0],
        ] as $key => $value) {
            BpjsSetting::create(['key' => $key, 'value' => $value, 'effective_date' => '2024-01-01', 'is_active' => true]);
        }
        BpjsSetting::create(['key' => 'kes_cap', 'value' => ['salary_cap' => 12_000_000], 'effective_date' => '2024-01-01', 'is_active' => true]);

        TaxSetting::create([
            'key' => 'ptkp_values',
            'effective_date' => '2024-01-01',
            'value' => ['TK/0' => 54_000_000],
            'description' => 'PTKP',
        ]);
        TaxSetting::create([
            'key' => 'pph21_brackets',
            'effective_date' => '2024-01-01',
            'value' => [['min' => 0, 'max' => 60_000_000, 'rate' => 5]],
            'description' => 'Progressive',
        ]);
        TaxSetting::create([
            'key' => 'biaya_jabatan',
            'effective_date' => '2024-01-01',
            'value' => ['percentage' => 5, 'max_monthly' => 500_000, 'max_annual' => 6_000_000],
            'description' => 'Biaya jabatan',
        ]);
    }
}
