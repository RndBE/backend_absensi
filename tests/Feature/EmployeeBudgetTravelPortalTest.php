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
            'lpj_items',
            'lpjs',
            'travel_reports',
            'budget_payments',
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
            'departments',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

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
            $table->integer('job_level')->nullable();
            $table->string('photo')->nullable();
            $table->string('signature')->nullable();
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

        Schema::create('budget_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('payment_method')->default('transfer');
            $table->string('reference_no')->nullable();
            $table->string('payment_proof')->nullable();
            $table->string('status')->default('paid');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
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

        Schema::create('lpjs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('travel_report_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->string('nomor_lpj')->nullable();
            $table->decimal('total_anggaran', 15, 2)->default(0);
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->decimal('sisa', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('lpj_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lpj_id');
            $table->unsignedBigInteger('budget_request_item_id')->nullable();
            $table->string('uraian');
            $table->string('kategori')->nullable();
            $table->string('satuan')->nullable();
            $table->decimal('volume', 10, 2)->default(1);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('anggaran', 15, 2)->default(0);
            $table->decimal('realisasi', 15, 2)->default(0);
            $table->string('bukti_file')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
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

        DB::table('departments')->insert([
            'id' => 1,
            'name' => 'Operasional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            ->assertSee('employee-mobile-page-header', false)
            ->assertSee('employee-mobile-action', false)
            ->assertSee('employee-period-filter-card', false)
            ->assertSee('employee-period-input', false)
            ->assertSee('employee-filter-submit', false)
            ->assertSee('name="period_month"', false)
            ->assertSee('name="period_year"', false)
            ->assertSee('employee-period-select', false)
            ->assertDontSee('type="month"', false)
            ->assertSee('Perjalanan Batam')
            ->assertSee('Rp 225.000');
    }

    public function test_employee_can_submit_budget_request_item_with_zero_amount(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/budget-requests', [
                'type' => 'budget',
                'title' => 'Perjalanan Tanpa Biaya Makan',
                'description' => 'Kunjungan klien',
                'distance_km' => 12,
                'items' => [
                    ['type' => 'transport', 'description' => 'Taksi', 'amount' => 50000],
                    ['type' => 'meal', 'description' => 'Uang makan ditanggung klien', 'amount' => 0],
                ],
            ])
            ->assertRedirect(route('employee.budget-requests.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('budget_requests', [
            'employee_id' => 1,
            'title' => 'Perjalanan Tanpa Biaya Makan',
            'total_amount' => 50000,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('budget_request_items', [
            'type' => 'meal',
            'description' => 'Uang makan ditanggung klien',
            'amount' => 0,
        ]);
    }

    public function test_employee_can_edit_own_pending_budget_request(): void
    {
        $this->seedEmployee();
        $budgetId = $this->seedApprovedBudgetRequest();
        DB::table('budget_requests')->where('id', $budgetId)->update([
            'status' => 'pending',
        ]);
        DB::table('budget_request_items')->insert([
            'budget_request_id' => $budgetId,
            'type' => 'transport',
            'description' => 'Taksi lama',
            'amount' => 225000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/budget-requests')
            ->assertOk()
            ->assertSee("/employee/budget-requests/{$budgetId}/edit", false)
            ->assertSee('Edit');

        $this->withSession(['employee_id' => 1])
            ->get("/employee/budget-requests/{$budgetId}")
            ->assertOk()
            ->assertDontSee("/employee/budget-requests/{$budgetId}/edit", false);

        $this->withSession(['employee_id' => 1])
            ->get("/employee/budget-requests/{$budgetId}/edit")
            ->assertOk()
            ->assertSee('Edit Pengajuan Anggaran')
            ->assertSee('Perjalanan Batam')
            ->assertSee('Taksi lama')
            ->assertSee("action=\"http://192.168.12.104:8000/employee/budget-requests/{$budgetId}\"", false)
            ->assertSee('name="_method" value="PUT"', false);

        $this->withSession(['employee_id' => 1])
            ->put("/employee/budget-requests/{$budgetId}", [
                'type' => 'budget',
                'title' => 'Perjalanan Bandung',
                'description' => 'Kunjungan project update',
                'surat_tugas_no' => 'ST-EDIT',
                'surat_tugas_date' => '2026-06-18',
                'distance_km' => 20,
                'items' => [
                    ['type' => 'transport', 'description' => 'Kereta', 'amount' => 100000],
                    ['type' => 'meal', 'description' => 'Makan', 'amount' => 50000],
                ],
            ])
            ->assertRedirect(route('employee.budget-requests.show', $budgetId));

        $this->assertDatabaseHas('budget_requests', [
            'id' => $budgetId,
            'title' => 'Perjalanan Bandung',
            'description' => 'Kunjungan project update',
            'surat_tugas_no' => 'ST-EDIT',
            'total_amount' => 150000,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('budget_request_items', [
            'budget_request_id' => $budgetId,
            'description' => 'Taksi lama',
        ]);
        $this->assertDatabaseHas('budget_request_items', [
            'budget_request_id' => $budgetId,
            'type' => 'meal',
            'description' => 'Makan',
            'amount' => 50000,
        ]);
    }

    public function test_employee_cannot_edit_budget_request_after_pending_status(): void
    {
        $this->seedEmployee();
        $budgetId = $this->seedApprovedBudgetRequest();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/budget-requests')
            ->assertOk()
            ->assertDontSee("/employee/budget-requests/{$budgetId}/edit", false);

        $this->withSession(['employee_id' => 1])
            ->get("/employee/budget-requests/{$budgetId}")
            ->assertOk()
            ->assertDontSee("/employee/budget-requests/{$budgetId}/edit", false);

        $this->withSession(['employee_id' => 1])
            ->get("/employee/budget-requests/{$budgetId}/edit")
            ->assertRedirect(route('employee.budget-requests.show', $budgetId));

        $this->withSession(['employee_id' => 1])
            ->put("/employee/budget-requests/{$budgetId}", [
                'type' => 'budget',
                'title' => 'Tidak boleh berubah',
                'items' => [
                    ['type' => 'transport', 'description' => 'Kereta', 'amount' => 100000],
                ],
            ])
            ->assertRedirect(route('employee.budget-requests.show', $budgetId));

        $this->assertDatabaseHas('budget_requests', [
            'id' => $budgetId,
            'title' => 'Perjalanan Batam',
            'total_amount' => 225000,
            'status' => 'approved',
        ]);
    }

    public function test_employee_budget_and_lhp_forms_use_mobile_native_field_styles(): void
    {
        $views = [
            resource_path('views/employee/layouts/app.blade.php'),
            resource_path('views/employee/budget-requests/create.blade.php'),
            resource_path('views/employee/budget-requests/partials/item-row.blade.php'),
            resource_path('views/employee/travel-reports/partials/form.blade.php'),
            resource_path('views/employee/travel-reports/partials/activity-row.blade.php'),
        ];

        foreach ($views as $viewPath) {
            $view = file_get_contents($viewPath);

            $this->assertStringContainsString('employee-native-field', $view);
        }

        $layout = file_get_contents(resource_path('views/employee/layouts/app.blade.php'));

        $this->assertStringContainsString('-webkit-appearance: none', $layout);
        $this->assertStringContainsString('background-color: #fff', $layout);
        $this->assertStringContainsString('color: #111827', $layout);
        $this->assertStringContainsString('::-webkit-date-and-time-value', $layout);
        $this->assertStringContainsString('employee-date-shell', $layout);
        $this->assertStringContainsString('data-employee-date-shell', file_get_contents(resource_path('views/employee/budget-requests/create.blade.php')));
        $this->assertStringContainsString('data-employee-date-shell', file_get_contents(resource_path('views/employee/travel-reports/partials/form.blade.php')));
        $this->assertStringContainsString('data-employee-date-shell', file_get_contents(resource_path('views/employee/travel-reports/partials/activity-row.blade.php')));
        $this->assertStringContainsString('data-date-placeholder', file_get_contents(resource_path('views/employee/budget-requests/create.blade.php')));
        $this->assertStringContainsString('data-date-placeholder', file_get_contents(resource_path('views/employee/travel-reports/partials/form.blade.php')));
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

        $this->withSession(['employee_id' => 1])
            ->get('/employee/travel-reports')
            ->assertOk()
            ->assertSee('employee-mobile-page-header', false)
            ->assertSee('employee-mobile-action', false)
            ->assertSee('Buat LHP');

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

    public function test_employee_lpj_form_separates_income_and_realization_expense(): void
    {
        $this->seedEmployee();
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('budget_request_items')->insert([
            'id' => 100,
            'budget_request_id' => $budgetId,
            'type' => 'meal',
            'description' => 'Uang makan',
            'amount' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get("/employee/lpj/create?budget_request_id={$budgetId}")
            ->assertOk()
            ->assertSee('Pemasukan (Anggaran Disetujui)')
            ->assertSee('Pengeluaran (Rincian Realisasi)')
            ->assertSee('TOTAL PEMASUKAN')
            ->assertSee('name="items[${i}][kategori]"', false)
            ->assertSee('name="items[${i}][realisasi]"', false)
            ->assertDontSee('name="items[${i}][anggaran]"', false);
    }

    public function test_employee_lpj_store_keeps_income_from_budget_and_expense_from_realization(): void
    {
        $this->seedEmployee();
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('budget_request_items')->insert([
            'id' => 100,
            'budget_request_id' => $budgetId,
            'type' => 'meal',
            'description' => 'Uang makan',
            'amount' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->post('/employee/lpj', [
                'budget_request_id' => $budgetId,
                'nomor_lpj' => 'LPJ-001',
                'items' => [
                    [
                        'budget_request_item_id' => 100,
                        'kategori' => 'meal',
                        'uraian' => 'Uang makan',
                        'satuan' => 'Makan',
                        'volume' => 1,
                        'anggaran' => 999999,
                        'realisasi' => 80000,
                        'keterangan' => 'Reimbursement',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lpjs', [
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'nomor_lpj' => 'LPJ-001',
            'total_anggaran' => 225000,
            'total_realisasi' => 80000,
        ]);
        $this->assertDatabaseHas('lpj_items', [
            'uraian' => 'Uang makan',
            'kategori' => 'meal',
            'anggaran' => 0,
            'realisasi' => 80000,
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

    public function test_admin_can_open_print_page_from_budget_request_detail(): void
    {
        $this->seedEmployee(['department_id' => 1]);
        $this->seedEmployee([
            'id' => 2,
            'employee_code' => 'ADM001',
            'email' => 'admin@example.test',
            'full_name' => 'Admin Finance',
            'role' => 'superadmin',
            'department_id' => 1,
        ]);
        $budgetId = $this->seedApprovedBudgetRequest();
        DB::table('budget_request_items')->insert([
            'budget_request_id' => $budgetId,
            'type' => 'transport',
            'description' => 'Taksi Bandara',
            'amount' => 225000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['admin_id' => 2])
            ->get("/admin/budget-requests/{$budgetId}")
            ->assertOk()
            ->assertSee("/admin/budget-requests/{$budgetId}/print", false)
            ->assertSee('Cetak');

        $this->withSession(['admin_id' => 2])
            ->get("/admin/budget-requests/{$budgetId}/print")
            ->assertOk()
            ->assertSee('FORM PENGAJUAN ANGGARAN PT. ARTA TEKNOLOGI COMUNINDO')
            ->assertSee('@page { size: A4 portrait;', false)
            ->assertSee('width: 277mm;', false)
            ->assertSee('transform: scale(0.68);', false)
            ->assertSee('Divisi')
            ->assertSee('Project')
            ->assertSee('Rincian')
            ->assertSee('Anggaran')
            ->assertSee('Total Anggaran')
            ->assertSee('PJ/Leader')
            ->assertSee('Manager Admin')
            ->assertSee('Tanda* (Wajib diisi)')
            ->assertSee('Perjalanan Batam')
            ->assertSeeInOrder([
                '<td class="col-rincian">Transportasi</td>',
                '<td class="col-anggaran">225.000</td>',
                '<td class="col-keterangan">Taksi Bandara</td>',
            ], false)
            ->assertSee('Rp 225.000');
    }

    public function test_employee_current_budget_approver_can_print_from_approval_chain(): void
    {
        $this->seedEmployee(['department_id' => 1]);
        $this->seedEmployee([
            'id' => 2,
            'employee_code' => 'EMP002',
            'email' => 'approver@example.test',
            'full_name' => 'Approver One',
            'department_id' => 1,
        ]);
        $this->seedApprover(1, 'budget', 2);
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('budget_requests')->where('id', $budgetId)->update([
            'status' => 'pending',
        ]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/approvals')
            ->assertOk()
            ->assertSee("/employee/approvals/budget/{$budgetId}/print", false)
            ->assertSee('approval-print-link', false)
            ->assertSee('bg-teal-600', false)
            ->assertSee('hover:bg-teal-700', false)
            ->assertSee('approval-action-bar grid grid-cols-1 sm:grid-cols-2', false)
            ->assertDontSee('approval-action-bar grid grid-cols-1 sm:grid-cols-3', false)
            ->assertSee('Cetak');

        $this->withSession(['employee_id' => 2])
            ->get("/employee/approvals/budget/{$budgetId}/print")
            ->assertOk()
            ->assertSee('FORM PENGAJUAN ANGGARAN PT. ARTA TEKNOLOGI COMUNINDO')
            ->assertSee('PJ/Leader')
            ->assertSee('Manager Admin')
            ->assertSee('Perjalanan Batam')
            ->assertSee('Approver One');
    }

    public function test_employee_cannot_print_budget_approval_when_not_current_approver(): void
    {
        $this->seedEmployee(['department_id' => 1]);
        $this->seedEmployee([
            'id' => 2,
            'employee_code' => 'EMP002',
            'email' => 'approver@example.test',
            'full_name' => 'Approver One',
            'department_id' => 1,
        ]);
        $this->seedEmployee([
            'id' => 3,
            'employee_code' => 'EMP003',
            'email' => 'other@example.test',
            'full_name' => 'Other Employee',
            'department_id' => 1,
        ]);
        $this->seedApprover(1, 'budget', 2);
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('budget_requests')->where('id', $budgetId)->update([
            'status' => 'pending',
        ]);

        $this->withSession(['employee_id' => 3])
            ->get("/employee/approvals/budget/{$budgetId}/print")
            ->assertRedirect(route('employee.approvals.index'));
    }

    public function test_employee_approver_can_see_and_approve_lpj_requests(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'approver@example.test', 'full_name' => 'LPJ Approver']);
        $this->seedApprover(1, 'lpj', 2);
        $budgetId = $this->seedApprovedBudgetRequest();

        $reportId = DB::table('travel_reports')->insertGetId([
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'destination_city' => 'Batam',
            'departure_date' => '2026-06-20',
            'return_date' => '2026-06-21',
            'purpose' => 'Kunjungan klien',
            'conclusion' => 'Selesai',
            'status' => 'approved',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lpjId = DB::table('lpjs')->insertGetId([
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'travel_report_id' => $reportId,
            'nomor_lpj' => 'LPJ-001',
            'total_anggaran' => 225000,
            'total_realisasi' => 250000,
            'sisa' => -25000,
            'status' => 'pending',
            'current_step' => 1,
            'catatan' => 'Realisasi perjalanan Batam',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/approvals')
            ->assertOk()
            ->assertSee('Pengajuan LPJ')
            ->assertSee('LPJ-001')
            ->assertSee('Perjalanan Batam')
            ->assertSee('Rp 250.000');

        $this->withSession(['employee_id' => 2])
            ->post("/employee/approvals/lpj/{$lpjId}/approve")
            ->assertRedirect(route('employee.approvals.index'));

        $this->assertDatabaseHas('lpjs', [
            'id' => $lpjId,
            'status' => 'approved',
        ]);
    }

    public function test_employee_dashboard_counts_pending_lpj_approvals(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'approver@example.test', 'full_name' => 'LPJ Approver']);
        $this->seedApprover(1, 'lpj', 2);
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('lpjs')->insert([
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'nomor_lpj' => 'LPJ-001',
            'total_anggaran' => 225000,
            'total_realisasi' => 250000,
            'sisa' => -25000,
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Ada 1 pengajuan menunggu approval Anda')
            ->assertSee('/employee/approvals', false);
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
