<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_indicator_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_indicator_id')->constrained('kpi_indicators')->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1–5
            $table->text('description');
            $table->timestamps();

            $table->unique(['kpi_indicator_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_indicator_rubrics');
    }
};
