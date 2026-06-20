<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('religion')->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->text('ktp_address')->nullable();
            $table->text('residential_address')->nullable();
            $table->string('position')->nullable();
            $table->integer('job_level')->nullable();
            $table->enum('employment_status', ['permanent', 'contract', 'intern', 'probation', 'outsourcing'])->default('contract');
            $table->date('join_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('role', ['admin', 'manager', 'employee'])->default('employee');
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('approver_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
