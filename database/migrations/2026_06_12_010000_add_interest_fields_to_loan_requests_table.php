<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->default(0)->after('amount');
            $table->decimal('interest_amount', 15, 2)->default(0)->after('interest_rate');
            $table->decimal('total_repayable', 15, 2)->default(0)->after('interest_amount');
        });

        DB::table('loan_requests')->update([
            'interest_rate' => 0,
            'interest_amount' => 0,
            'total_repayable' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropColumn([
                'interest_rate',
                'interest_amount',
                'total_repayable',
            ]);
        });
    }
};
