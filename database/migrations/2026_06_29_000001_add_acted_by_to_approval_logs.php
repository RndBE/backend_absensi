<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_logs', function (Blueprint $table) {
            // Jika superadmin approve/reject menggantikan approver asli, approver_id tetap
            // dicatat atas nama approver asli, sedangkan acted_by_id merekam siapa yang
            // sebenarnya menekan (superadmin). Null = pelaku sama dengan approver asli.
            $table->foreignId('acted_by_id')->nullable()->after('approver_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_logs', function (Blueprint $table) {
            $table->dropForeign(['acted_by_id']);
            $table->dropColumn('acted_by_id');
        });
    }
};
