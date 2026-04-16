<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_report_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('travel_report_activity_id')->nullable()
                ->constrained('travel_report_activities')->nullOnDelete();
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->date('activity_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_report_documents');
    }
};
