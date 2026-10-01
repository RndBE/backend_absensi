<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * generateDetails() memakai satu instance $periodStart untuk semua karyawan. Cuti yang mulai
 * tepat di awal periode dulu membuat getApprovedLeaveDates() menggeser instance itu, sehingga
 * lembur tanggal 1 milik karyawan yang diproses sesudahnya tidak ikut terbayar (payroll 2026-09).
 */
class PayrollLeaveDatesPeriodDriftTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function test_leave_starting_on_period_start_does_not_move_the_period(): void
    {
        DB::table('leave_requests')->insert([
            'employee_id' => 11,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'status' => 'approved',
        ]);

        $periodStart = Carbon::parse('2026-09-01')->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $dates = $this->invokePrivate(new PayrollRunController, 'getApprovedLeaveDates', [11, $periodStart, $periodEnd]);

        $this->assertSame(['2026-09-01', '2026-09-02'], $dates);
        $this->assertSame('2026-09-01 00:00:00', $periodStart->toDateTimeString());
        $this->assertSame('2026-09-30 23:59:59', $periodEnd->toDateTimeString());
    }

    public function test_leave_spanning_both_period_edges_is_clipped_without_moving_the_period(): void
    {
        DB::table('leave_requests')->insert([
            'employee_id' => 4,
            'start_date' => '2026-08-30',
            'end_date' => '2026-10-02',
            'status' => 'approved',
        ]);

        $periodStart = Carbon::parse('2026-09-01')->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $dates = $this->invokePrivate(new PayrollRunController, 'getApprovedLeaveDates', [4, $periodStart, $periodEnd]);

        $this->assertCount(30, $dates);
        $this->assertSame('2026-09-01', $dates[0]);
        $this->assertSame('2026-09-30', $dates[29]);
        $this->assertSame('2026-09-01 00:00:00', $periodStart->toDateTimeString());
        $this->assertSame('2026-09-30 23:59:59', $periodEnd->toDateTimeString());
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
