<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminRealtimeAttendanceViewTest extends TestCase
{
    public function test_realtime_attendance_selfie_thumbnail_uses_detail_offcanvas(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/realtime.blade.php'));

        $this->assertStringContainsString('onclick="openDetail({{ $att->id }})"', $view);
        $this->assertStringNotContainsString('target="_blank"', $view);
        $this->assertStringNotContainsString('href="{{ asset(\'storage/\' . $att->clock_in_photo) }}"', $view);
    }
}
