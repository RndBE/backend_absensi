<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminSuspiciousAttendanceReviewTest extends TestCase
{
    public function test_admin_attendance_history_exposes_suspicious_review_actions(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AttendanceController.php'));
        $view = file_get_contents(resource_path('views/admin/attendance/history.blade.php'));
        $model = file_get_contents(app_path('Models/Attendance.php'));

        $this->assertStringContainsString('attendance.security-review.approve', $routes);
        $this->assertStringContainsString('attendance.security-review.reject', $routes);
        $this->assertStringContainsString('function approveSecurityReview', $controller);
        $this->assertStringContainsString('function rejectSecurityReview', $controller);
        $this->assertStringContainsString("'review_status'", $model);
        $this->assertStringContainsString('Presensi Mencurigakan', $view);
        $this->assertStringContainsString('review_status', $view);
        $this->assertStringContainsString('Butuh Review', $view);
        $this->assertStringContainsString('Approve Presensi', $view);
        $this->assertStringContainsString('Tolak Presensi', $view);
    }
}
