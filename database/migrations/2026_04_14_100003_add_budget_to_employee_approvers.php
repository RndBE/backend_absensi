<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores Laravel enum columns as text, so no schema change is needed there.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE employee_approvers MODIFY COLUMN request_type ENUM('leave','overtime','attendance','budget')");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE employee_approvers MODIFY COLUMN request_type ENUM('leave','overtime','attendance')");
        }
    }
};
