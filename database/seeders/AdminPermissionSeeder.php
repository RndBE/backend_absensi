<?php

namespace Database\Seeders;

use App\Services\AdminPermissionService;
use Illuminate\Database\Seeder;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        AdminPermissionService::syncDefaults(true);
    }
}
