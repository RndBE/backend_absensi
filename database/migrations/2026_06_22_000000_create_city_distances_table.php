<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache permanen jarak kantor → kota tujuan, agar kota yang berulang
     * tidak memanggil API routing lagi dan bisa dikoreksi admin.
     */
    public function up(): void
    {
        Schema::create('city_distances', function (Blueprint $table) {
            $table->id();
            $table->string('city_key')->unique();          // nama kota ternormalisasi (lowercase, trim)
            $table->string('city_label');                  // nama kota seperti diinput
            $table->unsignedInteger('distance_km');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('source')->default('routing');  // routing | fallback | manual
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_distances');
    }
};
