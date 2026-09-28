<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\TravelReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedTinyInteger('lhp_deadline_days')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->date('date');
            $table->string('name')->nullable();
            $table->boolean('is_national')->default(false);
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
            $table->unsignedBigInteger('resubmission_of_id')->nullable();
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->string('destination_city');
            $table->unsignedInteger('distance_km')->nullable();
            $table->unsignedBigInteger('travel_zone_id')->nullable();
            $table->date('departure_date');
            $table->date('return_date');
            $table->date('submission_deadline')->nullable();
            $table->boolean('is_late')->default(false);
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
            'departure_date' => '2026-06-20',
            'return_date' => '2026-06-21',
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
                'departure_date' => '2026-06-20',
                'return_date' => '2026-06-21',
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
                'departure_date' => '2026-06-20',
                'return_date' => '2026-06-21',
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

    public function test_employee_lhp_edit_keeps_existing_documents_unless_removed(): void
    {
        Storage::fake('public');
        $this->seedEmployee();
        $budgetId = $this->seedApprovedBudgetRequest();

        $lhp = [
            'budget_request_id' => $budgetId,
            'destination_city' => 'Batam',
            'departure_date' => '2026-06-20',
            'return_date' => '2026-06-21',
            'purpose' => 'Kunjungan klien',
            'conclusion' => 'Kunjungan selesai',
        ];

        $this->withSession(['employee_id' => 1])
            ->post('/employee/travel-reports', $lhp + [
                'activities' => [
                    ['date' => '2026-06-20', 'description' => 'Meeting awal'],
                    ['date' => '2026-06-21', 'description' => 'Survei lokasi'],
                ],
                'activity_documents_0' => [
                    UploadedFile::fake()->image('meeting-1.jpg'),
                    UploadedFile::fake()->image('meeting-2.jpg'),
                ],
                'activity_documents_1' => [
                    UploadedFile::fake()->image('survei-1.jpg'),
                ],
            ])
            ->assertRedirect(route('employee.travel-reports.index'));

        $reportId = DB::table('travel_reports')->value('id');
        [$meetingPhoto1, $meetingPhoto2, $surveyPhoto] = DB::table('travel_report_documents')->orderBy('id')->get()->all();

        $this->withSession(['employee_id' => 1])
            ->get("/employee/travel-reports/{$reportId}/edit")
            ->assertOk()
            ->assertSee('storage/'.$meetingPhoto1->file_path, false)
            ->assertSee('name="activities[0][existing_documents][]" value="'.$meetingPhoto1->id.'"', false)
            ->assertSee('name="activities[0][existing_documents][]" value="'.$meetingPhoto2->id.'"', false)
            ->assertSee('name="activities[0][remove_documents][]" value="'.$meetingPhoto1->id.'"', false)
            ->assertSee('name="activities[1][existing_documents][]" value="'.$surveyPhoto->id.'"', false);

        // Simpan tanpa upload baru: semua foto tetap ada dan menempel ke aktivitas hasil buat ulang.
        $this->withSession(['employee_id' => 1])
            ->put("/employee/travel-reports/{$reportId}", $lhp + [
                'activities' => [
                    [
                        'date' => '2026-06-20',
                        'description' => 'Meeting awal dengan klien',
                        'existing_documents' => [$meetingPhoto1->id, $meetingPhoto2->id],
                    ],
                    [
                        'date' => '2026-06-21',
                        'description' => 'Survei lokasi',
                        'existing_documents' => [$surveyPhoto->id],
                    ],
                ],
            ])
            ->assertRedirect(route('employee.travel-reports.show', $reportId));

        $activityIds = DB::table('travel_report_activities')->pluck('id', 'description');
        $this->assertDatabaseCount('travel_report_documents', 3);
        $this->assertDatabaseHas('travel_report_documents', ['id' => $meetingPhoto1->id, 'travel_report_activity_id' => $activityIds['Meeting awal dengan klien'], 'sort_order' => 0]);
        $this->assertDatabaseHas('travel_report_documents', ['id' => $meetingPhoto2->id, 'travel_report_activity_id' => $activityIds['Meeting awal dengan klien'], 'sort_order' => 1]);
        $this->assertDatabaseHas('travel_report_documents', ['id' => $surveyPhoto->id, 'travel_report_activity_id' => $activityIds['Survei lokasi'], 'sort_order' => 0]);
        Storage::disk('public')->assertExists([$meetingPhoto1->file_path, $meetingPhoto2->file_path, $surveyPhoto->file_path]);

        // Centang Hapus pada satu foto: hanya foto itu (baris + file) yang hilang;
        // upload baru di aktivitas lain ditambahkan setelah foto lamanya.
        $this->withSession(['employee_id' => 1])
            ->put("/employee/travel-reports/{$reportId}", $lhp + [
                'activities' => [
                    [
                        'date' => '2026-06-20',
                        'description' => 'Meeting awal dengan klien',
                        'existing_documents' => [$meetingPhoto1->id, $meetingPhoto2->id],
                        'remove_documents' => [$meetingPhoto1->id],
                    ],
                    [
                        'date' => '2026-06-21',
                        'description' => 'Survei lokasi',
                        'existing_documents' => [$surveyPhoto->id],
                    ],
                ],
                'activity_documents_1' => [
                    UploadedFile::fake()->image('survei-2.jpg'),
                ],
            ])
            ->assertRedirect(route('employee.travel-reports.show', $reportId));

        $activityIds = DB::table('travel_report_activities')->pluck('id', 'description');
        $this->assertDatabaseCount('travel_report_documents', 3);
        $this->assertDatabaseMissing('travel_report_documents', ['id' => $meetingPhoto1->id]);
        $this->assertDatabaseHas('travel_report_documents', ['id' => $meetingPhoto2->id, 'travel_report_activity_id' => $activityIds['Meeting awal dengan klien'], 'sort_order' => 0]);
        $this->assertDatabaseHas('travel_report_documents', ['id' => $surveyPhoto->id, 'travel_report_activity_id' => $activityIds['Survei lokasi'], 'sort_order' => 0]);
        $newSurveyPhoto = DB::table('travel_report_documents')->orderByDesc('id')->first();
        $this->assertDatabaseHas('travel_report_documents', ['id' => $newSurveyPhoto->id, 'travel_report_activity_id' => $activityIds['Survei lokasi'], 'sort_order' => 1]);
        Storage::disk('public')->assertMissing($meetingPhoto1->file_path);
        Storage::disk('public')->assertExists([$meetingPhoto2->file_path, $surveyPhoto->file_path, $newSurveyPhoto->file_path]);
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

    public function test_tagged_participant_can_select_budget_request_for_lpj(): void
    {
        $this->seedEmployee();
        $this->seedEmployee([
            'id' => 2,
            'employee_code' => 'EMP002',
            'email' => 'participant@example.test',
            'full_name' => 'Tagged Participant',
        ]);
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('budget_request_participants')->insert([
            'budget_request_id' => $budgetId,
            'employee_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('budget_request_items')->insert([
            'budget_request_id' => $budgetId,
            'type' => 'meal',
            'description' => 'Uang makan tim',
            'amount' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/lpj/create')
            ->assertOk()
            ->assertSee('Perjalanan Batam')
            ->assertSee('Rp 225.000');
    }

    public function test_tagged_participant_can_create_own_lpj_after_owner_lpj_exists(): void
    {
        $this->seedEmployee();
        $this->seedEmployee([
            'id' => 2,
            'employee_code' => 'EMP002',
            'email' => 'participant@example.test',
            'full_name' => 'Tagged Participant',
        ]);
        $this->seedApprover(2, 'lpj', 1);
        $budgetId = $this->seedApprovedBudgetRequest();

        DB::table('budget_request_participants')->insert([
            'budget_request_id' => $budgetId,
            'employee_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('travel_reports')->insert([
            'employee_id' => 2,
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

        DB::table('lpjs')->insert([
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'nomor_lpj' => 'LPJ-PIC',
            'total_anggaran' => 225000,
            'total_realisasi' => 100000,
            'sisa' => 125000,
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 2])
            ->post('/employee/lpj', [
                'budget_request_id' => $budgetId,
                'nomor_lpj' => 'LPJ-PESERTA',
                'items' => [
                    [
                        'kategori' => 'meal',
                        'uraian' => 'Uang makan peserta',
                        'satuan' => 'Makan',
                        'volume' => 1,
                        'realisasi' => 75000,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lpjs', [
            'employee_id' => 2,
            'budget_request_id' => $budgetId,
            'nomor_lpj' => 'LPJ-PESERTA',
            'total_anggaran' => 225000,
            'total_realisasi' => 75000,
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

    public function test_lhp_rejection_requires_reason_and_notifies_employee(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'approver@example.test', 'full_name' => 'Approver One']);
        $this->seedApprover(1, 'travel_report', 2);

        $reportId = DB::table('travel_reports')->insertGetId([
            'employee_id' => 1,
            'destination_city' => 'Batam',
            'departure_date' => '2026-06-10',
            'return_date' => '2026-06-11',
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
            ->assertSee('(wajib diisi bila menolak)');

        $this->withSession(['employee_id' => 2])
            ->post("/employee/approvals/travel_report/{$reportId}/reject", ['notes' => ''])
            ->assertRedirect(route('employee.approvals.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('travel_reports', ['id' => $reportId, 'status' => 'pending']);

        $this->withSession(['employee_id' => 2])
            ->post("/employee/approvals/travel_report/{$reportId}/reject", ['notes' => 'Foto kegiatan kurang'])
            ->assertRedirect(route('employee.approvals.index'));

        $this->assertDatabaseHas('travel_reports', ['id' => $reportId, 'status' => 'rejected']);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => 1,
            'title' => 'Pengajuan LHP Ditolak',
            'message' => 'Pengajuan LHP Anda ditolak oleh Approver One: Foto kegiatan kurang',
            'reference_type' => TravelReport::class,
            'reference_id' => $reportId,
        ]);
    }

    public function test_employee_can_resubmit_rejected_lhp_as_new_report(): void
    {
        Storage::fake('public');
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'lhp-approver@example.test', 'full_name' => 'LHP Approver']);
        $this->seedApprover(1, 'travel_report', 2);
        $budgetId = $this->seedApprovedBudgetRequest();
        $rejectedId = $this->seedRejectedTravelReport($budgetId);

        $activityId = DB::table('travel_report_activities')->insertGetId([
            'travel_report_id' => $rejectedId,
            'activity_date' => '2026-06-10',
            'description' => 'Meeting awal',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put('travel-report-docs/lama.jpg', 'foto');
        DB::table('travel_report_documents')->insert([
            'travel_report_id' => $rejectedId,
            'travel_report_activity_id' => $activityId,
            'file_path' => 'travel-report-docs/lama.jpg',
            'caption' => 'Foto meeting',
            'activity_date' => '2026-06-10',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get("/employee/travel-reports/{$rejectedId}")
            ->assertOk()
            ->assertSee('LHP ditolak')
            ->assertSee('Foto kegiatan hari kedua belum ada')
            ->assertSee("/employee/travel-reports/{$rejectedId}/resubmit", false);

        $this->withSession(['employee_id' => 1])
            ->get("/employee/travel-reports/{$rejectedId}/resubmit")
            ->assertOk()
            ->assertSee('Ajukan Ulang LHP')
            ->assertSee('Yang perlu diperbaiki')
            ->assertSee('Foto kegiatan hari kedua belum ada')
            ->assertSee('Meeting awal')
            ->assertSee('name="resubmission_of_id" value="'.$rejectedId.'"', false);

        $payload = [
            'resubmission_of_id' => $rejectedId,
            'budget_request_id' => $budgetId,
            'destination_city' => 'Batam',
            'departure_date' => '2026-06-10',
            'return_date' => '2026-06-11',
            'purpose' => 'Kunjungan klien',
            'conclusion' => 'Selesai, foto hari kedua sudah dilengkapi',
            'activities' => [
                ['date' => '2026-06-10', 'description' => 'Meeting awal'],
            ],
        ];

        $this->withSession(['employee_id' => 1])
            ->post('/employee/travel-reports', $payload)
            ->assertRedirect(route('employee.travel-reports.index'));

        $replacement = TravelReport::where('resubmission_of_id', $rejectedId)->firstOrFail();
        $this->assertSame('pending', $replacement->status);
        $this->assertSame(1, (int) $replacement->current_step);
        // Batas & status telat diwarisi dari LHP yang ditolak, bukan dihitung ulang.
        $this->assertSame('2026-06-18', $replacement->submission_deadline?->toDateString());
        $this->assertFalse($replacement->is_late);
        $this->assertDatabaseHas('travel_reports', ['id' => $rejectedId, 'status' => 'rejected']);

        // Foto disalin ke file baru dan dipasang di aktivitas pengganti.
        $copied = $replacement->documents()->with('activity')->get();
        $this->assertCount(1, $copied);
        $this->assertNotSame('travel-report-docs/lama.jpg', $copied[0]->file_path);
        Storage::disk('public')->assertExists($copied[0]->file_path);
        $this->assertSame('Meeting awal', $copied[0]->activity?->description);

        $this->assertDatabaseHas('notifications', [
            'employee_id' => 2,
            'title' => 'Pengajuan Ulang LHP',
            'reference_id' => $replacement->id,
        ]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/approvals')
            ->assertOk()
            ->assertSee('Pengajuan ulang')
            ->assertSee('Ditolak sebelumnya')
            ->assertSee('Foto kegiatan hari kedua belum ada');

        $this->withSession(['employee_id' => 1])
            ->get("/employee/travel-reports/{$replacement->id}")
            ->assertOk()
            ->assertSee('Pengajuan ulang dari LHP yang ditolak');

        $this->withSession(['employee_id' => 1])
            ->get("/employee/travel-reports/{$rejectedId}")
            ->assertOk()
            ->assertSee('Lihat LHP pengganti');

        // LHP yang sudah diajukan ulang tidak bisa diajukan ulang lagi.
        $this->withSession(['employee_id' => 1])
            ->post('/employee/travel-reports', $payload)
            ->assertSessionHas('error');

        $this->assertSame(2, DB::table('travel_reports')->count());
    }

    public function test_resubmission_copies_only_photos_kept_in_form(): void
    {
        Storage::fake('public');
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'lhp-approver@example.test', 'full_name' => 'LHP Approver']);
        $this->seedApprover(1, 'travel_report', 2);
        $budgetId = $this->seedApprovedBudgetRequest();
        $rejectedId = $this->seedRejectedTravelReport($budgetId);

        $activityId = DB::table('travel_report_activities')->insertGetId([
            'travel_report_id' => $rejectedId,
            'activity_date' => '2026-06-10',
            'description' => 'Meeting awal',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seedDocument = function (int $reportId, ?int $activityId, string $path, string $caption): int {
            Storage::disk('public')->put($path, 'foto');

            return DB::table('travel_report_documents')->insertGetId([
                'travel_report_id' => $reportId,
                'travel_report_activity_id' => $activityId,
                'file_path' => $path,
                'caption' => $caption,
                'activity_date' => '2026-06-10',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };
        $keptId = $seedDocument($rejectedId, $activityId, 'travel-report-docs/dipakai.jpg', 'Foto dipakai');
        $removedId = $seedDocument($rejectedId, $activityId, 'travel-report-docs/dibuang.jpg', 'Foto dibuang');
        $seedDocument($rejectedId, null, 'travel-report-docs/umum.jpg', 'Foto umum');

        // Foto milik LHP lain tidak boleh ikut tersalin walau ID-nya diselipkan di request.
        $otherReportId = DB::table('travel_reports')->insertGetId([
            'employee_id' => 2,
            'destination_city' => 'Medan',
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-02',
            'purpose' => 'Lain',
            'status' => 'approved',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherActivityId = DB::table('travel_report_activities')->insertGetId([
            'travel_report_id' => $otherReportId,
            'activity_date' => '2026-06-01',
            'description' => 'Lain',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignId = $seedDocument($otherReportId, $otherActivityId, 'travel-report-docs/orang-lain.jpg', 'Foto orang lain');

        $this->withSession(['employee_id' => 1])
            ->post('/employee/travel-reports', [
                'resubmission_of_id' => $rejectedId,
                'document_selection' => 1,
                'budget_request_id' => $budgetId,
                'destination_city' => 'Batam',
                'departure_date' => '2026-06-10',
                'return_date' => '2026-06-11',
                'purpose' => 'Kunjungan klien',
                'conclusion' => 'Selesai',
                'activities' => [
                    [
                        'date' => '2026-06-10',
                        'description' => 'Meeting awal',
                        'existing_documents' => [$keptId, $removedId, $foreignId],
                        'remove_documents' => [$removedId],
                    ],
                ],
                'activity_documents_0' => [UploadedFile::fake()->create('baru.jpg', 10, 'image/jpeg')],
            ])
            ->assertRedirect(route('employee.travel-reports.index'));

        $replacement = TravelReport::where('resubmission_of_id', $rejectedId)->firstOrFail();
        $documents = $replacement->documents()->get();

        // Foto aktivitas: yang dipertahankan lebih dulu, lalu unggahan baru.
        $activityDocuments = $documents->whereNotNull('travel_report_activity_id')->sortBy('sort_order')->values();
        $this->assertSame(['Foto dipakai', null], $activityDocuments->pluck('caption')->all());
        $this->assertSame([0, 1], $activityDocuments->pluck('sort_order')->map(fn ($order) => (int) $order)->all());

        // Dokumentasi umum tidak tampil di form, jadi tetap ikut tersalin.
        $this->assertSame(['Foto umum'], $documents->whereNull('travel_report_activity_id')->pluck('caption')->values()->all());

        $this->assertCount(3, $documents);
        $this->assertFalse($documents->contains('caption', 'Foto dibuang'));
        $this->assertFalse($documents->contains('caption', 'Foto orang lain'));
    }

    public function test_resubmit_form_lets_employee_drop_old_photos(): void
    {
        Storage::fake('public');
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'lhp-approver@example.test', 'full_name' => 'LHP Approver']);
        $this->seedApprover(1, 'travel_report', 2);
        $budgetId = $this->seedApprovedBudgetRequest();
        $rejectedId = $this->seedRejectedTravelReport($budgetId);
        $activityId = DB::table('travel_report_activities')->insertGetId([
            'travel_report_id' => $rejectedId,
            'activity_date' => '2026-06-10',
            'description' => 'Meeting awal',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put('travel-report-docs/lama.jpg', 'foto');
        $documentId = DB::table('travel_report_documents')->insertGetId([
            'travel_report_id' => $rejectedId,
            'travel_report_activity_id' => $activityId,
            'file_path' => 'travel-report-docs/lama.jpg',
            'caption' => 'Foto lama',
            'activity_date' => '2026-06-10',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Form ajukan ulang menampilkan foto lama dengan centang Hapus.
        $this->withSession(['employee_id' => 1])
            ->get("/employee/travel-reports/{$rejectedId}/resubmit")
            ->assertOk()
            ->assertSee('name="document_selection" value="1"', false)
            ->assertSee('name="activities[0][existing_documents][]" value="'.$documentId.'"', false)
            ->assertSee('name="activities[0][remove_documents][]" value="'.$documentId.'"', false)
            ->assertSee('kecuali yang dicentang Hapus');

        // Kirim seperti browser: foto lama tercantum tapi dicentang Hapus.
        $this->withSession(['employee_id' => 1])
            ->post('/employee/travel-reports', [
                'resubmission_of_id' => $rejectedId,
                'document_selection' => 1,
                'budget_request_id' => $budgetId,
                'destination_city' => 'Batam',
                'departure_date' => '2026-06-10',
                'return_date' => '2026-06-11',
                'purpose' => 'Kunjungan klien',
                'conclusion' => 'Selesai',
                'activities' => [
                    [
                        'date' => '2026-06-10',
                        'description' => 'Meeting awal',
                        'existing_documents' => [$documentId],
                        'remove_documents' => [$documentId],
                    ],
                ],
            ])
            ->assertRedirect(route('employee.travel-reports.index'));

        $replacement = TravelReport::where('resubmission_of_id', $rejectedId)->firstOrFail();
        $this->assertSame(0, $replacement->documents()->count());

        // LHP yang ditolak tidak tersentuh: fotonya tetap ada.
        $this->assertDatabaseHas('travel_report_documents', ['id' => $documentId, 'travel_report_id' => $rejectedId]);
        Storage::disk('public')->assertExists('travel-report-docs/lama.jpg');
    }

    public function test_new_lhp_for_budget_with_rejected_lhp_is_recorded_as_resubmission(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'lhp-approver@example.test', 'full_name' => 'LHP Approver']);
        $this->seedApprover(1, 'travel_report', 2);
        $budgetId = $this->seedApprovedBudgetRequest();
        DB::table('budget_requests')->where('id', $budgetId)->update(['return_date' => '2026-06-11']);
        $rejectedId = $this->seedRejectedTravelReport($budgetId);

        // LHP yang ditolak tidak lagi menyembunyikan anggarannya.
        $this->withSession(['employee_id' => 1])
            ->get('/employee/travel-reports/create')
            ->assertOk()
            ->assertSee('Perjalanan Batam');

        $this->withSession(['employee_id' => 1])
            ->get('/employee/budget-requests')
            ->assertOk()
            ->assertSee('LHP ditolak · ajukan ulang')
            ->assertSee("/employee/travel-reports/{$rejectedId}/resubmit", false);

        $this->withSession(['employee_id' => 1])
            ->post('/employee/travel-reports', [
                'budget_request_id' => $budgetId,
                'destination_city' => 'Batam',
                'departure_date' => '2026-06-10',
                'return_date' => '2026-06-11',
                'purpose' => 'Kunjungan klien',
                'conclusion' => 'Selesai',
                'activities' => [
                    ['date' => '2026-06-10', 'description' => 'Meeting awal'],
                ],
            ])
            ->assertRedirect(route('employee.travel-reports.index'));

        $this->assertDatabaseHas('travel_reports', [
            'resubmission_of_id' => $rejectedId,
            'budget_request_id' => $budgetId,
            'status' => 'pending',
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

    public function test_admin_lhp_index_filters_by_employee_date_and_letter_number(): void
    {
        $this->seedEmployee(['department_id' => 1, 'full_name' => 'Budi Santoso']);
        $this->seedEmployee([
            'id' => 2,
            'employee_code' => 'ADM001',
            'email' => 'admin@example.test',
            'full_name' => 'Admin Finance',
            'role' => 'superadmin',
            'department_id' => 1,
        ]);
        $this->seedEmployee([
            'id' => 3,
            'employee_code' => 'EMP003',
            'email' => 'citra@example.test',
            'full_name' => 'Citra Lestari',
            'department_id' => 1,
        ]);

        foreach ([
            [1, 'Semarang', '036/ST-ATC/VI/2026', '2026-06-10', '2026-06-12'],
            [1, 'Magelang', null, '2026-07-01', '2026-07-02'],
            [3, 'Surabaya', '041/ST-ATC/VII/2026', '2026-07-05', '2026-07-08'],
        ] as [$employeeId, $city, $suratTugasNo, $departure, $return]) {
            DB::table('travel_reports')->insert([
                'employee_id' => $employeeId,
                'surat_tugas_no' => $suratTugasNo,
                'destination_city' => $city,
                'departure_date' => $departure,
                'return_date' => $return,
                'purpose' => 'Kunjungan klien',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $admin = $this->withSession(['admin_id' => 2]);

        // Dropdown hanya berisi karyawan yang punya LHP.
        $admin->get('/admin/travel-reports')
            ->assertOk()
            ->assertSee('036/ST-ATC/VI/2026')
            ->assertSee('Belum diisi')
            ->assertSee('>Budi Santoso</option>', false)
            ->assertSee('>Citra Lestari</option>', false)
            ->assertDontSee('>Admin Finance</option>', false);

        $admin->get('/admin/travel-reports?employee_id=3')
            ->assertOk()
            ->assertSee('Surabaya')
            ->assertDontSee('Semarang')
            ->assertDontSee('Magelang')
            ->assertSee('employee_id=3&amp;status=approved', false);

        $admin->get('/admin/travel-reports?surat_tugas_no=036')
            ->assertOk()
            ->assertSee('Semarang')
            ->assertDontSee('Magelang')
            ->assertDontSee('Surabaya');

        // Rentang 2-6 Juli beririsan dengan perjalanan 1-2 Juli dan 5-8 Juli, tidak dengan 10-12 Juni.
        $admin->get('/admin/travel-reports?date_from=2026-07-02&date_to=2026-07-06')
            ->assertOk()
            ->assertSee('Magelang')
            ->assertSee('Surabaya')
            ->assertDontSee('Semarang');
    }

    public function test_admin_lhp_index_links_rejected_lhp_and_its_resubmission(): void
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
        $rejectedId = $this->seedRejectedTravelReport($budgetId);
        $replacementId = DB::table('travel_reports')->insertGetId([
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'resubmission_of_id' => $rejectedId,
            'destination_city' => 'Batam',
            'departure_date' => '2026-06-10',
            'return_date' => '2026-06-11',
            'purpose' => 'Kunjungan klien',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['admin_id' => 2])
            ->get('/admin/travel-reports')
            ->assertOk()
            // LHP yang ditolak menautkan ke penggantinya, dan sebaliknya.
            ->assertSee('DIAJUKAN ULANG')
            ->assertSee('LHP pengganti berstatus Pending')
            ->assertSee('href="'.route('admin.travel-reports.show', $replacementId).'" title="LHP pengganti', false)
            ->assertSee('PENGAJUAN ULANG')
            ->assertSee('href="'.route('admin.travel-reports.show', $rejectedId).'" title="Pengganti LHP yang ditolak', false);
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

    public function test_employee_can_print_approved_budget_from_approval_history(): void
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

        DB::table('budget_requests')->where('id', $budgetId)->update(['status' => 'pending']);

        $this->withSession(['employee_id' => 2])
            ->post("/employee/approvals/budget/{$budgetId}/approve", ['notes' => 'Anggaran disetujui'])
            ->assertRedirect(route('employee.approvals.index'));

        $this->withSession(['employee_id' => 2])
            ->get('/employee/approvals?tab=history')
            ->assertOk()
            ->assertSee("/employee/approvals/budget/{$budgetId}/print", false)
            ->assertSee('approval-history-print', false);

        $this->withSession(['employee_id' => 2])
            ->get("/employee/approvals/budget/{$budgetId}/print")
            ->assertOk()
            ->assertSee('FORM PENGAJUAN ANGGARAN PT. ARTA TEKNOLOGI COMUNINDO')
            ->assertSee('/employee/approvals?tab=history', false);
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

    private function seedRejectedTravelReport(int $budgetId): int
    {
        $reportId = DB::table('travel_reports')->insertGetId([
            'employee_id' => 1,
            'budget_request_id' => $budgetId,
            'destination_city' => 'Batam',
            'departure_date' => '2026-06-10',
            'return_date' => '2026-06-11',
            'submission_deadline' => '2026-06-18',
            'is_late' => false,
            'purpose' => 'Kunjungan klien',
            'conclusion' => 'Selesai',
            'status' => 'rejected',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('approval_logs')->insert([
            'approvable_type' => TravelReport::class,
            'approvable_id' => $reportId,
            'approver_id' => 2,
            'action' => 'rejected',
            'step_order' => 1,
            'notes' => 'Foto kegiatan hari kedua belum ada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $reportId;
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
