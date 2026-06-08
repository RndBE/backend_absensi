<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminMonitorApprovalDurationFormatTest extends TestCase
{
    public function test_monitor_approval_durations_do_not_force_decimal_zeroes(): void
    {
        $view = file_get_contents(resource_path('views/admin/monitor-approvals/index.blade.php'));

        $this->assertStringContainsString('$formatDurationValue', $view);
        $this->assertStringContainsString('$formatDurationValue($item->total_days)', $view);
        $this->assertStringContainsString('$formatDurationValue(($item->total_duration ?? 0) / 60)', $view);
        $this->assertStringNotContainsString('$item->total_days . \' hari\'', $view);
        $this->assertStringNotContainsString('number_format(($item->total_duration ?? 0) / 60, 1)', $view);
    }
}
