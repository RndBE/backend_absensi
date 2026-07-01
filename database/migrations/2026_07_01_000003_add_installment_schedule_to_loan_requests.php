<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            // Override cicilan per bulan tertentu: { "2026-06": 500000, "2026-07": 800000 }.
            // Bulan yang tidak terdaftar tetap memakai monthly_installment (default).
            $table->json('installment_schedule')->nullable()->after('monthly_installment');
        });
    }

    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropColumn('installment_schedule');
        });
    }
};
