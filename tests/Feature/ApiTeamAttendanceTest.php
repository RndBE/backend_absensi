<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\TeamAttendanceScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Presensi tim di API mobile — aturan siapa melihat siapa sama dengan portal employee
 * (lihat TeamAttendancePortalTest): level 1-3 melihat level di bawahnya di departemennya
 * beserta turunannya.
 */
class ApiTeamAttendanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('job_level')->nullable();
            $table->unsignedBigInteger('kpi_level_id')->nullable();
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('kpi_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5);
            $table->timestamps();
        });
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->boolean('is_off')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
        });
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
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
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->unsignedBigInteger('shift_id');
            $table->timestamps();
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('date');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1)->default(1);
            $table->string('status')->default('approved');
            $table->unsignedInteger('current_step')->default(1);
            $table->timestamps();
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('clock_in_photo')->nullable();
            $table->string('clock_out_photo')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->string('remote_notes')->nullable();
            $table->timestamps();
        });

        $this->seedOrg();
    }

    /**
     * Pohon: DIVISI (1) ─┬─ PURCHASING (2)
     *                    └─ FINANCE (3)         LAIN (4)
     *
     * 10 Dewi L2 @DIVISI       11 Leader A L3 @PURCHASING   13 Staff X L4 @PURCHASING
     * 14 Staff Y L4 @FINANCE   16 Staff Lain L4 @LAIN       17 Nonaktif L4 @PURCHASING
     */
    private function seedOrg(): void
    {
        DB::table('departments')->insert([
            ['id' => 1, 'company_id' => 1, 'parent_id' => null, 'name' => 'DIVISI'],
            ['id' => 2, 'company_id' => 1, 'parent_id' => 1, 'name' => 'PURCHASING'],
            ['id' => 3, 'company_id' => 1, 'parent_id' => 1, 'name' => 'FINANCE'],
            ['id' => 4, 'company_id' => 1, 'parent_id' => null, 'name' => 'LAIN'],
        ]);
        DB::table('kpi_levels')->insert([
            ['id' => 2, 'code' => 'L2'], ['id' => 3, 'code' => 'L3'], ['id' => 4, 'code' => 'L4'],
        ]);

        $this->employee(10, 'Dewi L2', 1, 2);
        $this->employee(11, 'Leader A', 2, 3);
        $this->employee(13, 'Staff X', 2, 4);
        $this->employee(14, 'Staff Y', 3, 4);
        $this->employee(16, 'Staff Lain', 4, 4);
        $this->employee(17, 'Staff Nonaktif', 2, 4, isActive: false);
    }

    private function employee(int $id, string $name, int $departmentId, int $level, bool $isActive = true): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'company_id' => 1,
            'department_id' => $departmentId,
            'employee_code' => 'EMP'.$id,
            'full_name' => $name,
            'email' => "emp{$id}@example.test",
            'password' => Hash::make('password'),
            'kpi_level_id' => $level,
            'job_level' => $level,
            'is_active' => $isActive,
            'join_date' => '2020-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingAs_(int $id): void
    {
        Sanctum::actingAs(Employee::findOrFail($id));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/team-attendance')->assertUnauthorized();
    }

    public function test_level_two_sees_subtree_members_below_their_level(): void
    {
        $this->actingAs_(10);

        $response = $this->getJson('/api/team-attendance')->assertOk();

        $ids = collect($response->json('data.members'))->pluck('id')->sort()->values()->all();
        // L3 dan L4 di PURCHASING + FINANCE (turunan DIVISI). Bukan LAIN, bukan yang nonaktif, bukan diri sendiri.
        $this->assertSame([11, 13, 14], $ids);
        $response->assertJsonPath('data.departments.0', 'DIVISI');
        $this->assertArrayHasKey('summary', $response->json('data'));
        $this->assertArrayHasKey('label', $response->json('data.members.0.today'));
    }

    public function test_level_three_sees_only_level_four_in_own_department(): void
    {
        $this->actingAs_(11);

        $ids = collect($this->getJson('/api/team-attendance')->assertOk()->json('data.members'))
            ->pluck('id')->all();

        $this->assertSame([13], $ids);
    }

    public function test_level_four_is_forbidden(): void
    {
        $this->actingAs_(13);

        $this->getJson('/api/team-attendance')
            ->assertForbidden()
            ->assertJsonPath('success', false);
        $this->getJson('/api/team-attendance/14')->assertForbidden();
    }

    public function test_member_detail_returns_month_without_photo_or_gps(): void
    {
        DB::table('attendances')->insert([
            'employee_id' => 13, 'date' => now()->toDateString(), 'clock_in' => '08:00:00',
            'clock_in_photo' => 'selfie.jpg', 'clock_in_lat' => -7.8, 'clock_in_lng' => 110.3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs_(11);

        $response = $this->getJson('/api/team-attendance/13?period='.now()->format('Y-m'))->assertOk();

        $response->assertJsonPath('data.member.id', 13)
            ->assertJsonPath('data.period', now()->format('Y-m'));
        $this->assertCount(now()->daysInMonth, $response->json('data.days'));
        $this->assertArrayHasKey('hadir', $response->json('data.stats'));

        $today = collect($response->json('data.days'))->firstWhere('is_today', true);
        $this->assertSame('08:00:00', $today['attendance']['clock_in']);
        $this->assertArrayNotHasKey('clock_in_photo', $today['attendance']);
        $this->assertArrayNotHasKey('clock_in_lat', $today['attendance']);
    }

    public function test_member_outside_scope_is_forbidden(): void
    {
        $this->actingAs_(11);

        $this->getJson('/api/team-attendance/14')->assertForbidden(); // FINANCE, bukan departemen Leader A
        $this->getJson('/api/team-attendance/16')->assertForbidden(); // LAIN
        $this->getJson('/api/team-attendance/10')->assertForbidden(); // atasan
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->actingAs_(11);

        $this->getJson('/api/team-attendance/13?period=bukan-bulan')->assertStatus(422);
    }

    public function test_scope_allowed_flag_matches_levels(): void
    {
        $this->assertTrue((new TeamAttendanceScope(Employee::findOrFail(10)))->allowed());
        $this->assertTrue((new TeamAttendanceScope(Employee::findOrFail(11)))->allowed());
        $this->assertFalse((new TeamAttendanceScope(Employee::findOrFail(13)))->allowed());
    }
}
