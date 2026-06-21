<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employee_approvers MODIFY COLUMN request_type ENUM('leave','overtime','attendance','budget','travel_report','lpj')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_approvers MODIFY COLUMN request_type ENUM('leave','overtime','attendance','budget','travel_report')");
    }
};
