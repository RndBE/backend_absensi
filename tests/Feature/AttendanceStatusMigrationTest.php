<?php

namespace Tests\Feature;

use Tests\TestCase;

class AttendanceStatusMigrationTest extends TestCase
{
    public function test_attendance_status_enum_migration_allows_sick_status(): void
    {
        $migrationFiles = glob(database_path('migrations/*_add_sick_status_to_attendances_status_enum.php'));

        $this->assertNotEmpty($migrationFiles);

        $migration = file_get_contents($migrationFiles[0]);

        $this->assertStringContainsString("'present','absent','sick','leave','holiday'", $migration);
        $this->assertStringContainsString("where('status', 'sick')", $migration);
    }

    public function test_attendance_status_enum_migration_allows_partial_day_permission_statuses(): void
    {
        $migrationFiles = glob(database_path('migrations/*_add_partial_day_permission_statuses_to_attendances_status_enum.php'));

        $this->assertNotEmpty($migrationFiles);

        $migration = file_get_contents($migrationFiles[0]);

        $this->assertStringContainsString("'present','absent','sick','leave','holiday','late_excuse','early_departure'", $migration);
        $this->assertStringContainsString("whereIn('status', ['late_excuse', 'early_departure'])", $migration);
    }
}
