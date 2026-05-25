<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_monthly_hr_and_attendance_summaries(): void
    {
        Carbon::setTestNow('2026-05-25 09:00:00');

        $company = Company::create(['name' => 'Dashboard Company']);
        $admin = $this->employee($company, 'ADM-001', 'Admin User', 'admin-dashboard@example.test', 'superadmin');
        $employee = $this->employee($company, 'EMP-001', 'Employee One', 'employee-dashboard@example.test');
        $contractEmployee = $this->employee($company, 'EMP-002', 'Contract Ending Soon', 'contract-dashboard@example.test', 'employee', [
            'employment_status' => 'contract',
            'contract_end_date' => '2026-06-10',
        ]);
        $this->employee($company, 'EMP-003', 'Resigned This Month', 'resign-dashboard@example.test', 'employee', [
            'is_active' => false,
            'resign_date' => '2026-05-12',
        ]);

        $otherCompany = Company::create(['name' => 'Other Company']);
        $otherEmployee = $this->employee($otherCompany, 'OTH-001', 'Other Employee', 'other-dashboard@example.test');

        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-05-02', 'clock_in' => '09:30', 'status' => 'present', 'is_late' => true]);
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-05-03', 'clock_in' => '09:40', 'status' => 'present', 'is_late' => true]);
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-04-28', 'clock_in' => '09:50', 'status' => 'present', 'is_late' => true]);
        Attendance::create(['employee_id' => $otherEmployee->id, 'date' => '2026-05-04', 'clock_in' => '09:50', 'status' => 'present', 'is_late' => true]);

        $leaveType = LeaveType::create(['name' => 'Tahunan', 'default_days' => 12]);
        LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'start_date' => '2026-05-27', 'end_date' => '2026-05-27', 'total_days' => 1, 'reason' => 'Family', 'status' => 'pending']);
        LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'start_date' => '2026-05-28', 'end_date' => '2026-05-28', 'total_days' => 1, 'reason' => 'Family', 'status' => 'pending']);

        OvertimeRequest::create(['employee_id' => $employee->id, 'date' => '2026-05-26', 'planned_start' => '18:00', 'planned_end' => '20:00', 'total_duration' => 120, 'reason' => 'Deploy', 'status' => 'pending']);
        AttendanceRequest::create(['employee_id' => $employee->id, 'date' => '2026-05-24', 'clock_in' => '09:00', 'reason' => 'Forgot', 'status' => 'pending']);

        $response = $this->withSession(['admin_id' => $admin->id])
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Terlambat Bulan Ini');
        $response->assertSee('Cuti Pending');
        $response->assertSee('Lembur Pending');
        $response->assertSee('Presensi Pending');
        $response->assertSee('Resign Bulan Ini');
        $response->assertSee('Kontrak Hampir Habis');
        $response->assertSee('Contract Ending Soon');
        $response->assertSee('10/06/2026');

        $response->assertSee('>2</div>', false);
        $response->assertSee('>1</div>', false);
        $response->assertDontSee('Other Employee');
    }

    private function employee(
        Company $company,
        string $code,
        string $name,
        string $email,
        string $role = 'employee',
        array $overrides = []
    ): Employee {
        return Employee::create(array_merge([
            'employee_code' => $code,
            'company_id' => $company->id,
            'full_name' => $name,
            'email' => $email,
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => $role,
        ], $overrides));
    }
}
