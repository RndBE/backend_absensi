<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_zones', function (Blueprint $table) {
            $table->id();
            $table->integer('zone')->unique();
            $table->string('name');
            $table->decimal('meal_allowance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_zones');
    }
};
