<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LeaveController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->date('birth_date')->nullable();
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

        $response = (new LeaveController)->store($request);
        $payload = $response->getData(true);

        $this->assertSame('pending', $payload['data']['status']);
        $this->assertSame(1, $payload['data']['current_step']);
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => 1,
            'status' => 'pending',
            'current_step' => 1,
        ]);
    }

    public function test_api_leave_types_only_returns_types_with_employee_balance(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypesWithAnnualBalanceOnly();

        $request = Request::create('/api/leave/types', 'GET');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new LeaveController)->types($request);
        $data = $response->getData(true)['data'];

        $this->assertCount(1, $data);
        $this->assertSame('Cuti Tahunan', $data[0]['name']);
    }

    public function test_api_leave_request_rejects_cuti_tahunan_without_balance(): void
    {
        // Aturan bisnis: hanya "Cuti Tahunan" yang butuh saldo. Tanpa saldo → ditolak 422.
        $this->seedEmployee();
        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Tahunan',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/leave/requests', 'POST', [
            'leave_type_id' => 1,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
            'total_days' => 2,
            'reason' => 'Cuti tahunan tanpa saldo',
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new LeaveController)->store($request);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Saldo cuti belum tersedia.', $payload['message']);
        $this->assertDatabaseMissing('leave_requests', [
            'employee_id' => 1,
            'leave_type_id' => 1,
        ]);
    }

    public function test_api_leave_request_allows_non_quota_type_without_balance(): void
    {
        // Aturan bisnis: izin/cuti selain "Cuti Tahunan" bebas saldo → boleh dibuat.
        $this->seedEmployee();
        $this->seedLeaveTypesWithAnnualBalanceOnly();

        $request = Request::create('/api/leave/requests', 'POST', [
            'leave_type_id' => 2, // Cuti Melahirkan (tanpa saldo)
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
            'total_days' => 2,
            'reason' => 'Cuti melahirkan',
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new LeaveController)->store($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => 1,
            'leave_type_id' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_company_timeline_mixes_leave_and_birthday_items(): void
    {
        Carbon::setTestNow('2026-05-30 10:00:00');

        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Arta Teknologi Comunindo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('departments')->insert([
            'id' => 1,
            'name' => 'HSE Officer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'department_id' => 1,
                'full_name' => 'Employee One',
                'email' => 'employee@example.test',
                'password' => 'password',
                'birth_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'department_id' => 1,
                'full_name' => 'Shandy Bagus',
                'email' => 'shandy@example.test',
                'password' => 'password',
                'birth_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'company_id' => 1,
                'department_id' => 1,
                'full_name' => 'Widya',
                'email' => 'widya@example.test',
                'password' => 'password',
                'birth_date' => '1995-05-30',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Sakit',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'employee_id' => 2,
            'leave_type_id' => 1,
            'start_date' => '2026-05-30',
            'end_date' => '2026-05-30',
            'total_days' => 1,
            'reason' => 'Sakit',
            'status' => 'approved',
            'current_step' => 1,
            'created_at' => '2026-05-30 08:00:00',
            'updated_at' => '2026-05-30 08:00:00',
        ]);

        $employee = Employee::findOrFail(1);
        $request = Request::create('/api/leave/company-timeline', 'GET');
        $request->setUserResolver(fn () => $employee);

        $response = (new LeaveController)->companyTimeline($request);
        $payload = $response->getData(true);
        $timeline = collect($payload['data']['timeline']);

        $this->assertContains('leave', $timeline->pluck('type')->all());
        $this->assertContains('birthday', $timeline->pluck('type')->all());
        $this->assertSame('WIDYA', $timeline->firstWhere('type', 'birthday')['employee']['name']);
        $this->assertSame('Cuti Sakit', $timeline->firstWhere('type', 'leave')['employees'][0]['leave_type']);
    }

    private function seedEmployee(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedLeaveTypesWithAnnualBalanceOnly(): void
    {
        DB::table('leave_types')->insert([
            [
                'id' => 1,
                'name' => 'Cuti Tahunan',
                'max_days' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Cuti Melahirkan',
                'max_days' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('leave_balances')->insert([
            'employee_id' => 1,
            'leave_type_id' => 1,
            'year' => now()->year,
            'remaining_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
