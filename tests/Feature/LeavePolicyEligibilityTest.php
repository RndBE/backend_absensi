<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LeavePolicyController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeavePolicyEligibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'leave_policy_employees',
            'leave_balances',
            'leave_policies',
            'leave_types',
            'employees',
            'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->date('join_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->default(12);
            $table->timestamps();
        });

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('days_per_year')->default(12);
            $table->integer('min_tenure_months')->default(0);
            $table->integer('max_carry_over')->default(0);
            $table->boolean('is_prorated')->default(false);
            $table->string('eligibility_type')->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_policy_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_policy_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
            $table->unique(['leave_policy_id', 'employee_id']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('year');
            $table->integer('total_days')->default(12);
            $table->integer('carry_over')->default(0);
            $table->integer('used_days')->default(0);
            $table->integer('remaining_days')->default(12);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
    }

    public function test_selected_leave_policy_generates_balance_only_for_selected_employees(): void
    {
        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Beacon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 10,
                'employee_code' => 'EMP-10',
                'company_id' => 1,
                'full_name' => 'Karyawan A',
                'email' => 'a@example.test',
                'join_date' => '2024-01-01',
                'is_active' => true,
                'role' => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'employee_code' => 'EMP-11',
                'company_id' => 1,
                'full_name' => 'Karyawan B',
                'email' => 'b@example.test',
                'join_date' => '2024-01-01',
                'is_active' => true,
                'role' => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('leave_types')->insert([
            'id' => 20,
            'name' => 'Cuti Melahirkan',
            'max_days' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_policies')->insert([
            'id' => 30,
            'company_id' => 1,
            'leave_type_id' => 20,
            'days_per_year' => 90,
            'min_tenure_months' => 0,
            'max_carry_over' => 0,
            'is_prorated' => false,
            'eligibility_type' => 'selected',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_policy_employees')->insert([
            'leave_policy_id' => 30,
            'employee_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('leave:generate-annual', [
            'year' => 2026,
            '--company' => 1,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => 10,
            'leave_type_id' => 20,
            'year' => 2026,
            'total_days' => 90,
            'remaining_days' => 90,
        ]);

        $this->assertDatabaseMissing('leave_balances', [
            'employee_id' => 11,
            'leave_type_id' => 20,
            'year' => 2026,
        ]);
    }

    public function test_all_employee_leave_policy_keeps_generating_for_every_active_employee(): void
    {
        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Beacon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 10,
                'employee_code' => 'EMP-10',
                'company_id' => 1,
                'full_name' => 'Karyawan A',
                'email' => 'a@example.test',
                'join_date' => '2024-01-01',
                'is_active' => true,
                'role' => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'employee_code' => 'EMP-11',
                'company_id' => 1,
                'full_name' => 'Karyawan B',
                'email' => 'b@example.test',
                'join_date' => '2024-01-01',
                'is_active' => true,
                'role' => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('leave_types')->insert([
            'id' => 20,
            'name' => 'Cuti Tahunan',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_policies')->insert([
            'id' => 30,
            'company_id' => 1,
            'leave_type_id' => 20,
            'days_per_year' => 12,
            'min_tenure_months' => 0,
            'max_carry_over' => 0,
            'is_prorated' => false,
            'eligibility_type' => 'all',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('leave:generate-annual', [
            'year' => 2026,
            '--company' => 1,
        ]);

        $this->assertSame(2, DB::table('leave_balances')->count());
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => 10,
            'leave_type_id' => 20,
            'year' => 2026,
        ]);
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => 11,
            'leave_type_id' => 20,
            'year' => 2026,
        ]);
    }

    public function test_zero_tenure_policy_generates_for_employee_joining_during_generated_year(): void
    {
        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Beacon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            'id' => 18,
            'employee_code' => 'EMP-18',
            'company_id' => 1,
            'full_name' => 'Dewi Setiawati',
            'email' => 'dewi@example.test',
            'join_date' => '2026-04-14',
            'is_active' => true,
            'role' => 'employee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_types')->insert([
            [
                'id' => 20,
                'name' => 'Cuti Tahunan',
                'max_days' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 21,
                'name' => 'Cuti Izin',
                'max_days' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('leave_policies')->insert([
            [
                'id' => 30,
                'company_id' => 1,
                'leave_type_id' => 20,
                'days_per_year' => 12,
                'min_tenure_months' => 12,
                'max_carry_over' => 0,
                'is_prorated' => false,
                'eligibility_type' => 'all',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 31,
                'company_id' => 1,
                'leave_type_id' => 21,
                'days_per_year' => 6,
                'min_tenure_months' => 0,
                'max_carry_over' => 0,
                'is_prorated' => false,
                'eligibility_type' => 'all',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Artisan::call('leave:generate-annual', [
            'year' => 2026,
            '--company' => 1,
        ]);

        $this->assertDatabaseMissing('leave_balances', [
            'employee_id' => 18,
            'leave_type_id' => 20,
            'year' => 2026,
        ]);
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => 18,
            'leave_type_id' => 21,
            'year' => 2026,
            'total_days' => 6,
            'remaining_days' => 6,
        ]);
    }

    public function test_leave_policy_employee_picker_lists_all_active_company_employees_regardless_of_role(): void
    {
        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'PT Beacon', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'PT Other', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('employees')->insert([
            [
                'id' => 1,
                'employee_code' => 'ADM-01',
                'company_id' => 1,
                'full_name' => 'Admin Beacon',
                'email' => 'admin@example.test',
                'join_date' => '2024-01-01',
                'is_active' => true,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 18,
                'employee_code' => 'EMP-18',
                'company_id' => 1,
                'full_name' => 'Dewi Setiawati',
                'email' => 'dewi@example.test',
                'join_date' => '2026-04-14',
                'is_active' => true,
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 19,
                'employee_code' => 'EMP-19',
                'company_id' => 1,
                'full_name' => 'Inactive Employee',
                'email' => 'inactive@example.test',
                'join_date' => '2024-01-01',
                'is_active' => false,
                'role' => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 20,
                'employee_code' => 'EMP-20',
                'company_id' => 2,
                'full_name' => 'Other Company',
                'email' => 'other@example.test',
                'join_date' => '2024-01-01',
                'is_active' => true,
                'role' => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        session(['admin_id' => 1]);

        $view = app(LeavePolicyController::class)->index();
        $employeeNames = $view->getData()['employees']->pluck('full_name')->all();

        $this->assertContains('Admin Beacon', $employeeNames);
        $this->assertContains('Dewi Setiawati', $employeeNames);
        $this->assertNotContains('Inactive Employee', $employeeNames);
        $this->assertNotContains('Other Company', $employeeNames);
    }

    public function test_selected_leave_policy_accepts_active_manager_employee(): void
    {
        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Beacon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 1,
                'employee_code' => 'ADM-01',
                'company_id' => 1,
                'full_name' => 'Admin Beacon',
                'email' => 'admin@example.test',
                'join_date' => '2024-01-01',
                'is_active' => true,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 18,
                'employee_code' => 'EMP-18',
                'company_id' => 1,
                'full_name' => 'Dewi Setiawati',
                'email' => 'dewi@example.test',
                'join_date' => '2026-04-14',
                'is_active' => true,
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('leave_types')->insert([
            'id' => 21,
            'name' => 'Cuti Izin',
            'max_days' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['admin_id' => 1]);

        $request = Request::create('/admin/leave-policies', 'POST', [
            'leave_type_id' => 21,
            'days_per_year' => 6,
            'min_tenure_months' => 0,
            'max_carry_over' => 0,
            'eligibility_type' => 'selected',
            'employee_ids' => [18],
        ]);

        app(LeavePolicyController::class)->store($request);

        $this->assertDatabaseHas('leave_policies', [
            'company_id' => 1,
            'leave_type_id' => 21,
            'eligibility_type' => 'selected',
        ]);
        $this->assertDatabaseHas('leave_policy_employees', [
            'employee_id' => 18,
        ]);
    }
}
