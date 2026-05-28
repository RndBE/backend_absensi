<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScheduleTemplateViewTest extends TestCase
{
    public function test_edit_template_form_does_not_wrap_delete_template_form(): void
    {
        $view = file_get_contents(resource_path('views/admin/schedule-templates/index.blade.php'));

        $nestedFormPattern = '/<form\s+action="\{\{\s*route\(\'admin\.schedule-templates\.update\',\s*\$template->id\)\s*\}\}".*?<form\s+action="\{\{\s*route\(\'admin\.schedule-templates\.destroy\',\s*\$template->id\)\s*\}\}"/s';

        $this->assertDoesNotMatchRegularExpression($nestedFormPattern, $view);
        $this->assertStringContainsString('form="editTemplateForm-{{ $template->id }}"', $view);
    }

    public function test_template_cards_do_not_clip_shift_dropdowns(): void
    {
        $view = file_get_contents(resource_path('views/admin/schedule-templates/index.blade.php'));

        $this->assertStringContainsString('overflow-visible', $view);
        $this->assertStringNotContainsString('mb-5 overflow-hidden hover:shadow-sm', $view);
    }
}
