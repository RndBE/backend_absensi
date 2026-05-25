<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConfirmModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_layout_includes_reusable_confirmation_modal(): void
    {
        $admin = $this->admin();

        $response = $this
            ->withSession(['admin_id' => $admin->id])
            ->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('id="admin-confirm-modal"', false);
        $response->assertSee('data-confirm-modal-title', false);
        $response->assertSee('data-confirm-modal-message', false);
    }

    private function admin(): Employee
    {
        $company = Company::create(['name' => 'Test Company']);

        return Employee::create([
            'employee_code' => 'ADM-001',
            'company_id' => $company->id,
            'full_name' => 'Admin User',
            'email' => 'admin-confirm@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
        ]);
    }
}
