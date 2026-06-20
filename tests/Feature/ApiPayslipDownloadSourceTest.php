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
        $this->assertStringContainsString('$loanSummary', $source);
        $this->assertStringContainsString("compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary')", $source);
    }

    public function test_api_payslip_show_preserves_loan_metadata_on_deductions(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/PayslipController.php'));

        $this->assertStringContainsString("'loan' => PayslipLoanSummary::forComponent(\$comp)", $source);
    }

    public function test_payslip_download_sources_use_safe_filenames(): void
    {
        $apiSource = file_get_contents(app_path('Http/Controllers/Api/PayslipController.php'));
        $adminSource = file_get_contents(app_path('Http/Controllers/Admin/PayslipController.php'));
        $mailSource = file_get_contents(app_path('Mail/PayslipPublishedMail.php'));

        $this->assertStringContainsString('PayslipFilename::make', $apiSource);
        $this->assertStringContainsString('PayslipFilename::make', $adminSource);
        $this->assertStringContainsString('PayslipFilename::make', $mailSource);
        $this->assertStringNotContainsString("'Payslip_' . \$detail->employee->employee_code", $apiSource);
        $this->assertStringNotContainsString("'Payslip_' . \$detail->employee->employee_code", $adminSource);
        $this->assertStringNotContainsString("'Payslip_'.\$this->detail->employee->employee_code", $mailSource);
    }
}
