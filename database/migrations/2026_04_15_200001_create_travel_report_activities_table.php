<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_report_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_report_id')->constrained()->onDelete('cascade');
            $table->date('activity_date');
            $table->text('description');
            $table->json('results')->nullable();
            $table->text('issues')->nullable();
            $table->text('conclusion')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_report_activities');
    }
};
