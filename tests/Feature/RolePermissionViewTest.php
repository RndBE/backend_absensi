<?php

namespace Tests\Feature;

use Tests\TestCase;

class RolePermissionViewTest extends TestCase
{
    public function test_role_permission_view_uses_modals_for_permission_editing(): void
    {
        $view = file_get_contents(resource_path('views/admin/role-permissions/index.blade.php'));

        $this->assertStringContainsString('id="rolePermissionModal"', $view);
        $this->assertStringContainsString('id="employeeOverrideModal"', $view);
        $this->assertStringContainsString('openRolePermissionModal', $view);
        $this->assertStringContainsString('openEmployeeOverrideModal', $view);
        $this->assertStringContainsString('data-role-modal-trigger', $view);
        $this->assertStringContainsString('data-override-modal-trigger', $view);
        $this->assertStringContainsString('Pilih Semua', $view);
        $this->assertStringContainsString('Kosongkan', $view);
        $this->assertStringContainsString('style="z-index: 1000;"', $view);
        $this->assertStringContainsString('role-permission-dialog', $view);
    }
}
