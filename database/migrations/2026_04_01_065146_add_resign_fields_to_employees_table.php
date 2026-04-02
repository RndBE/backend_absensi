<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('resign_reason')->nullable()->after('resign_date');  // voluntary / termination / contract_end / retirement / passed_away
            $table->text('resign_notes')->nullable()->after('resign_reason');
            $table->date('last_working_date')->nullable()->after('resign_notes');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['resign_reason', 'resign_notes', 'last_working_date']);
        });
    }
};
