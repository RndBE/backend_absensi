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
}
