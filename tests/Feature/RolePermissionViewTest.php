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
        $this->assertStringNotContainsString('Role Karyawan', $view);
        $this->assertStringNotContainsString('name="roles[]"', $view);
        $this->assertStringContainsString('Pilih Semua', $view);
        $this->assertStringContainsString('Kosongkan', $view);
        $this->assertStringContainsString('style="z-index: 1000;"', $view);
        $this->assertStringContainsString('role-permission-dialog', $view);
        $this->assertStringContainsString('role-permission-modal-shell', $view);
        $this->assertStringContainsString('width: min(1040px, calc(100vw - 32px));', $view);
        $this->assertStringContainsString('lockRolePermissionModalScroll', $view);
        $this->assertStringContainsString('window.innerWidth - document.documentElement.clientWidth', $view);
        $this->assertStringNotContainsString('w-full max-w-5xl', $view);
    }

    public function test_role_management_has_its_own_view(): void
    {
        $view = file_get_contents(resource_path('views/admin/roles/index.blade.php'));

        $this->assertStringContainsString('Role Karyawan', $view);
        $this->assertStringContainsString('name="roles[]"', $view);
        $this->assertStringContainsString('admin.roles.employees.update', $view);
        $this->assertStringContainsString('id="employeeRoleModal"', $view);
        $this->assertStringContainsString('openEmployeeRoleModal', $view);
        $this->assertStringContainsString('data-role-edit-trigger', $view);
        $this->assertStringContainsString('lockPageScrollForModal', $view);
        $this->assertStringContainsString('window.innerWidth - document.documentElement.clientWidth', $view);
        $this->assertStringContainsString('document.body.style.paddingRight', $view);
        $this->assertStringNotContainsString('xl:grid-cols-[1fr_420px]', $view);
    }
}
