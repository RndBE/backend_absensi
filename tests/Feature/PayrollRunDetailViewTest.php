<?php

namespace Tests\Feature;

use Tests\TestCase;

class PayrollRunDetailViewTest extends TestCase
{
    public function test_draft_payroll_detail_view_has_edit_action_modal(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-runs/show.blade.php'));

        $this->assertStringContainsString("route('admin.payroll-runs.update-detail'", $view);
        $this->assertStringContainsString('Edit Detail', $view);
        $this->assertStringContainsString('openPayrollDetailEdit', $view);
    }

    public function test_auto_payroll_components_are_locked_in_edit_modal(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-runs/show.blade.php'));

        $this->assertStringContainsString('$isAutoComponent', $view);
        $this->assertStringNotContainsString('Komponen otomatis', $view);
        $this->assertStringContainsString('@unless($isAutoComponent)', $view);
        $this->assertStringContainsString('@disabled($isAutoComponent)', $view);
    }

    public function test_update_detail_only_counts_deduction_components_as_deductions(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/PayrollRunController.php'));

        $this->assertStringContainsString("} elseif (\$comp['type'] === 'deduction') {", $controller);
    }

    public function test_edit_detail_component_table_keeps_dropdown_room(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-runs/show.blade.php'));

        $this->assertStringContainsString('min-w-[600px]', $view);
        $this->assertStringContainsString('pb-32', $view);
        $this->assertStringContainsString('overflow-x-auto overflow-y-visible', $view);
    }

    public function test_overtime_component_can_open_detail_modal(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-runs/show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/PayrollRunController.php'));

        $this->assertStringContainsString('openPayrollOvertimeDetail', $view);
        $this->assertStringContainsString('payrollOvertimeDetail-', $view);
        $this->assertStringContainsString('Rincian Lembur', $view);
        $this->assertStringContainsString('$component[\'lines\']', $view);
        $this->assertStringContainsString('fallbackOvertimeLinesForDetail', $controller);
        $this->assertStringContainsString("'lines' => \$overtimeData['lines']", $controller);
    }
}
