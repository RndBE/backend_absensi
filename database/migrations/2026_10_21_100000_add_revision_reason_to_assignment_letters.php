<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Alasan surat tugas perbaikan dibuat (catatan internal, tidak tercetak). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->string('revision_reason', 500)->nullable()->after('revises_letter_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->dropColumn('revision_reason');
        });
    }
};
