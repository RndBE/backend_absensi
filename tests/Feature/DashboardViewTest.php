<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardViewTest extends TestCase
{
    public function test_dashboard_does_not_render_payroll_summary_widget(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringNotContainsString('$canViewPayrollSummary', $view);
        $this->assertStringNotContainsString("summary['payroll']", $view);
    }

    public function test_dashboard_uses_compact_stats_overview_cards(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringNotContainsString('$statCards = [', $view);
        $this->assertSame(5, substr_count($view, 'stat-border-'));
        $this->assertStringContainsString('grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-7', $view);
        $this->assertStringContainsString('grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-7', $view);
        $this->assertStringContainsString('stat-border-blue', $view);
        $this->assertStringContainsString('stat-border-green', $view);
        $this->assertStringContainsString('stat-border-yellow', $view);
        $this->assertStringContainsString('stat-border-red', $view);
        $this->assertStringContainsString('stat-border-purple', $view);
        $this->assertStringContainsString('text-[28px] font-extrabold', $view);
        $this->assertStringContainsString('text-[24px] font-extrabold', $view);
        $this->assertStringContainsString('Menunggu Persetujuan', $view);
        $this->assertStringContainsString('Terlambat Bulan Ini', $view);
        $this->assertStringContainsString('Cuti Pending', $view);
        $this->assertStringContainsString('Lembur Pending', $view);
        $this->assertStringContainsString('Presensi Pending', $view);
        $this->assertStringContainsString('Resign Bulan Ini', $view);
        $this->assertStringContainsString('$lateThisMonth', $view);
        $this->assertStringContainsString('$resignedThisMonth', $view);
    }

    public function test_dashboard_stat_cards_open_person_detail_modal(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertSame(10, substr_count($view, '<button type="button" data-dashboard-detail-trigger'));
        $this->assertStringContainsString('data-detail-key="total_employees"', $view);
        $this->assertStringContainsString('data-detail-key="present_today"', $view);
        $this->assertStringContainsString('data-detail-key="late_today"', $view);
        $this->assertStringContainsString('data-detail-key="absent_today"', $view);
        $this->assertStringContainsString('data-detail-key="total_pending"', $view);
        $this->assertStringContainsString('data-detail-key="late_this_month"', $view);
        $this->assertStringContainsString('data-detail-key="pending_leave"', $view);
        $this->assertStringContainsString('data-detail-key="pending_overtime"', $view);
        $this->assertStringContainsString('data-detail-key="pending_attendance"', $view);
        $this->assertStringContainsString('data-detail-key="resigned_this_month"', $view);
        $this->assertStringContainsString('id="dashboardDetailModal"', $view);
        $this->assertStringContainsString('@js($dashboardDetails)', $view);
    }

    public function test_dashboard_uses_contract_table_layout(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString('Kontrak Hampir Habis', $view);
        $this->assertStringContainsString('bg-white rounded-xl border border-gray-200 shadow-sm', $view);
        $this->assertStringContainsString('text-[15px] font-bold text-gray-900', $view);
        $this->assertStringContainsString('text-[11px] font-bold uppercase tracking-wider', $view);
        $this->assertStringContainsString('$contractsEndingSoon', $view);
        $this->assertStringContainsString('$contractsEndingSoonCount', $view);
        $this->assertStringContainsString('Tidak ada kontrak yang habis dalam 60 hari ke depan', $view);
        $this->assertStringNotContainsString('Ringkasan Approval', $view);
        $this->assertStringNotContainsString('Ringkasan HR', $view);
    }

    public function test_dashboard_recent_request_marks_in_review_as_diproses_not_ditolak(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString("\$request['status'] === 'in_review'", $view);
        $this->assertStringContainsString('Diproses', $view);
    }
}
