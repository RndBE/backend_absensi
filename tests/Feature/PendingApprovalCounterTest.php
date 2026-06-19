<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\PendingApprovalCounter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PendingApprovalCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->integer('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        foreach (['leave_requests', 'overtime_requests', 'attendance_requests'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('status')->default('pending');
                $table->integer('current_step')->default(1);
                $table->timestamps();
            });
        }
    }

    public function test_counts_only_pending_requests_waiting_for_the_current_approver_step(): void
    {
        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'email' => 'approver@example.test', 'password' => 'secret', 'full_name' => 'Approver One', 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'company_id' => 1, 'email' => 'other-approver@example.test', 'password' => 'secret', 'full_name' => 'Approver Two', 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'company_id' => 1, 'email' => 'staff-a@example.test', 'password' => 'secret', 'full_name' => 'Staff A', 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'company_id' => 1, 'email' => 'staff-b@example.test', 'password' => 'secret', 'full_name' => 'Staff B', 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('employee_approvers')->insert([
            ['employee_id' => 3, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 3, 'request_type' => 'overtime', 'step_order' => 2, 'approver_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 3, 'request_type' => 'attendance', 'step_order' => 1, 'approver_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 4, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_requests')->insert([
            ['employee_id' => 3, 'status' => 'pending', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 4, 'status' => 'pending', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 3, 'status' => 'approved', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('overtime_requests')->insert([
            ['employee_id' => 3, 'status' => 'in_review', 'current_step' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 3, 'status' => 'pending', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('attendance_requests')->insert([
            ['employee_id' => 3, 'status' => 'pending', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $count = app(PendingApprovalCounter::class)->countForApprover(Employee::findOrFail(1));

        $this->assertSame(2, $count);
    }
}
