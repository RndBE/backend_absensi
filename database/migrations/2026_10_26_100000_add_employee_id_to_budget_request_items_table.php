<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemilik baris biaya. Kosong = biaya bersama (bensin, tol, penginapan bareng) yang
     * dilaporkan pemilik anggaran; terisi = biaya pribadi seorang peserta (uang makan,
     * tunjangan) yang dilaporkan peserta itu di LPJ-nya sendiri. Baris lama semuanya kosong,
     * jadi tetap masuk LPJ pemilik anggaran seperti sebelumnya.
     */
    public function up(): void
    {
        Schema::table('budget_request_items', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('budget_request_id')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
