<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportIndexViewTest extends TestCase
{
    public function test_report_cards_keep_footer_aligned(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));

        $this->assertSame(4, substr_count($view, 'group flex h-full flex-col'));
        $this->assertSame(4, substr_count($view, 'p-5 flex-1'));
    }
}
