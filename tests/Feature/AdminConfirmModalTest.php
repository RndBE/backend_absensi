<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class AdminConfirmModalTest extends TestCase
{
    public function test_admin_layout_provides_shared_confirm_modal(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

        $this->assertStringContainsString('id="confirmActionModal"', $layout);
        $this->assertStringContainsString('function openConfirmModal', $layout);
        $this->assertStringContainsString('data-confirm', $layout);
    }

    public function test_admin_confirm_modal_does_not_pass_form_as_request_submitter(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

        $this->assertStringContainsString('validSubmitter', $layout);
        $this->assertStringNotContainsString('form.requestSubmit(submitter);', $layout);
    }

    public function test_admin_confirm_modal_stays_above_nested_modals(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

        $this->assertStringContainsString('id="confirmActionModal"', $layout);
        $this->assertStringContainsString('style="z-index: 1000;"', $layout);
    }

    public function test_admin_layout_displays_validation_errors(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

        $this->assertStringContainsString('$errors->any()', $layout);
        $this->assertStringContainsString('$errors->all()', $layout);
        $this->assertStringContainsString('Data belum dapat disimpan.', $layout);
    }

    public function test_form_level_confirm_only_runs_on_submit_not_inner_clicks(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

        $this->assertStringContainsString("if (trigger.matches('form')) return;", $layout);
    }

    public function test_payroll_regenerate_confirmation_explains_manual_edits_are_preserved(): void
    {
        $view = file_get_contents(resource_path('views/admin/payroll-runs/show.blade.php'));

        $this->assertStringContainsString(
            'Regenerate data otomatis? Perubahan manual tetap dipertahankan.',
            $view
        );
    }

    public function test_admin_views_do_not_use_browser_confirm_dialogs(): void
    {
        $views = collect(glob(resource_path('views/admin/**/*.blade.php'), GLOB_BRACE))
            ->merge(glob(resource_path('views/admin/*.blade.php'), GLOB_BRACE));

        $offenders = $views
            ->filter(fn ($path) => Str::contains(file_get_contents($path), 'confirm('))
            ->map(fn ($path) => str_replace(resource_path('views/admin').DIRECTORY_SEPARATOR, '', $path))
            ->values()
            ->all();

        $this->assertSame([], $offenders);
    }
}
