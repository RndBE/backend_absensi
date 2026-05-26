<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    public function test_application_uses_wib_timezone_by_default(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }
}
