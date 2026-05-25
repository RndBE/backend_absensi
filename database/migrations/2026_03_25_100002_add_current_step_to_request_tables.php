<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add current_step to all request tables
        $tables = ['leave_requests', 'overtime_requests', 'attendance_requests', 'data_change_requests'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('current_step')->default(1)->after('status');
            });
        }

        // SQLite stores Laravel enum columns as text, so no schema change is needed there.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach ($tables as $tableName) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `status` ENUM('pending','in_review','approved','rejected') NOT NULL DEFAULT 'pending'");
            }
        }
    }

    public function down(): void
    {
        $tables = ['leave_requests', 'overtime_requests', 'attendance_requests', 'data_change_requests'];

        foreach ($tables as $tableName) {
            // Change any 'in_review' back to 'pending' before modifying enum
            DB::table($tableName)->where('status', 'in_review')->update(['status' => 'pending']);

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('current_step');
            });
        }
    }
};
