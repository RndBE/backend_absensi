<?php

namespace Tests\Feature;

use Tests\TestCase;

class AttendancePhotoArchiveViewTest extends TestCase
{
    public function test_admin_navigation_exposes_attendance_photo_archive_menu(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $permissions = config('admin_permissions.route_permissions');

        $this->assertStringContainsString("'route' => 'admin.attendance-photo-archives.index'", $layout);
        $this->assertStringContainsString("'label' => 'Arsip Foto Absensi'", $layout);
        $this->assertSame('attendance.manage', $permissions['admin.attendance-photo-archives.*']);
    }

    public function test_attendance_photo_archive_page_has_manual_drive_workflow(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance-photo-archives/index.blade.php'));

        $this->assertStringContainsString('Generate ZIP', $view);
        $this->assertStringContainsString('Download ZIP', $view);
        $this->assertStringContainsString('Tandai Sudah Upload ke Drive', $view);
        $this->assertStringContainsString('Link Google Drive ZIP', $view);
        $this->assertStringContainsString('Tempel link file ZIP dari Google Drive', $view);
        $this->assertStringContainsString('Simpan Link Drive & Hapus Foto Lokal', $view);
        $this->assertStringNotContainsString('bg-amber-600', $view);
        $this->assertStringNotContainsString('border-2 border-blue-300', $view);
        $this->assertStringContainsString('border-2 border-gray-400', $view);
        $this->assertStringContainsString('bg-slate-50 border border-slate-200', $view);
        $this->assertStringContainsString("can(\$currentAdmin, 'attendance.manage')", $view);
        $this->assertStringNotContainsString("@if(\$canManage)\n                                            <button type=\"button\" onclick=\"openUploadModal", $view);
    }

    public function test_attendance_detail_views_guard_archived_photo_paths(): void
    {
        foreach ([
            resource_path('views/admin/attendance/history.blade.php'),
            resource_path('views/admin/attendance-recap/index.blade.php'),
            resource_path('views/admin/attendance-recap/employee-detail.blade.php'),
        ] as $path) {
            $view = file_get_contents($path);

            $this->assertStringContainsString("Storage::disk('public')->exists", $view, "{$path} must check local photo existence.");
            $this->assertStringContainsString('Foto sudah diarsipkan', $view, "{$path} must explain missing archived photos.");
        }
    }
}
