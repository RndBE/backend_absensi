<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaxSettingsViewTest extends TestCase
{
    public function test_bpjs_npp_field_is_presented_as_reporting_identity(): void
    {
        $view = file_get_contents(resource_path('views/admin/tax/settings.blade.php'));

        $this->assertStringContainsString('Identitas Pelaporan BPJS', $view);
        $this->assertStringContainsString('NPP Perusahaan BPJS Ketenagakerjaan', $view);
        $this->assertStringContainsString('Tidak memengaruhi perhitungan payroll', $view);
    }
}
