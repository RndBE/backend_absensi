<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\AdminDashboardSummary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDashboardSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-26 09:00:00');

        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->string('employment_status')->default('permanent');
            $table->boolean('is_active')->default(true);
            $table->date('resign_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present');
            $table->string('review_status')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamps();
        });

        foreach (['leave_requests', 'overtime_requests', 'attendance_requests'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_summary_counts_company_scoped_attendance_approvals_and_hr_alerts(): void
    {
        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'email' => 'admin@example.test', 'password' => 'secret', 'full_name' => 'Admin', 'role' => 'admin', 'employment_status' => 'permanent', 'is_active' => true, 'resign_date' => null, 'contract_end_date' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'company_id' => 1, 'email' => 'a@example.test', 'password' => 'secret', 'full_name' => 'Active One', 'role' => 'employee', 'employment_status' => 'contract', 'is_active' => true, 'resign_date' => null, 'contract_end_date' => '2026-06-10', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'company_id' => 1, 'email' => 'b@example.test', 'password' => 'secret', 'full_name' => 'Active Two', 'role' => 'employee', 'employment_status' => 'permanent', 'is_active' => true, 'resign_date' => null, 'contract_end_date' => '2026-07-30', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'company_id' => 1, 'email' => 'resigned@example.test', 'password' => 'secret', 'full_name' => 'Resigned', 'role' => 'employee', 'employment_status' => 'permanent', 'is_active' => false, 'resign_date' => '2026-05-15', 'contract_end_date' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'company_id' => 2, 'email' => 'other@example.test', 'password' => 'secret', 'full_name' => 'Other Company', 'role' => 'employee', 'employment_status' => 'contract', 'is_active' => true, 'resign_date' => '2026-05-20', 'contract_end_date' => '2026-06-05', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('attendances')->insert([
            ['employee_id' => 2, 'date' => '2026-05-26', 'clock_in' => '08:01:00', 'clock_out' => null, 'status' => 'present', 'is_late' => true, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 5, 'date' => '2026-05-26', 'clock_in' => '08:00:00', 'clock_out' => null, 'status' => 'present', 'is_late' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_requests')->insert([
            ['employee_id' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 5, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('overtime_requests')->insert([
            ['employee_id' => 3, 'status' => 'in_review', 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 5, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('attendance_requests')->insert([
            ['employee_id' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $summary = app(AdminDashboardSummary::class)->forAdmin(Employee::findOrFail(1));

        $this->assertSame(3, $summary['attendance']['total_employees']);
        $this->assertSame(0, $summary['attendance']['present_today']);
        $this->assertSame(1, $summary['attendance']['late_today']);
        $this->assertSame(2, $summary['attendance']['absent_today']);
        $this->assertSame(1, $summary['approvals']['leave_pending']);
        $this->assertSame(1, $summary['approvals']['overtime_pending']);
        $this->assertSame(1, $summary['approvals']['attendance_pending']);
        $this->assertSame(3, $summary['approvals']['total_pending']);
        $this->assertArrayNotHasKey('payroll', $summary);
        $this->assertSame(1, $summary['hr']['resigned_this_month']);
        $this->assertSame(1, $summary['hr']['contracts_expiring_soon']);
        $this->assertSame(1, $summary['hr']['inactive_employees']);
    }
}
