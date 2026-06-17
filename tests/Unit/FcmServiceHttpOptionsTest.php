<?php

namespace Tests\Unit;

use App\Services\FcmService;
use Tests\TestCase;

class FcmServiceHttpOptionsTest extends TestCase
{
    public function test_fcm_http_options_can_disable_ssl_verification_for_local_development(): void
    {
        config([
            'services.fcm.http_verify' => false,
            'services.fcm.ca_file' => null,
        ]);

        $this->assertSame(['verify' => false], FcmService::httpOptions());
    }

    public function test_fcm_http_options_can_use_configured_ca_file(): void
    {
        config([
            'services.fcm.http_verify' => true,
            'services.fcm.ca_file' => 'storage/app/firebase_credentials.json',
        ]);

        $this->assertSame([
            'verify' => base_path('storage/app/firebase_credentials.json'),
        ], FcmService::httpOptions());
    }
}
