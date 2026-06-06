<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminFaviconViewTest extends TestCase
{
    public function test_admin_pages_use_title_ico_favicon(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $login = file_get_contents(resource_path('views/admin/auth/login.blade.php'));

        $this->assertStringContainsString("asset('images/title.ico')", $layout);
        $this->assertStringContainsString('rel="icon"', $layout);
        $this->assertStringContainsString("asset('images/title.ico')", $login);
        $this->assertStringContainsString('rel="icon"', $login);
    }
}
