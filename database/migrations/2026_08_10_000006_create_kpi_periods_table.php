<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `status` sengaja string, bukan enum. Alur Bab 9.3 punya enam tahap dan hampir pasti
     * bertambah; repo ini sudah membayar empat migration hanya untuk melebarkan enum
     * (status absensi, request_type approver). Daftar nilainya dijaga konstanta di model.
     */
    public function up(): void
    {
        Schema::create('kpi_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // mis. "Semester I 2026"

            // Rentang kinerja yang dinilai — bukan rentang pengisian.
            $table->date('start_date');
            $table->date('end_date');

            // Jendela pengisian penilaian silang (Bab 9.3, 10 hari kerja).
            $table->date('cross_fill_start')->nullable();
            $table->date('cross_fill_end')->nullable();

            // Jendela pengisian penilaian atasan langsung.
            $table->date('fill_start')->nullable();
            $table->date('fill_end')->nullable();

            $table->string('status', 20)->default('draft');

            // Periode uji coba: skor dihitung tapi tidak dipakai untuk konsekuensi apa pun (Bab 11.1).
            $table->boolean('is_trial')->default(false);

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_periods');
    }
};
