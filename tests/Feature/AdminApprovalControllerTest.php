<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ApprovalController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminApprovalControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('data_change_requests');
        Schema::dropIfExists('travel_reports');
        Schema::dropIfExists('budget_requests');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('employees');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->integer('job_level')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->nullable();
            $table->timestamps();
        });

        foreach (['overtime_requests', 'attendance_requests', 'budget_requests', 'travel_reports', 'data_change_requests'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('status')->default('pending');
                $table->integer('current_step')->nullable();

                if ($tableName === 'travel_reports') {
                    $table->string('trip_purpose')->nullable();
                    $table->date('departure_date')->nullable();
                    $table->date('return_date')->nullable();
                }

                $table->timestamps();
            });
        }

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->integer('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        DB::table('departments')->insert([
            'id' => 1,
            'name' => 'Software',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Super Admin', 'email' => 'super@example.test', 'password' => 'secret', 'role' => 'superadmin', 'position' => 'Director', 'job_level' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'secret', 'role' => 'manager', 'position' => 'Manager', 'job_level' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Team Lead', 'email' => 'lead@example.test', 'password' => 'secret', 'role' => 'employee', 'position' => 'Lead', 'job_level' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Requester', 'email' => 'requester@example.test', 'password' => 'secret', 'role' => 'employee', 'position' => 'Staff', 'job_level' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Tahunan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_superadmin_sees_pending_leave_even_when_not_current_approver(): void
    {
        DB::table('leave_requests')->insert([
            'id' => 1,
            'employee_id' => 4,
            'leave_type_id' => 1,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'total_days' => 1,
            'reason' => 'Family event',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_approvers')->insert([
            'employee_id' => 4,
            'request_type' => 'leave',
            'step_order' => 1,
            'approver_id' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['admin_id' => 1]);

        $view = app(ApprovalController::class)->index(Request::create('/admin/approvals', 'GET'));

        $this->assertSame([1], $view->getData()['leave']->pluck('id')->all());
    }

    public function test_manager_only_sees_pending_leave_when_they_are_current_approver(): void
    {
        DB::table('leave_requests')->insert([
            'id' => 1,
            'employee_id' => 4,
            'leave_type_id' => 1,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'total_days' => 1,
            'reason' => 'Family event',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_approvers')->insert([
            'employee_id' => 4,
            'request_type' => 'leave',
            'step_order' => 1,
            'approver_id' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['admin_id' => 2]);

        $view = app(ApprovalController::class)->index(Request::create('/admin/approvals', 'GET'));

        $this->assertTrue($view->getData()['leave']->isEmpty());
    }

    public function test_travel_report_uses_travel_report_approver_chain(): void
    {
        DB::table('travel_reports')->insert([
            'id' => 1,
            'employee_id' => 4,
            'trip_purpose' => 'Customer visit',
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-02',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_approvers')->insert([
            'employee_id' => 4,
            'request_type' => 'travel_report',
            'step_order' => 1,
            'approver_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['admin_id' => 2]);

        $view = app(ApprovalController::class)->index(Request::create('/admin/approvals?tab=travel_report', 'GET', ['tab' => 'travel_report']));

        $this->assertSame([1], $view->getData()['travelReport']->pluck('id')->all());
    }
}
