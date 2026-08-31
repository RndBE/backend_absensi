<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buku besar cicilan pinjaman: satu baris per (pinjaman, payroll run).
     *
     * Sebelumnya penanda "sudah dipotong" hanya hidup di dalam JSON
     * payroll_run_details.components (flag balance_applied). Aksi regenerate
     * menghapus baris detail, jadi penandanya ikut hilang dan finalize
     * berikutnya memotong saldo pinjaman untuk kedua kalinya. Unique key di
     * bawah membuat pemotongan ganda mustahil, sekaligus jadi jejak audit
     * untuk mengembalikan saldo saat payroll run dibuka ulang atau dihapus.
     */
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('period', 7)->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['loan_request_id', 'payroll_run_id'], 'loan_repayments_loan_run_unique');
            $table->index('payroll_run_id');
            $table->index('loan_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
