<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'budget' to the request_type enum
        DB::statement("ALTER TABLE employee_approvers MODIFY COLUMN request_type ENUM('leave','overtime','attendance','budget')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_approvers MODIFY COLUMN request_type ENUM('leave','overtime','attendance')");
    }
};
