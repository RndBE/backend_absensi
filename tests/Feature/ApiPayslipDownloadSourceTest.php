<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiPayslipDownloadSourceTest extends TestCase
{
    public function test_api_payslip_download_provides_full_pdf_view_context(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/PayslipController.php'));

        $this->assertStringContainsString('employee.activePayroll', $source);
        $this->assertStringContainsString('$company', $source);
        $this->assertStringContainsString('$logoBase64', $source);
        $this->assertStringContainsString('$bpjsData', $source);
        $this->assertStringContainsString("compact('detail', 'company', 'logoBase64', 'bpjsData')", $source);
    }
}
