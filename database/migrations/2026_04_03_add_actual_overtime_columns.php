<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->enum('overtime_type', ['workday', 'holiday'])->default('workday')->after('date');
            $table->time('planned_start')->nullable()->after('overtime_type')->comment('Jam mulai (untuk OT hari libur)');
            $table->time('planned_end')->nullable()->after('planned_start')->comment('Jam selesai (untuk OT hari libur)');
            $table->integer('break_duration')->default(0)->after('post_shift_break')->comment('Total break dalam menit');
            $table->integer('approved_duration')->nullable()->after('total_duration')->comment('Durasi yang di-set approver (menit)');
            $table->integer('approved_break')->nullable()->after('approved_duration')->comment('Break yang di-set approver (menit)');
            $table->integer('actual_duration')->nullable()->after('approved_break')->comment('Overtime aktual dari clock out (menit)');
            $table->time('shift_end_time')->nullable()->after('actual_duration')->comment('Jam selesai shift hari itu');
            $table->time('actual_clock_in')->nullable()->after('shift_end_time');
            $table->time('actual_clock_out')->nullable()->after('actual_clock_in');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropColumn([
                'overtime_type', 'planned_start', 'planned_end',
                'break_duration', 'approved_duration', 'approved_break',
                'actual_duration', 'shift_end_time', 'actual_clock_in', 'actual_clock_out',
            ]);
        });
    }
};
