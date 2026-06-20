<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminEmployeeEmploymentStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('departments');
        Schema::dropIfExists('employees');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('employment_status')->default('contract');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('departments')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            'id' => 99,
            'company_id' => 1,
            'department_id' => 1,
            'employee_code' => 'ADM099',
            'full_name' => 'HR Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'role' => 'hr_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_create_and_update_outsourcing_employee_status(): void
    {
        $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->post(route('admin.employees.store'), [
                'employee_code' => 'OUT001',
                'full_name' => 'Outsource One',
                'email' => 'outsource-one@example.test',
                'password' => 'password123',
                'department_id' => 1,
                'employment_status' => 'outsourcing',
                'role' => 'employee',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employeeId = DB::table('employees')->where('employee_code', 'OUT001')->value('id');

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'OUT001',
            'employment_status' => 'outsourcing',
        ]);

        $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->put(route('admin.employees.update', $employeeId), [
                'employee_code' => 'OUT001',
                'full_name' => 'Outsource One Updated',
                'email' => 'outsource-one@example.test',
                'department_id' => 1,
                'employment_status' => 'outsourcing',
                'role' => 'employee',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $employeeId,
            'full_name' => 'Outsource One Updated',
            'employment_status' => 'outsourcing',
        ]);
    }
}
