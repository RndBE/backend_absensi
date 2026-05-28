<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EmployeeController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminEmployeeDestroyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_payroll_components');
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('employment_status')->default('contract');
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->date('resign_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->boolean('is_active')->default(true);
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_destroy_deactivates_employee_and_active_payroll_records(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'employee_code' => 'EMP001',
            'company_id' => 1,
            'full_name' => 'Test Employee',
            'email' => 'employee@example.test',
            'password' => 'password',
            'employment_status' => 'contract',
            'is_active' => true,
            'role' => 'employee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_payrolls')->insert([
            'employee_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_payroll_components')->insert([
            'employee_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = (new EmployeeController())->destroy(1);

        $this->assertSame(route('admin.employees.index'), $response->getTargetUrl());
        $this->assertDatabaseHas('employees', ['id' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('employee_payrolls', ['employee_id' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('employee_payroll_components', ['employee_id' => 1, 'is_active' => false]);
    }
}
