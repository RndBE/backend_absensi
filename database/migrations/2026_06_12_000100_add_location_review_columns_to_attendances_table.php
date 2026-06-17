<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('review_status')->nullable()->after('status');
            $table->text('suspicious_reason')->nullable()->after('review_status');
            $table->json('security_flags')->nullable()->after('suspicious_reason');
            $table->foreignId('reviewed_by')->nullable()->after('security_flags')->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'review_status',
                'suspicious_reason',
                'security_flags',
                'reviewed_at',
                'review_notes',
            ]);
        });
    }
};
