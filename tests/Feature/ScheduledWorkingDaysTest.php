<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\ScheduledWorkingDays;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScheduledWorkingDaysTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['schedule_assignments', 'schedule_template_days', 'schedule_templates', 'holidays', 'work_schedules', 'shifts', 'employees'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
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

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->time('start_time')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedBigInteger('shift_id');
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
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

        DB::table('shifts')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'Pagi', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_off' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'company_id' => 1, 'name' => 'Off', 'start_time' => null, 'end_time' => null, 'is_off' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('schedule_templates')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Senin-Jumat', 'created_at' => now(), 'updated_at' => now()]);
        // Senin(1) s/d Jumat(5) kerja shift Pagi; Sabtu/Minggu tidak ada baris → off.
        foreach ([1, 2, 3, 4, 5] as $dow) {
            DB::table('schedule_template_days')->insert(['template_id' => 1, 'day_of_week' => $dow, 'shift_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'schedule_template_id' => 1, 'full_name' => 'Budi', 'email' => 'budi@test.id', 'created_at' => now(), 'updated_at' => now()],
            // Karyawan assignment-only (tanpa template) untuk uji inferensi pola mingguan.
            ['id' => 2, 'company_id' => 1, 'schedule_template_id' => null, 'full_name' => 'Sinta', 'email' => 'sinta@test.id', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function assignmentOnlyEmp(): Employee
    {
        return Employee::find(2);
    }

    private function emp(): Employee
    {
        return Employee::find(1);
    }

    public function test_counts_weekdays_in_full_month(): void
    {
        // Juni 2026: 22 hari kerja Senin-Jumat.
        $count = ScheduledWorkingDays::count($this->emp(), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertSame(22, $count);
    }

    public function test_counts_working_days_from_join_date(): void
    {
        // Join 24 Jun (Rabu) → 24,25,26,29,30 = 5 hari kerja.
        $count = ScheduledWorkingDays::count($this->emp(), Carbon::parse('2026-06-24'), Carbon::parse('2026-06-30'));

        $this->assertSame(5, $count);
    }

    public function test_excludes_national_holiday(): void
    {
        DB::table('holidays')->insert(['company_id' => 1, 'date' => '2026-06-25', 'name' => 'Libur', 'is_national' => true, 'created_at' => now(), 'updated_at' => now()]);

        // 24-30 Jun tanpa 25 → 4 hari kerja.
        $count = ScheduledWorkingDays::count($this->emp(), Carbon::parse('2026-06-24'), Carbon::parse('2026-06-30'));

        $this->assertSame(4, $count);
    }

    public function test_monthly_denominator_infers_weekly_off_pattern_for_assignment_only(): void
    {
        // Karyawan resign: jadwal hanya di-assign 1-10, kerja tiap hari kecuali Rabu (3 & 10 off).
        // Juni 2026: Rabu = 3,10,17,24. Pola off-Rabu → pembagi sebulan = 30 - 4 = 26.
        $working = [1, 2, 4, 5, 6, 7, 8, 9]; // 1-10 minus Rabu (3,10)
        $off = [3, 10];
        foreach ($working as $d) {
            DB::table('schedule_assignments')->insert(['employee_id' => 2, 'shift_id' => 1, 'date' => sprintf('2026-06-%02d', $d), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($off as $d) {
            DB::table('schedule_assignments')->insert(['employee_id' => 2, 'shift_id' => 2, 'date' => sprintf('2026-06-%02d', $d), 'created_at' => now(), 'updated_at' => now()]);
        }

        // Pembagi: sebulan penuh, off tiap Rabu → 26.
        $denominator = ScheduledWorkingDays::monthlyWorkingDays($this->assignmentOnlyEmp(), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
        $this->assertSame(26, $denominator);

        // Pembilang: hari kerja dijalani 1-10 (lompati off 3 & 10) → 8.
        $numerator = ScheduledWorkingDays::count($this->assignmentOnlyEmp(), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-10'));
        $this->assertSame(8, $numerator);
    }

    public function test_monthly_denominator_counts_holiday_when_employee_works_it(): void
    {
        // Off Rabu, TAPI tetap ada shift kerja saat libur (tgl 1 Juni = libur, tetap masuk).
        foreach ([1, 2, 4, 5, 6, 7, 8, 9] as $d) {
            DB::table('schedule_assignments')->insert(['employee_id' => 2, 'shift_id' => 1, 'date' => sprintf('2026-06-%02d', $d), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([3, 10] as $d) {
            DB::table('schedule_assignments')->insert(['employee_id' => 2, 'shift_id' => 2, 'date' => sprintf('2026-06-%02d', $d), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('holidays')->insert([
            ['company_id' => 1, 'date' => '2026-06-01', 'name' => 'Pancasila', 'is_national' => true, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'date' => '2026-06-16', 'name' => 'Tahun Baru Islam', 'is_national' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Karena ada shift kerja di hari libur (tgl 1) → libur dihitung kerja → tetap 26.
        $this->assertSame(26, ScheduledWorkingDays::monthlyWorkingDays($this->assignmentOnlyEmp(), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')));
    }

    public function test_monthly_denominator_excludes_holiday_when_employee_off_on_it(): void
    {
        // Off Rabu, DAN libur di tgl 1 memang OFF (tidak masuk).
        foreach ([2, 4, 5, 6, 7, 8, 9] as $d) {
            DB::table('schedule_assignments')->insert(['employee_id' => 2, 'shift_id' => 1, 'date' => sprintf('2026-06-%02d', $d), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([1, 3, 10] as $d) {
            DB::table('schedule_assignments')->insert(['employee_id' => 2, 'shift_id' => 2, 'date' => sprintf('2026-06-%02d', $d), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('holidays')->insert([
            ['company_id' => 1, 'date' => '2026-06-01', 'name' => 'Pancasila', 'is_national' => true, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'date' => '2026-06-16', 'name' => 'Tahun Baru Islam', 'is_national' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Tidak ada shift kerja di hari libur → 2 libur (1 & 16, keduanya hari kerja) dikurangi → 24.
        $this->assertSame(24, ScheduledWorkingDays::monthlyWorkingDays($this->assignmentOnlyEmp(), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')));
    }

    public function test_monthly_denominator_zero_when_no_schedule_at_all(): void
    {
        // Tanpa template & tanpa assignment → 0 (pemanggil fallback ke hari kalender).
        $this->assertSame(0, ScheduledWorkingDays::monthlyWorkingDays($this->assignmentOnlyEmp(), Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')));
    }

    public function test_override_off_excluded_and_override_work_on_weekend_included(): void
    {
        // Override: 24 Jun jadi OFF, 27 Jun (Sabtu) jadi kerja.
        DB::table('schedule_assignments')->insert([
            ['employee_id' => 1, 'shift_id' => 2, 'date' => '2026-06-24', 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 1, 'shift_id' => 1, 'date' => '2026-06-27', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 24-30: normalnya 24,25,26,29,30 = 5. Override: -24 (off) +27 (kerja) = 5.
        $count = ScheduledWorkingDays::count($this->emp(), Carbon::parse('2026-06-24'), Carbon::parse('2026-06-30'));

        $this->assertSame(5, $count);
        // Pastikan 27 (Sabtu) benar dihitung: 27-28 → hanya 27.
        $weekend = ScheduledWorkingDays::count($this->emp(), Carbon::parse('2026-06-27'), Carbon::parse('2026-06-28'));
        $this->assertSame(1, $weekend);
    }
}
