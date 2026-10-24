<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\Notification;
use App\Services\LpjReminderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LpjReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-22 08:00:00');

        foreach (['notifications', 'lpjs', 'travel_reports', 'budget_requests', 'employees', 'settings'] as $table) {
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
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('return_date')->nullable();
            $table->timestamps();
        });

        Schema::create('lpjs', function (Blueprint $table) {
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

    private function seedTrip(string $returnDate, string $status = 'approved'): array
    {
        $empId = DB::table('employees')->insertGetId([
            'full_name' => 'Budi', 'email' => 'budi'.uniqid().'@test.id', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $budgetId = DB::table('budget_requests')->insertGetId([
            'employee_id' => $empId, 'title' => 'Perjalanan Solo', 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('travel_reports')->insert([
            'budget_request_id' => $budgetId, 'employee_id' => $empId, 'return_date' => $returnDate, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['employee_id' => $empId, 'budget_id' => $budgetId];
    }

    public function test_sends_reminder_three_days_after_return_when_lpj_missing(): void
    {
        // Hari ini 2026-06-22 → tanggal pulang 3 hari lalu = 2026-06-19
        $trip = $this->seedTrip('2026-06-19');

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('notifications', [
            'employee_id'    => $trip['employee_id'],
            'type'           => 'lpj_reminder',
            'reference_type' => BudgetRequest::class,
            'reference_id'   => $trip['budget_id'],
        ]);
    }

    public function test_does_not_send_when_not_yet_three_days(): void
    {
        $this->seedTrip('2026-06-20'); // baru 2 hari

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, Notification::count());
    }

    public function test_does_not_send_when_lpj_already_created(): void
    {
        $trip = $this->seedTrip('2026-06-19');
        DB::table('lpjs')->insert([
            'budget_request_id' => $trip['budget_id'], 'employee_id' => $trip['employee_id'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, Notification::count());
    }

    public function test_only_the_budget_owner_is_reminded_once_even_when_participants_wrote_lhps(): void
    {
        $owner = $this->seedTrip('2026-06-19');
        $participantId = $this->participantLhp($owner['budget_id'], '2026-06-19');

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, Notification::where('type', 'lpj_reminder')->count());
        $this->assertDatabaseHas('notifications', ['employee_id' => $owner['employee_id'], 'reference_id' => $owner['budget_id']]);
        $this->assertDatabaseMissing('notifications', ['employee_id' => $participantId]);
    }

    public function test_the_owner_is_reminded_when_only_a_participant_wrote_the_lhp(): void
    {
        $owner = $this->seedTrip('2026-06-25'); // LHP pemilik belum jatuh tempo
        $this->participantLhp($owner['budget_id'], '2026-06-19');

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('notifications', ['employee_id' => $owner['employee_id'], 'reference_id' => $owner['budget_id']]);
    }

    public function test_an_lpj_from_anyone_on_the_budget_stops_the_reminder(): void
    {
        $owner = $this->seedTrip('2026-06-19');
        $participantId = $this->participantLhp($owner['budget_id'], '2026-06-19');
        // LPJ lama buatan peserta (sebelum aturan satu LPJ per anggaran) tetap dihitung.
        DB::table('lpjs')->insert([
            'budget_request_id' => $owner['budget_id'], 'employee_id' => $participantId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, Notification::count());
    }

    public function test_due_list_names_only_the_owner_once_per_budget(): void
    {
        $owner = $this->seedTrip('2026-06-19');
        $this->participantLhp($owner['budget_id'], '2026-06-19');

        $due = LpjReminderService::dueForDate(Carbon::today());

        $this->assertCount(1, $due);
        $this->assertSame($owner['employee_id'], $due->first()['employee']->id);
        $this->assertSame($owner['budget_id'], $due->first()['reference_id']);
    }

    /** Peserta lain yang menulis LHP untuk anggaran yang sama. */
    private function participantLhp(int $budgetId, string $returnDate): int
    {
        $participantId = DB::table('employees')->insertGetId([
            'full_name' => 'Peserta', 'email' => 'peserta'.uniqid().'@test.id', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('travel_reports')->insert([
            'budget_request_id' => $budgetId, 'employee_id' => $participantId, 'return_date' => $returnDate, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $participantId;
    }

    public function test_does_not_send_when_budget_not_approved(): void
    {
        $this->seedTrip('2026-06-19', 'pending');

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
    }

    public function test_does_not_duplicate_on_repeated_runs(): void
    {
        $this->seedTrip('2026-06-19');

        LpjReminderService::remindForDate(Carbon::today());
        $second = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $second['sent']);
        $this->assertSame(1, Notification::where('type', 'lpj_reminder')->count());
    }

    public function test_does_not_send_when_disabled_in_settings(): void
    {
        DB::table('settings')->insert(['key' => 'lpj_reminder_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()]);
        $this->seedTrip('2026-06-19');

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, Notification::count());
    }

    public function test_uses_configurable_reminder_days(): void
    {
        DB::table('settings')->insert(['key' => 'lpj_reminder_days', 'value' => '5', 'created_at' => now(), 'updated_at' => now()]);
        // Hari ini 2026-06-22 → 5 hari lalu = 2026-06-17
        $trip = $this->seedTrip('2026-06-17');

        $result = LpjReminderService::remindForDate(Carbon::today());

        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('notifications', [
            'reference_id' => $trip['budget_id'],
            'type'         => 'lpj_reminder',
        ]);
    }
}
