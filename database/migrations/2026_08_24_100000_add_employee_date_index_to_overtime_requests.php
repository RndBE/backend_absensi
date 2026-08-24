<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks (employee_id, date) untuk overtime_requests.
 *
 * Dibutuhkan dua pihak sekaligus:
 * - Guard duplikat di OvertimeController mencari "lembur karyawan ini di tanggal ini" pada
 *   setiap penyimpanan. Tanpa indeks gabungan, MySQL menyaring seluruh baris milik karyawan
 *   itu satu per satu.
 * - Perhitungan gaji (PayrollRunController) menjalankan employee_id + whereBetween(date)
 *   untuk SETIAP karyawan pada setiap penggajian.
 *
 * Sebelumnya yang ada hanya indeks foreign key employee_id saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('overtime_requests')) {
            return;
        }

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->index(['employee_id', 'date'], 'overtime_requests_employee_date_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('overtime_requests')) {
            return;
        }

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropIndex('overtime_requests_employee_date_index');
        });
    }
};
