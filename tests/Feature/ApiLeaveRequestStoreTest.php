<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LeaveController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiLeaveRequestStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->default(12);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('year');
            $table->decimal('remaining_days', 4, 1)->default(0);
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
            $table->unsignedBigInteger('delegate_to')->nullable();
            $table->string('status')->nullable();
            $table->integer('current_step')->nullable();
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

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });
    }

    public function test_api_leave_request_store_sets_initial_approval_state(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
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
            'remaining_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employee = Employee::findOrFail(1);
        $request = Request::create('/api/leave/requests', 'POST', [
            'leave_type_id' => 1,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
            'total_days' => 2,
            'reason' => 'Family event',
        ]);
        $request->setUserResolver(fn () => $employee);

        $response = (new LeaveController())->store($request);
        $payload = $response->getData(true);

        $this->assertSame('pending', $payload['data']['status']);
        $this->assertSame(1, $payload['data']['current_step']);
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => 1,
            'status' => 'pending',
            'current_step' => 1,
        ]);
    }
}
