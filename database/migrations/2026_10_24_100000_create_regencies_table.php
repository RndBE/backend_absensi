<?php

use App\Models\Regency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master kabupaten/kota untuk kota tujuan perjalanan. Jarak jalan kantor → ibukota
     * kabupaten/kota dihitung sekali lalu disimpan di sini, jadi "Kendal", "Kab. Kendal", dan
     * "Kendal, Jawa Tengah" selalu mendapat jarak dan zona yang sama.
     */
    public function up(): void
    {
        Schema::create('regencies', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary(); // kode wilayah BPS/Kemendagri, mis. 3324
            $table->string('type', 10);                    // kabupaten | kota
            $table->string('name');                        // tanpa awalan, mis. "Kendal"
            $table->string('province');
            $table->string('capital');                     // ibukota; untuk kota = kota itu sendiri
            $table->decimal('lat', 10, 7)->nullable();     // titik ibukota
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_km')->nullable(); // jarak jalan kantor → ibukota
            $table->string('distance_source')->nullable();      // routing | manual
            $table->timestamps();
            $table->index('name');
        });

        // Diisi di sini, bukan di seeder, supaya tiap database tenant ikut terisi saat migrate.
        foreach (array_chunk(Regency::masterRows(), 200) as $chunk) {
            DB::table('regencies')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regencies');
    }
};
