<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminTravelReportPrintViewTest extends TestCase
{
    public function test_print_lhp_uses_existing_logo_asset(): void
    {
        $view = file_get_contents(resource_path('views/admin/travel-reports/print.blade.php'));

        $this->assertFileExists(public_path('images/logo_be2.png'));
        $this->assertStringContainsString("asset('images/logo_be2.png')", $view);
        $this->assertStringNotContainsString("asset('image/logo_beacon.png')", $view);
    }
}
