<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat Tugas beserta master kendaraan & perangkat komunikasi (HT) yang dipakai di dalamnya.
 *
 * Data karyawan, penanda tangan, dan kolom paraf disalin ke surat saat diterbitkan. Nomor
 * karyawan dan jabatan bisa berubah karena mutasi; surat yang sudah dibagikan tidak boleh
 * ikut berubah saat dicetak ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('plate_number', 20)->nullable();
            $table->string('ownership', 20)->default('company');
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Penghitung nomor dokumen per perusahaan, jenis, dan tahun. Baris dikunci
        // (SELECT ... FOR UPDATE) saat nomor diambil, jadi dua penerbitan bersamaan
        // tidak mendapat nomor yang sama.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'type', 'year']);
        });

        Schema::create('assignment_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Kosong selama draft; diisi saat diterbitkan.
            $table->string('number', 50)->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->unsignedSmallInteger('sequence_year')->nullable();
            $table->date('letter_date');
            $table->string('city', 100);
            $table->foreignId('signer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('signer_name');
            $table->string('signer_position');
            // Kalimat pembuka bagian "Untuk ...", mis. "melaksanakan beberapa pekerjaan".
            $table->string('purpose', 500)->nullable();
            $table->date('departure_date');
            $table->date('work_start_date');
            $table->date('return_date');
            $table->json('initial_boxes')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['departure_date', 'return_date']);
        });

        Schema::create('assignment_letter_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('name');
            $table->string('employee_code')->nullable();
            $table->string('position')->nullable();
            $table->string('department_name')->nullable();
            $table->timestamps();

            // Nama otomatis Laravel (67 karakter) melewati batas 64 karakter identifier MySQL.
            $table->unique(['assignment_letter_id', 'employee_id'], 'al_employees_letter_employee_unique');
        });

        Schema::create('assignment_letter_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_letter_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('activity');
            $table->text('address')->nullable();
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->timestamps();
        });

        // Perangkat komunikasi dan transportasi beserta hasil pemeriksaan GA&HSE.
        Schema::create('assignment_letter_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_letter_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('type', 20);
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('communication_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500);
            $table->string('inspection_result')->nullable();
            $table->date('inspected_on')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_letter_facilities');
        Schema::dropIfExists('assignment_letter_destinations');
        Schema::dropIfExists('assignment_letter_employees');
        Schema::dropIfExists('assignment_letters');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('communication_devices');
        Schema::dropIfExists('vehicles');
    }
};
