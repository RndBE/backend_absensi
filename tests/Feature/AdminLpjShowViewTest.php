<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLpjShowViewTest extends TestCase
{
    public function test_lpj_detail_actions_and_notes_are_visually_separated(): void
    {
        $view = file_get_contents(resource_path('views/admin/lpj/show.blade.php'));

        $this->assertStringContainsString('admin-lpj-actions', $view);
        $this->assertStringContainsString('from-sky-600 to-blue-600', $view);
        $this->assertStringContainsString('from-emerald-600 to-emerald-500', $view);
        $this->assertStringContainsString('from-red-600 to-red-500', $view);
        $this->assertStringContainsString('from-slate-600 to-slate-500', $view);

        $this->assertStringContainsString('admin-lpj-note-cell', $view);
        $this->assertStringContainsString('flex items-start justify-between gap-3', $view);
        $this->assertStringContainsString('order-last', $view);
        $this->assertStringContainsString('break-words leading-relaxed', $view);
    }
}
