<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change enum to include superadmin
        DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('superadmin', 'admin', 'manager', 'employee') DEFAULT 'employee'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('admin', 'manager', 'employee') DEFAULT 'employee'");
    }
};
