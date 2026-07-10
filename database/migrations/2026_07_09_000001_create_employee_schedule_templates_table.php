<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat template jadwal per karyawan, dengan TANGGAL BERLAKU.
 *
 * `employees.schedule_template_id` hanya satu kolom tanpa dimensi waktu, sehingga template
 * yang terpasang sekarang berlaku surut ke seluruh masa lalu karyawan — membuat rekap dan
 * potongan alpha salah ketika jadwal seseorang pernah berubah.
 *
 * Template yang berlaku pada tanggal X = baris dengan `effective_from` TERBESAR yang <= X.
 * Tidak ada `effective_to`: batas atas otomatis dari baris berikutnya, sehingga celah dan
 * tumpang-tindih tidak mungkin terjadi.
 *
 * `employees.schedule_template_id` DIPERTAHANKAN sebagai penunjuk "template yang berlaku
 * sekarang", agar kode yang hanya menampilkan jadwal hari ini tidak perlu berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // NULL = "sejak tanggal ini karyawan TIDAK bertemplate". Tanpa ini, melepas template
            // dari seorang karyawan tidak bisa direkam, dan riwayat lama akan terus berlaku.
            $table->foreignId('template_id')->nullable()->constrained('schedule_templates')->cascadeOnDelete();
            $table->date('effective_from');
            $table->timestamps();

            // Resolusi selalu "baris terbaru yang effective_from <= tanggal".
            $table->index(['employee_id', 'effective_from']);
            $table->unique(['employee_id', 'effective_from']);
        });

        // Backfill: satu baris per karyawan bertemplate, berlaku sejak tanggal masuk.
        // Ini MEMPERTAHANKAN perilaku yang ada sekarang — belum mengoreksi siapa pun.
        // Karyawan tanpa join_date memakai tanggal lantai agar tetap tertangkap resolver.
        $rows = DB::table('employees')
            ->whereNotNull('schedule_template_id')
            ->select('id', 'schedule_template_id', 'join_date')
            ->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('employee_schedule_templates')->insert([
                'employee_id' => $row->id,
                'template_id' => $row->schedule_template_id,
                'effective_from' => $row->join_date ?: '1970-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedule_templates');
    }
};
