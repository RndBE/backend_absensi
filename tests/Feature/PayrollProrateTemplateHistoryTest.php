<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\ScheduledWorkingDays;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Riwayat template karyawan baru dimulai di tanggal join. Pembagi pro-rate dulu ikut terpotong
 * ke tanggal itu (Aris & Latasha, join 16 Sep 2026: 11/11 dan 13/13 hari → gaji penuh).
 */
class PayrollProrateTemplateHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->date('join_date')->nullable();
            $table->timestamps();
        });
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->boolean('is_off')->default(false);
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
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->timestamps();
        });
        Schema::create('employee_schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->date('effective_from');
            $table->timestamps();
        });
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->timestamps();
        });

        DB::table('shifts')->insert(['id' => 1, 'name' => 'Pagi', 'is_off' => false]);
        DB::table('schedule_templates')->insert(['id' => 1, 'name' => 'Senin-Jumat']);
        foreach ([1, 2, 3, 4, 5] as $dow) {
            DB::table('schedule_template_days')->insert(['template_id' => 1, 'day_of_week' => $dow, 'shift_id' => 1]);
        }
    }

    public function test_monthly_denominator_covers_days_before_the_first_history_row(): void
    {
        $aris = $this->employee('2026-09-16', historyFrom: '2026-09-16');

        // September 2026: 22 hari Senin–Jumat; join Rabu 16 Sep → 11 hari kerja dijalani.
        $this->assertSame(22, ScheduledWorkingDays::monthlyWorkingDays($aris, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), []));
        $this->assertSame(11, ScheduledWorkingDays::count($aris, Carbon::parse('2026-09-16'), Carbon::parse('2026-09-30'), [], forPayroll: true));
    }

    public function test_worked_days_from_join_count_even_when_history_starts_later(): void
    {
        // Join Senin 7 Sep, template baru tercatat mulai 12 Sep → 7–11 Sep tetap hari kerja.
        $paisal = $this->employee('2026-09-07', historyFrom: '2026-09-12');

        $this->assertSame(18, ScheduledWorkingDays::count($paisal, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-30'), [], forPayroll: true));
    }

    public function test_non_payroll_counts_keep_ignoring_days_before_the_history(): void
    {
        // Dipakai KPI absensi: hari sebelum riwayat tetap tidak dijadwalkan.
        $aris = $this->employee('2026-09-16', historyFrom: '2026-09-16');

        $this->assertSame(0, ScheduledWorkingDays::count($aris, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-15'), []));
        $this->assertSame(11, ScheduledWorkingDays::count($aris, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), []));
    }

    public function test_an_explicit_untemplated_row_is_still_respected(): void
    {
        $e = $this->employee('2026-09-01', historyFrom: '2026-09-01');
        // Sejak 16 Sep tanpa template.
        DB::table('employee_schedule_templates')->insert(['employee_id' => $e->id, 'template_id' => null, 'effective_from' => '2026-09-16']);

        $this->assertSame(0, ScheduledWorkingDays::count($e->fresh(), Carbon::parse('2026-09-16'), Carbon::parse('2026-09-30'), [], forPayroll: true));
    }

    private function employee(string $joinDate, string $historyFrom): Employee
    {
        $id = DB::table('employees')->insertGetId([
            'schedule_template_id' => 1,
            'full_name' => 'Karyawan '.$joinDate,
            'email' => uniqid().'@t.test',
            'join_date' => $joinDate,
        ]);
        DB::table('employee_schedule_templates')->insert(['employee_id' => $id, 'template_id' => 1, 'effective_from' => $historyFrom]);

        return Employee::find($id);
    }
}
