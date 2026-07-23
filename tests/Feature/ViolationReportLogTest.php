<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ViolationReportLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('violation_report_logs');
        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('employees');
        Schema::enableForeignKeyConstraints();

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('module');
            $table->string('action');
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('violation_report_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('action')->default('open_form');
            $table->text('target_url');
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function test_opening_violation_report_form_records_separate_hidden_log(): void
    {
        config()->set('services.violation_report.form_url', 'https://forms.example.test/report');

        DB::table('employees')->insert([
            'id' => 7,
            'company_id' => 3,
            'email' => 'employee@example.test',
            'password' => 'secret',
            'full_name' => 'Employee',
            'role' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withSession(['employee_id' => 7])
            ->withHeader('User-Agent', 'FeatureTest')
            ->get(route('employee.violation-report.open'));

        $response->assertRedirect('https://forms.example.test/report');

        $this->assertDatabaseHas('violation_report_logs', [
            'employee_id' => 7,
            'company_id' => 3,
            'action' => 'open_form',
            'target_url' => 'https://forms.example.test/report',
            'route_name' => 'employee.violation-report.open',
            'method' => 'GET',
            'path' => 'employee/violation-report/open',
        ]);

        $this->assertDatabaseCount('admin_activity_logs', 0);
    }

    public function test_violation_report_page_uses_internal_logging_redirect_route(): void
    {
        DB::table('employees')->insert([
            'id' => 8,
            'company_id' => 3,
            'email' => 'employee-2@example.test',
            'password' => 'secret',
            'full_name' => 'Employee 2',
            'role' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->withSession(['employee_id' => 8])
            ->get(route('employee.violation-report.index'))
            ->assertOk()
            ->assertSee(route('employee.violation-report.open'), false)
            ->assertDontSee('https://tinyurl.com/PELAPORAN-PELANGGARAN-ATC', false);
    }
}
