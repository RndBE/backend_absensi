<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\AttendanceLate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceLateRecalcTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-22 09:00:00');

        foreach (['attendances', 'schedule_assignments', 'holidays', 'shifts', 'employees'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off')->default(false);
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->date('date');
            $table->string('name')->nullable();
            $table->boolean('is_national')->default(false);
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->string('status')->default('present');
            $table->string('review_status')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamps();
        });

        DB::table('employees')->insert([
            'id' => 1, 'company_id' => 1, 'full_name' => 'Budi', 'email' => 'budi@test.id',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shifts')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'Pagi', 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'is_off' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'company_id' => 1, 'name' => 'Siang', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_off' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'company_id' => 1, 'name' => 'Libur', 'start_time' => null, 'end_time' => null, 'is_off' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedAttendance(string $clockIn, bool $isLate): void
    {
        DB::table('attendances')->insert([
            'employee_id' => 1, 'date' => '2026-06-22', 'clock_in' => $clockIn,
            'status' => 'present', 'is_late' => $isLate, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assignShift(int $shiftId): void
    {
        DB::table('schedule_assignments')->updateOrInsert(
            ['employee_id' => 1, 'date' => '2026-06-22'],
            ['shift_id' => $shiftId, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function test_recalc_clears_late_when_shift_changed_to_later_start(): void
    {
        // Clock-in 08:30, shift lama mulai 08:00 → terlambat.
        $this->assignShift(1);
        $this->seedAttendance('08:30:00', true);

        // Ganti ke shift mulai 09:00 → 08:30 tidak lagi terlambat.
        $this->assignShift(2);
        $changed = AttendanceLate::recalculate(Employee::find(1), '2026-06-22');

        $this->assertTrue($changed);
        $this->assertSame(0, (int) DB::table('attendances')->where('employee_id', 1)->value('is_late'));
    }

    public function test_recalc_sets_late_when_shift_changed_to_earlier_start(): void
    {
        // Clock-in 08:30, shift mulai 09:00 → tidak terlambat.
        $this->assignShift(2);
        $this->seedAttendance('08:30:00', false);

        // Ganti ke shift mulai 08:00 → jadi terlambat.
        $this->assignShift(1);
        $changed = AttendanceLate::recalculate(Employee::find(1), '2026-06-22');

        $this->assertTrue($changed);
        $this->assertSame(1, (int) DB::table('attendances')->where('employee_id', 1)->value('is_late'));
    }

    public function test_recalc_clears_late_when_shift_is_off(): void
    {
        $this->assignShift(1);
        $this->seedAttendance('08:30:00', true);

        $this->assignShift(3); // shift off
        AttendanceLate::recalculate(Employee::find(1), '2026-06-22');

        $this->assertSame(0, (int) DB::table('attendances')->where('employee_id', 1)->value('is_late'));
    }

    public function test_recalc_noop_when_no_attendance(): void
    {
        $this->assignShift(1);

        $changed = AttendanceLate::recalculate(Employee::find(1), '2026-06-22');

        $this->assertFalse($changed);
    }

    public function test_recalc_skips_rejected_review(): void
    {
        $this->assignShift(2); // mulai 09:00 → seharusnya tidak telat
        DB::table('attendances')->insert([
            'employee_id' => 1, 'date' => '2026-06-22', 'clock_in' => '08:30:00',
            'status' => 'absent', 'review_status' => 'rejected', 'is_late' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $changed = AttendanceLate::recalculate(Employee::find(1), '2026-06-22');

        $this->assertFalse($changed);
        $this->assertSame(1, (int) DB::table('attendances')->where('employee_id', 1)->value('is_late'));
    }
}
