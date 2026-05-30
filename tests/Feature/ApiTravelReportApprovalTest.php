<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\TravelReportController;
use App\Models\Employee;
use App\Models\TravelReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiTravelReportApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('travel_report_documents');
        Schema::dropIfExists('travel_report_activities');
        Schema::dropIfExists('travel_reports');
        Schema::dropIfExists('budget_requests');
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('employee_approvers');
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
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('photo')->nullable();
            $table->string('position')->nullable();
            $table->string('fcm_token')->nullable();
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

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('budget_request_id')->nullable();
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->string('destination_city');
            $table->date('departure_date');
            $table->date('return_date');
            $table->text('purpose');
            $table->text('conclusion')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('travel_report_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('travel_report_id');
            $table->date('activity_date');
            $table->text('description');
            $table->json('results')->nullable();
            $table->text('issues')->nullable();
            $table->text('conclusion')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('travel_report_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('travel_report_id');
            $table->unsignedBigInteger('travel_report_activity_id')->nullable();
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->date('activity_date')->nullable();
            $table->integer('sort_order')->default(0);
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

        DB::table('employees')->insert([
            ['id' => 1, 'full_name' => 'Requester', 'email' => 'requester@example.test', 'password' => 'secret', 'role' => 'employee', 'position' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'full_name' => 'Approver', 'email' => 'approver@example.test', 'password' => 'secret', 'role' => 'manager', 'position' => 'Manager', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'full_name' => 'HSE Approver', 'email' => 'hse@example.test', 'password' => 'secret', 'role' => 'manager', 'position' => 'Hse Officer', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_store_notifies_first_travel_report_approver(): void
    {
        DB::table('employee_approvers')->insert([
            'employee_id' => 1,
            'request_type' => 'travel_report',
            'step_order' => 1,
            'approver_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requester = Employee::findOrFail(1);
        $request = Request::create('/api/travel-reports', 'POST', [
            'destination_city' => 'Surabaya',
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-01',
            'purpose' => 'Audit HSE',
            'conclusion' => 'Aman',
            'recommendations' => json_encode([]),
            'activities' => json_encode([
                ['date' => '2026-06-01', 'description' => 'Inspeksi area', 'results' => ['OK']],
            ]),
        ]);
        $request->setUserResolver(fn () => $requester);

        $response = app(TravelReportController::class)->store($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => 2,
            'title' => 'Pengajuan LHP Baru',
            'type' => 'approval',
            'reference_type' => TravelReport::class,
            'reference_id' => $payload['data']['id'],
        ]);
    }

    public function test_travel_report_approval_detail_loads_for_mobile(): void
    {
        DB::table('travel_reports')->insert([
            'id' => 5,
            'employee_id' => 1,
            'destination_city' => 'Surabaya',
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-01',
            'purpose' => 'Audit HSE',
            'conclusion' => 'Aman',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('travel_report_activities')->insert([
            'id' => 7,
            'travel_report_id' => 5,
            'activity_date' => '2026-06-01',
            'description' => 'Inspeksi area',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(ApprovalController::class)->show(
            Request::create('/api/approvals/travel_report/5', 'GET'),
            'travel_report',
            5
        );

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Surabaya', $payload['data']['destination_city']);
        $this->assertSame('Inspeksi area', $payload['data']['activities'][0]['description']);
    }

    public function test_travel_report_detail_allows_edit_only_on_active_hse_approver_step(): void
    {
        DB::table('employee_approvers')->insert([
            [
                'employee_id' => 1,
                'request_type' => 'travel_report',
                'step_order' => 1,
                'approver_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_id' => 1,
                'request_type' => 'travel_report',
                'step_order' => 2,
                'approver_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('travel_reports')->insert([
            [
                'id' => 10,
                'employee_id' => 1,
                'destination_city' => 'Surabaya',
                'departure_date' => '2026-06-01',
                'return_date' => '2026-06-01',
                'purpose' => 'Audit HSE',
                'conclusion' => 'Aman',
                'status' => 'pending',
                'current_step' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'employee_id' => 1,
                'destination_city' => 'Malang',
                'departure_date' => '2026-06-02',
                'return_date' => '2026-06-02',
                'purpose' => 'Audit HSE',
                'conclusion' => 'Aman',
                'status' => 'in_review',
                'current_step' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 13,
                'employee_id' => 1,
                'destination_city' => 'Semarang',
                'departure_date' => '2026-06-04',
                'return_date' => '2026-06-04',
                'purpose' => 'Audit HSE',
                'conclusion' => 'Aman',
                'status' => 'in_review',
                'current_step' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 12,
                'employee_id' => 1,
                'destination_city' => 'Jakarta',
                'departure_date' => '2026-06-03',
                'return_date' => '2026-06-03',
                'purpose' => 'Audit HSE',
                'conclusion' => 'Aman',
                'status' => 'approved',
                'current_step' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('approval_logs')->insert([
            'approvable_type' => TravelReport::class,
            'approvable_id' => 11,
            'approver_id' => 2,
            'action' => 'approved',
            'step_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pendingPayload = app(TravelReportController::class)
            ->show(Request::create('/api/travel-reports/10', 'GET'), 10)
            ->getData(true);
        $processedPayload = app(TravelReportController::class)
            ->show(Request::create('/api/travel-reports/11', 'GET'), 11)
            ->getData(true);
        $managerStepPayload = app(TravelReportController::class)
            ->show(Request::create('/api/travel-reports/13', 'GET'), 13)
            ->getData(true);
        $approvedPayload = app(TravelReportController::class)
            ->show(Request::create('/api/travel-reports/12', 'GET'), 12)
            ->getData(true);

        $this->assertFalse($pendingPayload['data']['can_edit']);
        $this->assertTrue($processedPayload['data']['can_edit']);
        $this->assertFalse($managerStepPayload['data']['can_edit']);
        $this->assertFalse($approvedPayload['data']['can_edit']);
    }

    public function test_travel_report_cannot_be_updated_after_final_approval(): void
    {
        DB::table('travel_reports')->insert([
            'id' => 12,
            'employee_id' => 1,
            'destination_city' => 'Surabaya',
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-01',
            'purpose' => 'Audit HSE',
            'conclusion' => 'Aman',
            'status' => 'approved',
            'current_step' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('approval_logs')->insert([
            'approvable_type' => TravelReport::class,
            'approvable_id' => 12,
            'approver_id' => 2,
            'action' => 'approved',
            'step_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requester = Employee::findOrFail(1);
        $request = Request::create('/api/travel-reports/12', 'PUT', [
            'destination_city' => 'Malang',
            'departure_date' => '2026-06-03',
            'return_date' => '2026-06-03',
            'purpose' => 'Audit lanjutan',
            'conclusion' => 'Aman',
            'activities' => json_encode([
                ['date' => '2026-06-03', 'description' => 'Inspeksi area', 'results' => ['OK']],
            ]),
        ]);
        $request->setUserResolver(fn () => $requester);

        $response = app(TravelReportController::class)->update($request, 12);
        $payload = $response->getData(true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Surabaya', DB::table('travel_reports')->where('id', 12)->value('destination_city'));
    }
}
