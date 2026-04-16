<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');             // e.g. "Pagi", "Siang", "Malam", "Off"
            $table->time('start_time')->nullable(); // null for Off/Libur
            $table->time('end_time')->nullable();
            $table->string('color', 7)->default('#3B82F6'); // hex color
            $table->boolean('is_off')->default(false);       // day off marker
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
