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
        $this->assertStringNotContainsString('data-confirm="Nonaktifkan karyawan ini?"', $view);
    }

    public function test_internship_supervisor_field_is_marked_optional(): void
    {
        foreach ([
            resource_path('views/admin/employees/create.blade.php'),
            resource_path('views/admin/employees/edit.blade.php'),
        ] as $viewPath) {
            $view = file_get_contents($viewPath);

            $this->assertStringContainsString('Pembimbing Institusi <span class="font-normal text-gray-400">(Opsional)</span>', $view);
            $this->assertStringContainsString('Boleh dikosongi jika tidak ada pembimbing dari kampus/sekolah', $view);
        }
    }

    public function test_internship_field_supervisor_is_available_on_employee_forms_and_detail(): void
    {
        foreach ([
            resource_path('views/admin/employees/create.blade.php'),
            resource_path('views/admin/employees/edit.blade.php'),
        ] as $viewPath) {
            $view = file_get_contents($viewPath);

            $this->assertStringContainsString('Pembimbing Lapangan / Kantor', $view);
            $this->assertStringContainsString('name="internship_field_supervisor"', $view);
            $this->assertStringContainsString('Nama pembimbing di kantor/lapangan', $view);
        }

        $showView = file_get_contents(resource_path('views/admin/employees/show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/EmployeeController.php'));
        $model = file_get_contents(app_path('Models/Employee.php'));
        $migration = file_get_contents(database_path('migrations/2026_05_29_101000_add_internship_field_supervisor_to_employees_table.php'));

        $this->assertStringContainsString('Pembimbing Lapangan / Kantor', $showView);
        $this->assertStringContainsString('$employee->internship_field_supervisor', $showView);
        $this->assertStringContainsString("'internship_field_supervisor' => 'nullable|string|max:255'", $controller);
        $this->assertStringContainsString("'internship_field_supervisor'", $model);
        $this->assertStringContainsString("string('internship_field_supervisor')", $migration);
    }

    public function test_employee_edit_loads_all_approval_request_types(): void
    {
        $view = file_get_contents(resource_path('views/admin/employees/edit.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/EmployeeController.php'));

        $this->assertStringContainsString("'travel_report' => 'LHP'", $view);
        $this->assertStringContainsString("private const APPROVAL_REQUEST_TYPES = ['leave', 'overtime', 'attendance', 'budget', 'travel_report'];", $controller);
        $this->assertStringContainsString('foreach (self::APPROVAL_REQUEST_TYPES as $type)', $controller);
        $this->assertStringNotContainsString("foreach (['leave', 'overtime', 'attendance'] as \$type)", $controller);
    }
}
