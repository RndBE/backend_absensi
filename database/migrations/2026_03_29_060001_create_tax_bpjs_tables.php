<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tax settings (PPh 21 brackets, PTKP, biaya jabatan)
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key'); // pph21_brackets, ptkp_values, biaya_jabatan_pct, etc
            $table->json('value');
            $table->date('effective_date');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'effective_date']);
        });

        // BPJS settings with rate versioning
        Schema::create('bpjs_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key'); // kes_rate, jht_rate, jkk_rate, jkm_rate, jp_rate, kes_cap, jp_cap
            $table->json('value'); // {company: x, employee: y} or single value
            $table->date('effective_date');
            $table->string('npp')->nullable()->comment('NPP BPJS per cabang');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'effective_date']);
        });

        // Bukti potong annual
        Schema::create('tax_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->integer('tax_year');
            $table->string('certificate_number')->nullable();
            $table->decimal('gross_annual', 15, 2)->default(0);
            $table->decimal('tax_annual', 15, 2)->default(0);
            $table->decimal('bpjs_annual', 15, 2)->default(0);
            $table->decimal('nett_annual', 15, 2)->default(0);
            $table->json('monthly_details')->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();

            $table->unique(['employee_id', 'tax_year']);
        });

        // Add tax_method to employee_payrolls
        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->enum('tax_method', ['gross', 'gross_up', 'nett'])->default('gross_up')->after('overtime_multiplier');
            $table->boolean('pph21_dtp')->default(false)->after('tax_method')->comment('PPh 21 ditanggung pemerintah');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->dropColumn(['tax_method', 'pph21_dtp']);
        });
        Schema::dropIfExists('tax_certificates');
        Schema::dropIfExists('bpjs_settings');
        Schema::dropIfExists('tax_settings');
    }
};
