<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_login_to_dashboard(): void
    {
        $employee = $this->employee(['role' => 'admin']);

        $response = $this->post(route('admin.login'), [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertSame($employee->id, session('admin_id'));
    }

    public function test_active_superadmin_can_login_to_dashboard(): void
    {
        $employee = $this->employee(['role' => 'superadmin']);

        $response = $this->post(route('admin.login'), [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertSame($employee->id, session('admin_id'));
    }

    public function test_regular_employee_cannot_login_to_admin_dashboard(): void
    {
        $employee = $this->employee(['role' => 'employee']);

        $response = $this->from(route('admin.login'))->post(route('admin.login'), [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $this->assertFalse(session()->has('admin_id'));
    }

    public function test_inactive_admin_cannot_login_to_admin_dashboard(): void
    {
        $employee = $this->employee([
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login'), [
            'email' => $employee->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $this->assertFalse(session()->has('admin_id'));
    }

    private function employee(array $attributes = []): Employee
    {
        $company = Company::create(['name' => 'Test Company']);

        return Employee::create(array_merge([
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'company_id' => $company->id,
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'employee',
        ], $attributes));
    }
}
