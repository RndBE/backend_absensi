<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil pindai surat tugas yang sudah ditandatangani & diparaf basah. Diunggah setelah surat
 * terbit dan disimpan di disk privat (storage/app/private), dibuka lewat controller supaya
 * hanya admin berizin dan peserta surat yang bisa melihatnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->string('signed_file_path')->nullable()->after('cancel_reason');
            $table->string('signed_file_name')->nullable()->after('signed_file_path');
            $table->timestamp('signed_uploaded_at')->nullable()->after('signed_file_name');
            $table->foreignId('signed_uploaded_by')->nullable()->after('signed_uploaded_at')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_uploaded_by');
            $table->dropColumn(['signed_file_path', 'signed_file_name', 'signed_uploaded_at']);
        });
    }
};
