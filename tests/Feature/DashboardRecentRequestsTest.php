<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use App\Support\AdminDashboardSummary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardRecentRequestsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-09 10:00:00');
        $this->createDashboardSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_recent_requests_combines_latest_approval_modules_for_admin_company(): void
    {
        $this->seedRecentRequestData();
        session(['admin_id' => 1]);

        $view = app(DashboardController::class)->index(app(AdminDashboardSummary::class));
        $recentRequests = $view->getData()['recentRequests'];

        $this->assertCount(5, $recentRequests);
        $this->assertSame(
            ['Lembur', 'Laporan Perjalanan', 'Budget', 'Koreksi Presensi', 'Cuti'],
            $recentRequests->pluck('category')->all()
        );
        $this->assertSame('Hari kerja', $recentRequests->firstWhere('category', 'Lembur')['type']);
        $this->assertSame('Cuti Sakit', $recentRequests->firstWhere('category', 'Cuti')['type']);
        $this->assertFalse($recentRequests->contains(fn ($request) => $request['employee_name'] === 'Other Company'));
    }

    private function seedRecentRequestData(): void
    {
        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'email' => 'admin@example.test', 'password' => 'secret', 'full_name' => 'Admin User', 'role' => 'admin', 'employment_status' => 'permanent', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'company_id' => 1, 'email' => 'staff@example.test', 'password' => 'secret', 'full_name' => 'Staff One', 'role' => 'employee', 'employment_status' => 'permanent', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'company_id' => 2, 'email' => 'other@example.test', 'password' => 'secret', 'full_name' => 'Other Company', 'role' => 'employee', 'employment_status' => 'permanent', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_types')->insert([
            ['id' => 1, 'name' => 'Cuti Sakit', 'max_days' => 365, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_requests')->insert([
            ['employee_id' => 2, 'leave_type_id' => 1, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'total_days' => 1, 'reason' => 'Sakit', 'status' => 'pending', 'current_step' => 1, 'created_at' => '2026-06-09 08:00:00', 'updated_at' => now()],
        ]);

        DB::table('attendance_requests')->insert([
            ['employee_id' => 2, 'date' => '2026-06-09', 'clock_in' => '08:00:00', 'clock_out' => null, 'reason' => 'Lupa clock in', 'status' => 'pending', 'current_step' => 1, 'created_at' => '2026-06-09 08:30:00', 'updated_at' => now()],
        ]);

        DB::table('budget_requests')->insert([
            ['employee_id' => 2, 'type' => 'budget', 'title' => 'Perjalanan dinas', 'status' => 'in_review', 'current_step' => 1, 'total_amount' => 1000000, 'created_at' => '2026-06-09 09:00:00', 'updated_at' => now()],
        ]);

        DB::table('travel_reports')->insert([
            ['employee_id' => 2, 'destination_city' => 'Bandung', 'departure_date' => '2026-06-12', 'return_date' => '2026-06-13', 'purpose' => 'Meeting', 'status' => 'pending', 'current_step' => 1, 'created_at' => '2026-06-09 09:30:00', 'updated_at' => now()],
        ]);

        DB::table('overtime_requests')->insert([
            ['employee_id' => 2, 'date' => '2026-06-09', 'overtime_type' => 'workday', 'total_duration' => 120, 'break_duration' => 0, 'reason' => 'Deploy', 'status' => 'pending', 'current_step' => 1, 'created_at' => '2026-06-09 09:45:00', 'updated_at' => now()],
            ['employee_id' => 3, 'date' => '2026-06-09', 'overtime_type' => 'holiday', 'total_duration' => 120, 'break_duration' => 0, 'reason' => 'Other company', 'status' => 'pending', 'current_step' => 1, 'created_at' => '2026-06-09 10:00:00', 'updated_at' => now()],
        ]);
    }

    private function createDashboardSchema(): void
    {
        foreach ([
            'travel_reports',
            'budget_requests',
            'attendance_requests',
            'overtime_requests',
            'leave_requests',
            'leave_types',
            'attendances',
            'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('photo')->nullable();
            $table->string('position')->nullable();
            $table->string('role')->default('employee');
            $table->string('employment_status')->default('permanent');
            $table->boolean('is_active')->default(true);
            $table->date('resign_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
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

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('destination_city');
            $table->date('departure_date');
            $table->date('return_date');
            $table->text('purpose');
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });
    }
}
