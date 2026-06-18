<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminAttendanceHistoryViewTest extends TestCase
{
    public function test_attendance_history_pagination_is_right_aligned(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance/history.blade.php'));

        $this->assertStringContainsString('flex justify-end pt-4', $view);
        $this->assertStringNotContainsString('flex justify-center pt-4', $view);
    }
}
