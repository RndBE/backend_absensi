<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EmployeeController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminEmployeeDestroyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
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
            $table->string('resign_reason')->nullable();
            $table->text('resign_notes')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->boolean('is_active')->default(true);
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id']);
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
            'fcm_token' => 'token-hp-karyawan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Employee::findOrFail(1)->createToken('mobile-app');

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

        DB::table('employees')->insert([
            'id' => 2,
            'employee_code' => 'ADM001',
            'company_id' => 1,
            'full_name' => 'Admin Tester',
            'email' => 'admin@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        session(['admin_id' => 2]);

        $response = (new EmployeeController())->destroy(1);

        $this->assertSame(route('admin.employees.index'), $response->getTargetUrl());
        $this->assertDatabaseHas('employees', ['id' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('employee_payrolls', ['employee_id' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('employee_payroll_components', ['employee_id' => 1, 'is_active' => false]);
        $this->assertSignedOutOfApp(1);
    }

    public function test_resign_signs_the_employee_out_of_the_app(): void
    {
        $this->seedEmployee(1, 'EMP001', 'employee', 'token-hp-karyawan');
        $this->seedEmployee(2, 'ADM001', 'superadmin');
        Employee::findOrFail(1)->createToken('mobile-app');
        Employee::findOrFail(2)->createToken('mobile-app');
        session(['admin_id' => 2]);

        $request = Request::create('/admin/employees/1/resign', 'POST', [
            'resign_date' => '2026-10-30',
            'last_working_date' => '2026-10-31',
            'resign_reason' => 'voluntary',
        ]);
        (new EmployeeController())->processResign($request, 1);

        $this->assertDatabaseHas('employees', ['id' => 1, 'is_active' => false]);
        $this->assertSignedOutOfApp(1);
        // Akun lain tidak ikut keluar.
        $this->assertSame(1, Employee::findOrFail(2)->tokens()->count());
    }

    private function seedEmployee(int $id, string $code, string $role, ?string $fcmToken = null): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'employee_code' => $code,
            'company_id' => 1,
            'full_name' => $code,
            'email' => strtolower($code).'@example.test',
            'password' => 'password',
            'is_active' => true,
            'role' => $role,
            'fcm_token' => $fcmToken,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertSignedOutOfApp(int $employeeId): void
    {
        $employee = Employee::findOrFail($employeeId);
        $this->assertSame(0, $employee->tokens()->count());
        $this->assertNull($employee->fcm_token);
    }
}
