<?php

namespace Tests\Feature;

use App\Models\BpjsSetting;
use App\Models\Employee;
use App\Models\TaxSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_creates_admin_and_tax_bpjs_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Employee::where('role', 'superadmin')->exists());
        $this->assertGreaterThan(0, TaxSetting::where('is_active', true)->count());
        $this->assertGreaterThan(0, BpjsSetting::where('is_active', true)->count());
    }
}
