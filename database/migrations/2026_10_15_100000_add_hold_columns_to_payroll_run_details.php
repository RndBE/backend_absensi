<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slip yang ditahan tidak terlihat oleh karyawan dan tidak ikut dikirim email
     * saat run di-publish, sementara slip lain di run yang sama tetap jalan.
     */
    public function up(): void
    {
        Schema::table('payroll_run_details', function (Blueprint $table) {
            $table->boolean('is_held')->default(false)->after('net_salary');
            $table->string('hold_reason', 500)->nullable()->after('is_held');
            $table->timestamp('held_at')->nullable()->after('hold_reason');
            $table->unsignedBigInteger('held_by')->nullable()->after('held_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_details', function (Blueprint $table) {
            $table->dropColumn(['is_held', 'hold_reason', 'held_at', 'held_by']);
        });
    }
};
