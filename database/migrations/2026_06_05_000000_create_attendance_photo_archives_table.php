<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_photo_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->string('status')->default('pending');
            $table->string('zip_file_name')->nullable();
            $table->string('zip_file_path')->nullable();
            $table->unsignedInteger('photo_count')->default(0);
            $table->json('photo_paths')->nullable();
            $table->text('drive_link')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('local_photos_deleted_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_photo_archives');
    }
};
