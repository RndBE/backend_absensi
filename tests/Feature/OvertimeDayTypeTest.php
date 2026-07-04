<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Support\ScheduledWorkingDays;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Auto-deteksi tipe lembur dari tanggal: ada shift kerja terjadwal → hari kerja;
 * off/libur → hari libur. (ScheduledWorkingDays::isWorkingDate)
 */
class OvertimeDayTypeTest extends TestCase
{
    private const DATE = '2026-07-10';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('employee_code')->unique();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('schedule_template_id')->nullable();
            $t->unsignedBigInteger('work_schedule_id')->nullable();
            $t->string('full_name');
            $t->string('email')->unique();
            $t->timestamps();
        });
        Schema::create('shifts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->string('name');
            $t->boolean('is_off')->default(false);
            $t->timestamps();
        });
        Schema::create('schedule_assignments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('date');
            $t->unsignedBigInteger('shift_id');
            $t->timestamps();
        });
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->date('date');
            $t->timestamps();
        });
    }

    private function employee(): Employee
    {
        return Employee::create([
            'employee_code' => 'EMP-1',
            'company_id' => Company::create(['name' => 'PT X'])->id,
            'full_name' => 'Shandy',
            'email' => 'shandy@t.test',
        ]);
    }

    private function shift(bool $isOff): int
    {
        return DB::table('shifts')->insertGetId([
            'company_id' => 1, 'name' => $isOff ? 'Libur' : 'Pagi', 'is_off' => $isOff,
        ]);
    }

    private function assign(int $employeeId, int $shiftId): void
    {
        DB::table('schedule_assignments')->insert([
            'employee_id' => $employeeId, 'date' => self::DATE, 'shift_id' => $shiftId,
        ]);
    }

    public function test_working_shift_is_workday(): void
    {
        $emp = $this->employee();
        $this->assign($emp->id, $this->shift(false));

        $this->assertTrue(ScheduledWorkingDays::isWorkingDate($emp, Carbon::parse(self::DATE)));
    }

    public function test_off_shift_is_holiday(): void
    {
        $emp = $this->employee();
        $this->assign($emp->id, $this->shift(true));

        $this->assertFalse(ScheduledWorkingDays::isWorkingDate($emp, Carbon::parse(self::DATE)));
    }

    public function test_national_holiday_is_holiday(): void
    {
        $emp = $this->employee();
        DB::table('holidays')->insert(['company_id' => $emp->company_id, 'date' => self::DATE]);

        $this->assertFalse(ScheduledWorkingDays::isWorkingDate($emp, Carbon::parse(self::DATE)));
    }
}
