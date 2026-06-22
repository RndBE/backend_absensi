<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\TravelReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeBudgetTravelPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Carbon::setTestNow('2026-06-15 08:15:00');

        foreach ([
            'travel_report_documents',
            'travel_report_activities',
            'travel_reports',
            'budget_request_participants',
            'budget_request_items',
            'budget_requests',
            'request_attachments',
            'approval_logs',
            'employee_approvers',
            'notifications',
            'travel_zones',
            'city_distances',
            'attendances',
            'attendance_requests',
            'overtime_requests',
            'leave_requests',
            'settings',
            'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->boolean('is_late')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->string('review_status')->nullable();
            $table->text('suspicious_reason')->nullable();
            $table->text('remote_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('overtime_type')->default('workday');
            $table->integer('total_duration')->default(0);
            $table->integer('break_duration')->default(0);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('travel_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone');
            $table->string('name');
            $table->unsignedInteger('min_km');
            $table->unsignedInteger('max_km')->nullable();
            $table->decimal('meal_allowance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('city_distances', function (Blueprint $table) {
            $table->id();
            $table->string('city_key')->unique();
            $table->string('city_label');
            $table->unsignedInteger('distance_km');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('source')->default('routing');
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->unsignedTinyInteger('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('action');
            $table->unsignedTinyInteger('step_order');
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

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedBigInteger('travel_zone_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->string('type');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('budget_request_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('budget_request_id')->nullable();
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->string('destination_city');
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedBigInteger('travel_zone_id')->nullable();
            $table->date('departure_date');
            $table->date('return_date');
            $table->text('purpose');
            $table->text('conclusion')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('current_step')->default(1);
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
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('travel_report_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('travel_report_id');
            $table->unsignedBigInteger('travel_report_activity_id')->nullable();
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->date('activity_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach ([
            'office_latitude' => '1.0456',
            'office_longitude' => '104.0305',
            'office_radius_meters' => '100',
            'require_photo' => '0',
            'require_gps' => '0',
            'allow_remote_clockin' => '0',
            'face_verification_enabled' => '0',
        ] as $key => $value) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_dashboard_links_to_budget_and_lhp_portal_pages(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Anggaran')
            ->assertSee('/employee/budget-requests', false)
            ->assertSee('LHP')
            ->assertSee('/employee/travel-reports', false);
    }

    public function test_employee_can_create_budget_request_from_web_portal(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'approver@example.test', 'full_name' => 'Budget Approver']);
        $this->seedApprover(1, 'budget', 2);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/budget-requests/create')
            ->assertOk()
            ->assertSee('Pengajuan Anggaran')
            ->assertSee('Kota Tujuan', false)
            ->assertSee('name="destination_city"', false)
            ->assertSee('name="distance_km"', false)
            ->assertSee('type="hidden"', false)
            ->assertSee('/employee/travel/estimate-zone', false)
            ->assertDontSee('Jarak KM')
            ->assertDontSee('select name="participants[]" multiple', false)
            ->assertSee('name="attachments[]"', false)
            ->assertSee('name="item_attachments_0[]"', false);

        $this->withSession(['employee_id' => 1])
            ->post('/employee/budget-requests', [
                'type' => 'budget',
                'title' => 'Perjalanan Batam',
                'description' => 'Kunjungan klien',
                'surat_tugas_no' => 'ST-001',
                'surat_tugas_date' => '2026-06-16',
            'distance_km' => 12,
            'items' => [
                ['type' => 'transport', 'description' => 'Taksi', 'amount' => 150000],
                ['type' => 'meal', 'description' => 'Makan 1 hari', 'amount' => 75000],
            ],
                'participants' => [2],
            ])
            ->assertRedirect(route('employee.budget-requests.index'));

        $this->assertDatabaseHas('budget_requests', [
            'employee_id' => 1,
            'title' => 'Perjalanan Batam',
            'total_amount' => 225000,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('budget_request_items', [
            'type' => 'meal',
            'amount' => 75000,
        ]);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => 2,
            'title' => 'Pengajuan Anggaran Baru',
            'reference_type' => BudgetRequest::class,
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/budget-requests')
            ->assertOk()
            ->assertSee('employee-budget-filter-form', false)
            ->assertSee('items-start', false)
            ->assertSee('self-start', false)
            ->assertSee('Perjalanan Batam')
            ->assertSee('Rp 225.000');
    }

    public function test_employee_budget_city_estimate_route_returns_zone_data(): void
    {
        $this->seedEmployee();
        DB::table('travel_zones')->insert([
            'id' => 1,
            'zone' => '1',
            'name' => 'Zona 1',
            'min_km' => 0,
            'max_km' => 1500,
            'meal_allowance' => 75000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => 1.0456, 'lon' => 104.0305],
            ]),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/travel/estimate-zone?city=Batam')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.city', 'Batam')
            ->assertJsonPath('data.distance_km', 0)
            ->assertJsonPath('data.zone.name', 'Zona 1')
            ->assertJsonPath('data.zone.meal_allowance', 75000);
    }

    public function test_employee_can_create_and_edit_lhp_from_web_portal(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'lhp-approver@example.test', 'full_name' => 'LHP Approver']);
        $this->seedApprover(1, 'travel_report', 2);
        $budgetId = $this->seedApprovedBudgetRequest();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/travel-reports/create')
            ->assertOk()
            ->assertSee('Buat LHP')
            ->assertSee('Perjalanan Batam')
            ->assertSee('name="activity_documents_0[]"', false);

        $this->withSession(['employee_id' => 1])
            ->post('/employee/travel-reports', [
                'budget_request_id' => $budgetId,
                'destination_city' => 'Batam',
                'departure_date' => '2026-06-20',
                'return_date' => '2026-06-21',
                'surat_tugas_no' => 'ST-001',
                'surat_tugas_date' => '2026-06-16',
                'distance_km' => 12,
                'purpose' => 'Kunjungan klien',
                'conclusion' => 'Kunjungan selesai',
                'recommendations' => ['Follow up kontrak'],
                'activities' => [
                    [
                        'date' => '2026-06-20',
                        'description' => 'Meeting awal',
                        'results' => ['Klien setuju jadwal'],
                        'issues' => 'Tidak ada',
                        'conclusion' => 'Berjalan lancar',
                    ],
                ],
            ])
            ->assertRedirect(route('employee.travel-reports.index'));

        $this->assertDatabaseHas('travel_reports', [
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'destination_city' => 'Batam',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('travel_report_activities', [
            'description' => 'Meeting awal',
        ]);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => 2,
            'title' => 'Pengajuan LHP Baru',
            'reference_type' => TravelReport::class,
        ]);

        $reportId = DB::table('travel_reports')->value('id');

        $this->withSession(['employee_id' => 1])
            ->get("/employee/travel-reports/{$reportId}/edit")
            ->assertOk()
            ->assertSee('Edit LHP')
            ->assertSee('Meeting awal');

        $this->withSession(['employee_id' => 1])
            ->put("/employee/travel-reports/{$reportId}", [
                'budget_request_id' => $budgetId,
                'destination_city' => 'Tanjung Pinang',
                'departure_date' => '2026-06-20',
                'return_date' => '2026-06-22',
                'purpose' => 'Kunjungan lanjutan',
                'conclusion' => 'Perlu follow up',
                'recommendations' => ['Kirim proposal'],
                'activities' => [
                    [
                        'date' => '2026-06-21',
                        'description' => 'Presentasi proposal',
                        'results' => ['Proposal diterima'],
                        'issues' => '',
                        'conclusion' => 'Menunggu keputusan',
                    ],
                ],
            ])
            ->assertRedirect(route('employee.travel-reports.show', $reportId));

        $this->assertDatabaseHas('travel_reports', [
            'id' => $reportId,
            'destination_city' => 'Tanjung Pinang',
        ]);
        $this->assertDatabaseMissing('travel_report_activities', [
            'description' => 'Meeting awal',
        ]);
        $this->assertDatabaseHas('travel_report_activities', [
            'description' => 'Presentasi proposal',
        ]);
    }

    public function test_employee_approver_can_approve_budget_and_lhp_from_web_portal(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'approver@example.test', 'full_name' => 'Approver One']);
        $this->seedApprover(1, 'budget', 2);
        $this->seedApprover(1, 'travel_report', 2);
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('budget_requests')->where('id', $budgetId)->update([
            'status' => 'pending',
        ]);

        $reportId = DB::table('travel_reports')->insertGetId([
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'destination_city' => 'Batam',
            'departure_date' => '2026-06-20',
            'return_date' => '2026-06-21',
            'purpose' => 'Kunjungan klien',
            'conclusion' => 'Selesai',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/approvals')
            ->assertOk()
            ->assertSee('Pengajuan Anggaran')
            ->assertSee('Perjalanan Batam')
            ->assertSee('Pengajuan LHP')
            ->assertSee('Batam');

        $this->withSession(['employee_id' => 2])
            ->post("/employee/approvals/budget/{$budgetId}/approve")
            ->assertRedirect(route('employee.approvals.index'));

        $this->assertDatabaseHas('budget_requests', [
            'id' => $budgetId,
            'status' => 'approved',
        ]);

        $this->withSession(['employee_id' => 2])
            ->post("/employee/approvals/travel_report/{$reportId}/approve")
            ->assertRedirect(route('employee.approvals.index'));

        $this->assertDatabaseHas('travel_reports', [
            'id' => $reportId,
            'status' => 'approved',
        ]);
    }

    private function seedEmployee(array $attributes = []): void
    {
        DB::table('employees')->insert(array_merge([
            'id' => 1,
            'company_id' => 1,
            'employee_code' => 'EMP001',
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'position' => 'Staff',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function seedApprover(int $employeeId, string $type, int $approverId): void
    {
        DB::table('employee_approvers')->insert([
            'employee_id' => $employeeId,
            'request_type' => $type,
            'step_order' => 1,
            'approver_id' => $approverId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedApprovedBudgetRequest(): int
    {
        return DB::table('budget_requests')->insertGetId([
            'employee_id' => 1,
            'type' => 'budget',
            'title' => 'Perjalanan Batam',
            'description' => 'Kunjungan awal',
            'status' => 'approved',
            'current_step' => 1,
            'total_amount' => 225000,
            'surat_tugas_no' => 'ST-001',
            'surat_tugas_date' => '2026-06-16',
            'distance_km' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
