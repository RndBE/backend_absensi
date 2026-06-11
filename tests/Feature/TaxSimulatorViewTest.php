<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaxSimulatorViewTest extends TestCase
{
    public function test_simulator_view_explains_ter_monthly_methodology(): void
    {
        $view = file_get_contents(resource_path('views/admin/tax/simulator.blade.php'));

        $this->assertStringContainsString('Masa Pajak', $view);
        $this->assertStringContainsString('TER Bulanan PP 58/2023', $view);
        $this->assertStringContainsString('PPh 21 Jan-Nov', $view);
    }
}
