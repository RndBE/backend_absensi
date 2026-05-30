<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('internship_institution')->nullable()->after('contract_end_date');
            $table->string('internship_supervisor')->nullable()->after('internship_institution');
            $table->text('internship_notes')->nullable()->after('internship_supervisor');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['internship_institution', 'internship_supervisor', 'internship_notes']);
        });
    }
};
