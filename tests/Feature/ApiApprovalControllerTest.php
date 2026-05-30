<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ApprovalController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiApprovalControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('travel_reports');
        Schema::dropIfExists('budget_requests');
        Schema::dropIfExists('data_change_requests');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_balances');
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
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('photo')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->default(12);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->integer('current_step')->nullable();
            $table->timestamps();
        });

        foreach (['overtime_requests', 'attendance_requests', 'data_change_requests', 'budget_requests', 'travel_reports'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('status')->default('pending');
                $table->integer('current_step')->nullable();

                if ($tableName === 'budget_requests') {
                    $table->string('title')->nullable();
                    $table->decimal('total_amount', 12, 2)->default(0);
                }

                if ($tableName === 'travel_reports') {
                    $table->string('destination_city')->nullable();
                    $table->date('departure_date')->nullable();
                    $table->date('return_date')->nullable();
                    $table->text('purpose')->nullable();
                    $table->text('conclusion')->nullable();
                }

                $table->timestamps();
            });
        }

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('year');
            $table->integer('total_days')->default(12);
            $table->integer('used_days')->default(0);
            $table->integer('remaining_days')->default(12);
            $table->timestamps();
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
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

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('action');
            $table->integer('step_order')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function test_leave_approval_detail_includes_leave_type_for_mobile_display(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'full_name' => 'Requester',
            'email' => 'requester@example.test',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_types')->insert([
            'id' => 2,
            'name' => 'Cuti Sakit',
            'max_days' => 14,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'id' => 9,
            'employee_id' => 1,
            'leave_type_id' => 2,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'total_days' => 1,
            'reason' => 'Sakit',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(ApprovalController::class)->show(
            Request::create('/api/approvals/leave/9', 'GET'),
            'leave',
            9
        );

        $payload = $response->getData(true);

        $this->assertSame('Cuti Sakit', $payload['data']['leave_type']['name']);
        $this->assertSame('2026-06-01', $payload['data']['start_date']);
        $this->assertSame('2026-06-01', $payload['data']['end_date']);
    }

    public function test_superadmin_mobile_approval_inbox_only_shows_items_where_they_are_current_approver(): void
    {
        DB::table('employees')->insert([
            ['id' => 1, 'full_name' => 'Requester One', 'email' => 'requester1@example.test', 'password' => 'secret', 'role' => 'employee', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'full_name' => 'Requester Two', 'email' => 'requester2@example.test', 'password' => 'secret', 'role' => 'employee', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'full_name' => 'Super Admin', 'email' => 'super@example.test', 'password' => 'secret', 'role' => 'superadmin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'full_name' => 'Other Approver', 'email' => 'other@example.test', 'password' => 'secret', 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Tahunan',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            ['id' => 11, 'employee_id' => 1, 'leave_type_id' => 1, 'start_date' => '2026-06-01', 'end_date' => '2026-06-01', 'total_days' => 1, 'reason' => 'Family event', 'status' => 'pending', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'employee_id' => 2, 'leave_type_id' => 1, 'start_date' => '2026-06-02', 'end_date' => '2026-06-02', 'total_days' => 1, 'reason' => 'Family event', 'status' => 'pending', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('employee_approvers')->insert([
            ['employee_id' => 1, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 2, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $request = Request::create('/api/approvals', 'GET');
        $request->setUserResolver(fn () => Employee::findOrFail(3));

        $response = app(ApprovalController::class)->index($request);
        $payload = $response->getData(true);

        $this->assertSame([11], collect($payload['data']['leave'])->pluck('id')->all());
    }

    public function test_final_leave_approval_uses_employee_approvers_not_approver_id_chain(): void
    {
        DB::table('employees')->insert([
            ['id' => 1, 'approver_id' => 2, 'full_name' => 'Requester', 'email' => 'requester@example.test', 'password' => 'secret', 'role' => 'employee', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'approver_id' => 3, 'full_name' => 'Step One', 'email' => 'step1@example.test', 'password' => 'secret', 'role' => 'employee', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'approver_id' => 4, 'full_name' => 'Step Two', 'email' => 'step2@example.test', 'password' => 'secret', 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'approver_id' => null, 'full_name' => 'Not In Chain', 'email' => 'not-in-chain@example.test', 'password' => 'secret', 'role' => 'superadmin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Tahunan',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_balances')->insert([
            'employee_id' => 1,
            'leave_type_id' => 1,
            'year' => now()->year,
            'total_days' => 12,
            'used_days' => 0,
            'remaining_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'id' => 10,
            'employee_id' => 1,
            'leave_type_id' => 1,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'total_days' => 1,
            'reason' => 'Family event',
            'status' => 'in_review',
            'current_step' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employee_approvers')->insert([
            ['employee_id' => 1, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 1, 'request_type' => 'leave', 'step_order' => 2, 'approver_id' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $approver = Employee::findOrFail(3);
        $request = Request::create('/api/approvals/leave/10/approve', 'POST');
        $request->setUserResolver(fn () => $approver);

        app(ApprovalController::class)->approve($request, 'leave', 10);

        $this->assertDatabaseHas('leave_requests', [
            'id' => 10,
            'status' => 'approved',
            'current_step' => 2,
        ]);
        $this->assertDatabaseHas('approval_logs', [
            'approvable_id' => 10,
            'approver_id' => 3,
            'action' => 'approved',
            'step_order' => 2,
        ]);
    }
}
