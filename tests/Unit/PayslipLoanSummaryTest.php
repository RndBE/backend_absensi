<?php

namespace Tests\Unit;

use App\Support\PayslipLoanSummary;
use PHPUnit\Framework\TestCase;

class PayslipLoanSummaryTest extends TestCase
{
    public function test_builds_loan_summary_from_kasbon_deduction_component(): void
    {
        $summary = PayslipLoanSummary::fromComponents([
            [
                'type' => 'deduction',
                'name' => 'Kasbon Karyawan',
                'amount' => 250000,
                'loan' => [
                    'principal_amount' => 1000000,
                    'interest_rate' => 2.5,
                    'interest_amount' => 25000,
                    'total_repayable' => 1025000,
                    'installment_number' => 2,
                    'installment_count' => 4,
                    'paid_amount' => 500000,
                    'remaining_amount' => 500000,
                    'status' => 'berjalan',
                ],
            ],
        ]);

        $this->assertSame(1000000.0, $summary['principal_amount']);
        $this->assertSame(2.5, $summary['interest_rate']);
        $this->assertSame(25000.0, $summary['interest_amount']);
        $this->assertSame(1025000.0, $summary['total_repayable']);
        $this->assertSame(250000.0, $summary['current_deduction']);
        $this->assertSame(2, $summary['installment_number']);
        $this->assertSame(4, $summary['installment_count']);
        $this->assertSame(500000.0, $summary['paid_amount']);
        $this->assertSame(500000.0, $summary['remaining_amount']);
        $this->assertSame('berjalan', $summary['status']);
    }

    public function test_builds_inline_detail_lines_for_loan_deduction_component(): void
    {
        $lines = PayslipLoanSummary::detailLinesForComponent([
            'type' => 'deduction',
            'name' => 'Potongan Pinjaman A',
            'amount' => 500000,
            'loan' => [
                'interest_rate' => 5,
                'interest_amount' => 250000,
                'installment_number' => 8,
                'installment_count' => 10,
                'remaining_amount' => 2500000,
            ],
        ]);

        $this->assertSame([
            'cicilan ke 8 dari 10',
            'bunga 5% Rp250.000',
            'sisa pinjaman Rp2.500.000',
        ], $lines);
    }

    public function test_does_not_build_inline_detail_lines_for_non_loan_deduction(): void
    {
        $this->assertSame([], PayslipLoanSummary::detailLinesForComponent([
            'type' => 'deduction',
            'name' => 'PPh 21',
            'amount' => 500000,
        ]));
    }
}
