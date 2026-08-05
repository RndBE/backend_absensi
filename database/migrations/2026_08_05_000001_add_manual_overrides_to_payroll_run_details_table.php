<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_details', function (Blueprint $table) {
            $table->json('manual_overrides')->nullable()->after('is_manual_edited');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_details', function (Blueprint $table) {
            $table->dropColumn('manual_overrides');
        });
    }
};
