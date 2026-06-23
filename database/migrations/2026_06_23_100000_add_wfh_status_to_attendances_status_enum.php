<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','sick','leave','holiday','late_excuse','early_departure','wfh') NOT NULL DEFAULT 'present'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('attendances')
            ->where('status', 'wfh')
            ->update(['status' => 'present']);

        DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','sick','leave','holiday','late_excuse','early_departure') NOT NULL DEFAULT 'present'");
    }
};
