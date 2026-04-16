<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('processed_by')->nullable()->constrained('employees')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable(); // transfer, cash, etc.
            $table->string('payment_proof')->nullable(); // file path
            $table->string('reference_no')->nullable(); // nomor referensi transfer
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, paid
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_payments');
    }
};
