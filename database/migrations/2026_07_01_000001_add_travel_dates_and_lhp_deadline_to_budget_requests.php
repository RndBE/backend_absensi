<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            // Tanggal berangkat/pulang perjalanan dinas. Tanggal pulang jadi acuan
            // batas pengumpulan LHP (dan reminder LHP). Nullable agar pengajuan
            // non-perjalanan (budget/reimbursement biasa) tetap boleh kosong.
            $table->date('departure_date')->nullable()->after('travel_zone_id');
            $table->date('return_date')->nullable()->after('departure_date');

            // Override batas pengumpulan LHP (hari kerja) — keringanan dari HR.
            // Null = ikut default global (Setting: lhp_deadline_working_days, default 5).
            $table->unsignedTinyInteger('lhp_deadline_days')->nullable()->after('return_date');
        });
    }

    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->dropColumn(['departure_date', 'return_date', 'lhp_deadline_days']);
        });
    }
};
