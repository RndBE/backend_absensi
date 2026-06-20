<?php

namespace Tests\Unit;

use App\Models\LeaveRequest;
use PHPUnit\Framework\TestCase;

class LeaveRequestTotalDaysLabelTest extends TestCase
{
    public function test_total_days_label_removes_only_trailing_zero_decimal(): void
    {
        $this->assertSame('1', $this->leaveDaysLabel(1));
        $this->assertSame('0.5', $this->leaveDaysLabel(0.5));
        $this->assertSame('1.5', $this->leaveDaysLabel(1.5));
    }

    private function leaveDaysLabel(float $days): string
    {
        return (new LeaveRequest(['total_days' => $days]))->total_days_label;
    }
}
