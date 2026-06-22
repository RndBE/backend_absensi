<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','sick','leave','holiday') NOT NULL DEFAULT 'present'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('attendances')->where('status', 'sick')->update(['status' => 'absent']);
        DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','leave','holiday') NOT NULL DEFAULT 'present'");
    }
};
