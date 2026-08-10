<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Matikan jembatan DailyCloseApp secara default.
        //
        // Tidak ada .env.testing di repo ini, jadi test membaca .env biasa — yang di
        // mesin dev berisi DAILY_APP_URL produksi beserta secret-nya. Tanpa ini, test
        // apa pun yang meng-approve cuti akan memicu SyncLeaveToDailyJob mengirim HTTP
        // sungguhan ke Daily produksi (QUEUE_CONNECTION=sync -> job jalan inline).
        //
        // Test yang memang menguji jembatannya meng-set config ini sendiri.
        config([
            'services.daily.url' => null,
            'services.daily.internal_secret' => null,
        ]);
    }
}
