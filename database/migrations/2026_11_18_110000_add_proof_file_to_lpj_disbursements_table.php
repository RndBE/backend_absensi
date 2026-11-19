<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lpj_disbursements') || Schema::hasColumn('lpj_disbursements', 'proof_file')) {
            return;
        }

        Schema::table('lpj_disbursements', function (Blueprint $table) {
            // Bukti transfer (foto/PDF) untuk baris ini, di disk public.
            $table->string('proof_file')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lpj_disbursements') || ! Schema::hasColumn('lpj_disbursements', 'proof_file')) {
            return;
        }

        Schema::table('lpj_disbursements', function (Blueprint $table) {
            $table->dropColumn('proof_file');
        });
    }
};
