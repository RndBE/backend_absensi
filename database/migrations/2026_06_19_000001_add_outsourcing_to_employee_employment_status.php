<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE employees MODIFY COLUMN employment_status ENUM('permanent','contract','intern','probation','outsourcing') NOT NULL DEFAULT 'contract'");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('employees')
            ->where('employment_status', 'outsourcing')
            ->update(['employment_status' => 'contract']);

        DB::statement("ALTER TABLE employees MODIFY COLUMN employment_status ENUM('permanent','contract','intern','probation') NOT NULL DEFAULT 'contract'");
    }
};
