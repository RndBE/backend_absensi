<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('budget_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->string('destination_city');
            $table->date('departure_date');
            $table->date('return_date');
            $table->text('purpose');
            $table->text('conclusion')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('status')->default('pending'); // pending, in_review, approved, rejected
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_reports');
    }
};
