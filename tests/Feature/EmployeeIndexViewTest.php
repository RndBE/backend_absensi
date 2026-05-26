<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmployeeIndexViewTest extends TestCase
{
    public function test_employee_index_uses_resign_action_instead_of_direct_delete(): void
    {
        $view = file_get_contents(resource_path('views/admin/employees/index.blade.php'));

        $this->assertStringContainsString("route('admin.employees.resign', \$emp->id)", $view);
        $this->assertStringContainsString('title="Proses Resign"', $view);
        $this->assertStringContainsString('person_remove', $view);
        $this->assertStringNotContainsString("route('admin.employees.destroy', \$emp->id)", $view);
        $this->assertStringNotContainsString("data-confirm=\"Nonaktifkan karyawan ini?\"", $view);
    }
}
