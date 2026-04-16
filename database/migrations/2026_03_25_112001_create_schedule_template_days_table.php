<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('schedule_templates')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1=Monday ... 7=Sunday
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_template_days');
    }
};
