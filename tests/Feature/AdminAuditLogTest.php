<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutating_admin_requests_are_logged_with_sanitized_payload(): void
    {
        $company = Company::create(['name' => 'Audit Company']);
        $admin = Employee::create([
            'employee_code' => 'ADM-AUDIT',
            'company_id' => $company->id,
            'full_name' => 'Audit Admin',
            'email' => 'audit-admin@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
        ]);

        $response = $this->withSession(['admin_id' => $admin->id])
            ->from(route('admin.departments.index'))
            ->post(route('admin.departments.store'), [
                'name' => 'Finance',
                'password' => 'secret-password',
            ]);

        $response->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseHas('admin_audit_logs', [
            'employee_id' => $admin->id,
            'company_id' => $company->id,
            'route_name' => 'admin.departments.store',
            'method' => 'POST',
            'status_code' => 302,
        ]);

        $payload = DB::table('admin_audit_logs')->value('payload');

        $this->assertStringContainsString('Finance', $payload);
        $this->assertStringContainsString('[FILTERED]', $payload);
        $this->assertStringNotContainsString('secret-password', $payload);
    }

    public function test_superadmin_can_view_audit_log_page(): void
    {
        $company = Company::create(['name' => 'Audit Company']);
        $admin = Employee::create([
            'employee_code' => 'ADM-AUDIT',
            'company_id' => $company->id,
            'full_name' => 'Audit Admin',
            'email' => 'audit-page@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => 'superadmin',
        ]);

        DB::table('admin_audit_logs')->insert([
            'employee_id' => $admin->id,
            'company_id' => $company->id,
            'action' => 'admin.departments.store',
            'route_name' => 'admin.departments.store',
            'method' => 'POST',
            'path' => 'admin/departments',
            'status_code' => 302,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature Test',
            'payload' => json_encode(['name' => 'Finance']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['admin_id' => $admin->id])
            ->get(route('admin.audit-logs.index'));

        $response->assertOk();
        $response->assertSee('Audit Log');
        $response->assertSee('admin.departments.store');
    }
}
