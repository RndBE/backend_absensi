<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Support\AdminDataScope;
use Tests\TestCase;

/**
 * Aturan scope: hanya role manager (yang punya departemen) yang dibatasi ke
 * departemennya. Role lain tidak dibatasi.
 */
class AdminDataScopeTest extends TestCase
{
    private function employee(string $role, ?int $departmentId): Employee
    {
        $e = new Employee();
        $e->role = $role;
        $e->department_id = $departmentId;

        return $e;
    }

    public function test_manager_is_scoped_to_own_department(): void
    {
        $manager = $this->employee('manager', 7);

        $this->assertEquals(7, AdminDataScope::departmentId($manager));
        $this->assertTrue(AdminDataScope::isDepartmentScoped($manager));
    }

    public function test_manager_without_department_is_not_scoped(): void
    {
        $manager = $this->employee('manager', null);

        $this->assertNull(AdminDataScope::departmentId($manager));
        $this->assertFalse(AdminDataScope::isDepartmentScoped($manager));
    }

    public function test_other_roles_are_not_scoped(): void
    {
        foreach (['superadmin', 'hr_admin', 'finance_admin', 'admin', 'employee'] as $role) {
            $emp = $this->employee($role, 7);
            $this->assertNull(AdminDataScope::departmentId($emp), "role {$role} tidak boleh dibatasi departemen");
        }
    }

    public function test_null_admin_is_not_scoped(): void
    {
        $this->assertNull(AdminDataScope::departmentId(null));
    }
}
