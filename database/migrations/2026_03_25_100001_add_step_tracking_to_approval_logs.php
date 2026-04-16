<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_logs', function (Blueprint $table) {
            $table->integer('step_order')->default(1)->after('action');
            $table->foreignId('approval_rule_id')->nullable()->after('step_order')->constrained('approval_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_logs', function (Blueprint $table) {
            $table->dropForeign(['approval_rule_id']);
            $table->dropColumn(['step_order', 'approval_rule_id']);
        });
    }
};
