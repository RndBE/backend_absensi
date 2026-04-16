<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('npwp_15', 25)->nullable()->after('nik');
            $table->string('npwp_16', 25)->nullable()->after('npwp_15');
            $table->string('ptkp', 10)->nullable()->after('npwp_16');
            $table->string('bpjs_tk', 30)->nullable()->after('ptkp');
            $table->string('bpjs_kesehatan', 30)->nullable()->after('bpjs_tk');
            $table->string('bank_account', 30)->nullable()->after('bpjs_kesehatan');
            $table->string('bank_name', 50)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'npwp_15', 'npwp_16', 'ptkp',
                'bpjs_tk', 'bpjs_kesehatan',
                'bank_account', 'bank_name',
            ]);
        });
    }
};
