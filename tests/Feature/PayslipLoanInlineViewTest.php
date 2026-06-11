<?php

namespace Tests\Feature;

use Tests\TestCase;

class PayslipLoanInlineViewTest extends TestCase
{
    public function test_web_payslip_renders_loan_details_inside_deduction_rows(): void
    {
        $source = file_get_contents(resource_path('views/admin/payslips/show.blade.php'));

        $this->assertStringContainsString('PayslipLoanSummary::detailLinesForComponent', $source);
        $this->assertStringContainsString('<table class="w-full text-[12px]', $source);
        $this->assertStringContainsString('text-[10.5px]', $source);
        $this->assertStringNotContainsString('payslip-section-list', $source);
        $this->assertStringNotContainsString('Ringkasan Kasbon', $source);
    }

    public function test_pdf_payslip_renders_loan_details_inside_deduction_rows(): void
    {
        $source = file_get_contents(resource_path('views/admin/payslips/pdf.blade.php'));

        $this->assertStringContainsString('PayslipLoanSummary::detailLinesForComponent', $source);
        $this->assertStringContainsString('main-tbl', $source);
        $this->assertStringContainsString('loan-detail', $source);
        $this->assertStringNotContainsString('section-list', $source);
        $this->assertStringNotContainsString('Ringkasan Kasbon', $source);
    }
}
