<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminTravelZoneViewTest extends TestCase
{
    public function test_travel_zone_modals_use_viewport_centered_shells(): void
    {
        $view = file_get_contents(resource_path('views/admin/travel-zones/index.blade.php'));

        $this->assertStringContainsString('id="createModal"', $view);
        $this->assertStringContainsString('id="editModal"', $view);
        $this->assertStringContainsString('z-[80] overflow-y-auto bg-slate-900/45 px-4 py-6', $view);
        $this->assertStringContainsString('min-h-[calc(100vh-3rem)] items-start justify-center sm:items-center', $view);
        $this->assertStringContainsString('function openZoneModal', $view);
        $this->assertStringContainsString('function closeZoneModal', $view);
        $this->assertStringNotContainsString('min-h-full', $view);
    }

    public function test_travel_zone_km_inputs_have_stable_columns(): void
    {
        $view = file_get_contents(resource_path('views/admin/travel-zones/index.blade.php'));

        $this->assertStringContainsString('grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]', $view);
        $this->assertStringContainsString('relative min-w-0', $view);
        $this->assertStringContainsString('id="edit_min_km"', $view);
        $this->assertStringContainsString('id="edit_max_km"', $view);
    }
}
