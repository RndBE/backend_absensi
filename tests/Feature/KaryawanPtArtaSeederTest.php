<?php

namespace Tests\Feature;

use Tests\TestCase;

class KaryawanPtArtaSeederTest extends TestCase
{
    public function test_karyawan_pt_arta_seeder_ensures_leave_types_and_annual_policy(): void
    {
        $seeder = file_get_contents(database_path('seeders/KaryawanPtArtaSeeder.php'));

        $this->assertStringContainsString("LeaveType::updateOrCreate", $seeder);
        $this->assertStringContainsString("'Cuti Tahunan' => 12", $seeder);
        $this->assertStringContainsString("'Cuti Sakit' => 14", $seeder);
        $this->assertStringContainsString("'Izin Datang Terlambat' => 365", $seeder);
        $this->assertStringContainsString("'Cuti Melahirkan' => 90", $seeder);
        $this->assertStringContainsString("LeavePolicy::updateOrCreate", $seeder);
    }
}
