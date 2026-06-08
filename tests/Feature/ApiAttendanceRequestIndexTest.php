<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceRequestController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiAttendanceRequestIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });
    }

    public function test_attendance_request_period_filters_by_requested_attendance_date(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'employee_code' => 'EMP001',
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => 'password',
            'role' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_requests')->insert([
            [
                'id' => 10,
                'employee_id' => 1,
                'date' => '2026-05-31',
                'clock_in' => '08:00:00',
                'clock_out' => null,
                'reason' => 'Lupa clock in',
                'status' => 'pending',
                'current_step' => 1,
                'created_at' => '2026-06-01 09:00:00',
                'updated_at' => '2026-06-01 09:00:00',
            ],
            [
                'id' => 11,
                'employee_id' => 1,
                'date' => '2026-06-01',
                'clock_in' => '08:00:00',
                'clock_out' => null,
                'reason' => 'Hari ini',
                'status' => 'pending',
                'current_step' => 1,
                'created_at' => '2026-06-01 10:00:00',
                'updated_at' => '2026-06-01 10:00:00',
            ],
        ]);

        $request = Request::create('/api/attendance-requests?period=2026-05', 'GET', [
            'period' => '2026-05',
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceRequestController())->index($request);
        $data = $response->getData(true)['data'];

        $this->assertCount(1, $data);
        $this->assertSame(10, $data[0]['id']);
        $this->assertSame('2026-05-31', $data[0]['date']);
    }
}
