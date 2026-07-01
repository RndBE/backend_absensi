<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\Notification;
use App\Services\LhpReminderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LhpReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-23 08:00:00');

        foreach (['notifications', 'travel_reports', 'budget_request_participants', 'budget_requests', 'holidays', 'employees', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->date('date');
            $table->string('name')->nullable();
            $table->boolean('is_national')->default(false);
            $table->timestamps();
        });

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedTinyInteger('lhp_deadline_days')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_request_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    private function seedEmployee(string $name = 'Budi'): int
    {
        return DB::table('employees')->insertGetId([
            'full_name' => $name,
            'email' => strtolower($name).uniqid().'@test.id',
            'company_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBudget(int $empId, string $returnDate, string $status = 'approved', ?int $overrideDays = null): int
    {
        return DB::table('budget_requests')->insertGetId([
            'employee_id' => $empId,
            'title' => 'Perjalanan Solo',
            'status' => $status,
            'departure_date' => $returnDate,
            'return_date' => $returnDate,
            'lhp_deadline_days' => $overrideDays,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_sends_after_return_reminder_next_day_when_lhp_missing(): void
    {
        // Hari ini 2026-06-23, tanggal pulang 2026-06-22 (H+1 default).
        $emp = $this->seedEmployee();
        $budget = $this->seedBudget($emp, '2026-06-22');

        $result = LhpReminderService::remindForDate(Carbon::today());

        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('notifications', [
            'employee_id'    => $emp,
            'type'           => 'lhp_reminder_after',
            'reference_type' => BudgetRequest::class,
            'reference_id'   => $budget,
        ]);
    }

    public function test_sends_deadline_reminder_two_working_days_before(): void
    {
        // return 2026-06-15 → batas = +5 hari kerja = 2026-06-22.
        // H-2 hari kerja sebelum batas = 2026-06-18 (Kamis, lompati Sab-Min).
        Carbon::setTestNow('2026-06-18 08:00:00');
        $emp = $this->seedEmployee();
        $budget = $this->seedBudget($emp, '2026-06-15');

        $result = LhpReminderService::remindForDate(Carbon::today());

        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('notifications', [
            'employee_id'    => $emp,
            'type'           => 'lhp_reminder_deadline',
            'reference_id'   => $budget,
        ]);
    }

    public function test_does_not_send_when_lhp_already_created(): void
    {
        $emp = $this->seedEmployee();
        $budget = $this->seedBudget($emp, '2026-06-22');
        DB::table('travel_reports')->insert([
            'budget_request_id' => $budget, 'employee_id' => $emp, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = LhpReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, Notification::count());
    }

    public function test_does_not_send_when_budget_not_approved(): void
    {
        $emp = $this->seedEmployee();
        $this->seedBudget($emp, '2026-06-22', 'pending');

        $result = LhpReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
    }

    public function test_does_not_duplicate_on_repeated_runs(): void
    {
        $emp = $this->seedEmployee();
        $this->seedBudget($emp, '2026-06-22');

        LhpReminderService::remindForDate(Carbon::today());
        $second = LhpReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $second['sent']);
        $this->assertSame(1, Notification::where('type', 'lhp_reminder_after')->count());
    }

    public function test_does_not_send_when_disabled_in_settings(): void
    {
        DB::table('settings')->insert(['key' => 'lhp_reminder_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()]);
        $emp = $this->seedEmployee();
        $this->seedBudget($emp, '2026-06-22');

        $result = LhpReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, Notification::count());
    }

    public function test_reminds_participants_who_have_not_made_lhp(): void
    {
        $owner = $this->seedEmployee('Owner');
        $participant = $this->seedEmployee('Peserta');
        $budget = $this->seedBudget($owner, '2026-06-22');
        DB::table('budget_request_participants')->insert([
            'budget_request_id' => $budget, 'employee_id' => $participant, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = LhpReminderService::remindForDate(Carbon::today());

        $this->assertSame(2, $result['sent']);
        $this->assertDatabaseHas('notifications', ['employee_id' => $owner, 'type' => 'lhp_reminder_after']);
        $this->assertDatabaseHas('notifications', ['employee_id' => $participant, 'type' => 'lhp_reminder_after']);
    }
}
