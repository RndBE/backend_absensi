<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaxCertificateViewTest extends TestCase
{
    public function test_bukti_potong_list_exposes_review_finalize_download_and_error_states(): void
    {
        $view = file_get_contents(resource_path('views/admin/tax/bukti-potong.blade.php'));
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("session('error')", $view);
        $this->assertStringContainsString('admin.tax.show-bukti-potong', $view);
        $this->assertStringContainsString('admin.tax.finalize-bukti-potong', $view);
        $this->assertStringContainsString('admin.tax.download-bukti-potong', $view);
        $this->assertStringContainsString('Review', $view);
        $this->assertStringContainsString('Finalisasi', $view);
        $this->assertStringContainsString('/tax/bukti-potong/{id}', $routes);
        $this->assertStringContainsString('/tax/bukti-potong/{id}/download', $routes);
        $this->assertStringContainsString('/tax/bukti-potong/{id}/finalize', $routes);
    }
}
