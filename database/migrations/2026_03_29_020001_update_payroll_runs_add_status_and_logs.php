<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores Laravel enum columns as text, so no schema change is needed there.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payroll_runs MODIFY COLUMN status ENUM('draft','finalized','published','locked') DEFAULT 'draft'");
        }

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('finalized_at');
            $table->timestamp('locked_at')->nullable()->after('published_at');
        });

        Schema::create('payroll_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('performed_by')->references('id')->on('employees')->nullOnDelete();
            $table->index('payroll_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_logs');

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'locked_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payroll_runs MODIFY COLUMN status ENUM('draft','finalized') DEFAULT 'draft'");
        }
    }
};
