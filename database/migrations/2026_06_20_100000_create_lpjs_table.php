<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lpjs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('travel_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('nomor_lpj')->nullable();
            $table->decimal('total_anggaran', 15, 2)->default(0);
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->decimal('sisa', 15, 2)->default(0);
            $table->enum('status', ['draft', 'pending', 'in_review', 'approved', 'rejected'])->default('draft');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('rejection_reason')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('lpj_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lpj_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('uraian');
            $table->string('satuan')->nullable();
            $table->decimal('volume', 10, 2)->default(1);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('anggaran', 15, 2)->default(0);
            $table->decimal('realisasi', 15, 2)->default(0);
            $table->string('bukti_file')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpj_items');
        Schema::dropIfExists('lpjs');
    }
};
