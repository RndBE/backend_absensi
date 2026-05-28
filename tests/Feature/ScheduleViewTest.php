<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScheduleViewTest extends TestCase
{
    public function test_clear_override_button_uses_shared_confirm_modal_and_has_clear_label(): void
    {
        $view = file_get_contents(resource_path('views/admin/schedules/index.blade.php'));

        $this->assertStringContainsString('form="clearForm"', $view);
        $this->assertStringContainsString('data-confirm="Hapus override? Jadwal akan kembali mengikuti template."', $view);
        $this->assertStringContainsString('Hapus Override', $view);
        $this->assertStringNotContainsString('onclick="clearAssignment()"', $view);
    }
}
