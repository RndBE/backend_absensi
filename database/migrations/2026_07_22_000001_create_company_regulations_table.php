<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_regulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('content')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_regulations');
    }
};
