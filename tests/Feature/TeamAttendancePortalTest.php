<?php

namespace Tests\Feature;

use App\Support\DepartmentTree;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Presensi Tim di portal employee — hanya untuk manager.
 *
 * Cakupannya departemen manager BESERTA turunannya. Perbandingan `department_id` yang persis
 * membuat manager yang menempel di simpul induk tidak melihat siapa pun, karena anak buahnya
 * ber-`department_id` sub-departemen.
 */
class TeamAttendancePortalTest extends TestCase
{
    use WithFaker;

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
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->boolean('is_active')->default(true);
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
     *                    └─ FINANCE (3)
     * Manager di simpul INDUK; anggota di simpul anak. Plus satu departemen lain (4).
     */
    private function seedOrg(): void
    {
        DB::table('departments')->insert([
            ['id' => 1, 'company_id' => 1, 'parent_id' => null, 'name' => 'DIVISI'],
            ['id' => 2, 'company_id' => 1, 'parent_id' => 1, 'name' => 'PURCHASING'],
            ['id' => 3, 'company_id' => 1, 'parent_id' => 1, 'name' => 'FINANCE'],
            ['id' => 4, 'company_id' => 1, 'parent_id' => null, 'name' => 'LAIN'],
        ]);

        $this->employee(1, 'Manager Induk', 'manager', departmentId: 1);
        $this->employee(2, 'Anggota Purchasing', 'employee', departmentId: 2);
        $this->employee(3, 'Anggota Finance', 'employee', departmentId: 3);
        $this->employee(4, 'Orang Departemen Lain', 'employee', departmentId: 4);
        $this->employee(5, 'Karyawan Nonaktif', 'employee', departmentId: 2, isActive: false);
        $this->employee(6, 'Staff Biasa', 'employee', departmentId: 2);
    }

    private function employee(int $id, string $name, string $role, ?int $departmentId, bool $isActive = true): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'company_id' => 1,
            'department_id' => $departmentId,
            'employee_code' => 'EMP'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'email' => "emp{$id}@example.test",
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => $isActive,
            'join_date' => '2020-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_department_tree_includes_all_descendants(): void
    {
        $this->assertEqualsCanonicalizing([1, 2, 3], DepartmentTree::withDescendants(1));
        $this->assertSame([2], DepartmentTree::withDescendants(2));
        $this->assertSame([], DepartmentTree::withDescendants(null));
    }

    /** Inti fitur: manager di simpul induk tetap melihat anggota di sub-departemen. */
    public function test_manager_sees_members_of_department_subtree(): void
    {
        $html = $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance')
            ->assertOk()
            ->assertSee('Anggota Purchasing')
            ->assertSee('Anggota Finance')
            ->assertSee('Staff Biasa')
            ->assertDontSee('Orang Departemen Lain')   // departemen lain
            ->assertDontSee('Karyawan Nonaktif')       // sudah tidak aktif
            ->getContent();

        // Tepat 3 anggota. Tautan muncul dua kali per anggota (kartu HP + tabel layar lebar),
        // jadi yang dihitung adalah id uniknya, bukan jumlah tautan.
        preg_match_all('#/employee/team-attendance/(\d+)#', $html, $cocok);
        $this->assertEqualsCanonicalizing([2, 3, 6], array_unique(array_map('intval', $cocok[1])));
        $this->assertStringNotContainsString('/employee/team-attendance/1?', $html);
    }

    /** Di HP tabel disembunyikan, diganti kartu bertumpuk — tanpa scroll horizontal. */
    public function test_index_renders_cards_for_mobile_and_table_for_desktop(): void
    {
        $html = $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('space-y-2 lg:hidden', $html);       // kartu: hanya di HP
        $this->assertStringContainsString('hidden overflow-hidden', $html);    // tabel: mulai lg
        $this->assertStringContainsString('lg:block', $html);
    }

    public function test_non_manager_is_forbidden(): void
    {
        $this->withSession(['employee_id' => 6])
            ->get('/employee/team-attendance')
            ->assertForbidden();
    }

    public function test_manager_without_department_is_forbidden(): void
    {
        DB::table('employees')->where('id', 1)->update(['department_id' => null]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance')
            ->assertForbidden();
    }

    public function test_manager_cannot_open_member_outside_their_team(): void
    {
        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance/4')
            ->assertForbidden();
    }

    public function test_manager_can_open_member_detail(): void
    {
        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance/2?period=2026-07')
            ->assertOk()
            ->assertSee('Anggota Purchasing')
            ->assertSee('Hadir')
            ->assertSee('Alpha');
    }

    /** Jadwal 5 hari, masuk 08:00, dipasang ke seluruh anggota tim. */
    private function seedSchedule(): void
    {
        DB::table('shifts')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'Pagi', 'is_off' => false, 'start_time' => '08:00', 'end_time' => '17:00'],
            ['id' => 2, 'company_id' => 1, 'name' => 'Off', 'is_off' => true, 'start_time' => null, 'end_time' => null],
        ]);
        DB::table('schedule_templates')->insert(['id' => 1, 'company_id' => 1, 'name' => '5 Hari']);
        foreach ([1, 2, 3, 4, 5] as $dow) {
            DB::table('schedule_template_days')->insert(['template_id' => 1, 'day_of_week' => $dow, 'shift_id' => 1]);
        }
        foreach ([6, 7] as $dow) {
            DB::table('schedule_template_days')->insert(['template_id' => 1, 'day_of_week' => $dow, 'shift_id' => 2]);
        }

        DB::table('employees')->whereIn('id', [2, 3, 6])->update(['schedule_template_id' => 1]);
    }

    /** Sudah lewat jam masuk tapi belum clock-in → "Belum absen" (merah), bukan "Alpha". */
    public function test_today_column_marks_missing_clock_in_after_shift_start(): void
    {
        $this->seedSchedule();
        Carbon::setTestNow(Carbon::parse('2026-07-09 09:30:00')); // Kamis, lewat 08:00

        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance')
            ->assertOk()
            ->assertSee('Belum absen')
            ->assertSee('bg-red-50/30', false)          // baris disorot merah
            ->assertDontSee('Alpha</span>', false);     // hari berjalan tak boleh dicap alpha

        Carbon::setTestNow();
    }

    /** Sebelum jam masuk, yang belum absen bukan pelanggaran. */
    public function test_today_column_says_not_yet_time_before_shift_start(): void
    {
        $this->seedSchedule();
        Carbon::setTestNow(Carbon::parse('2026-07-09 06:30:00')); // Kamis, sebelum 08:00

        // "Belum absen" tetap muncul sebagai label kartu ringkasan; yang tidak boleh ada
        // adalah baris yang disorot merah.
        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance')
            ->assertOk()
            ->assertSee('Belum waktunya')
            ->assertDontSee('bg-red-50/30', false);

        Carbon::setTestNow();
    }

    public function test_today_column_shows_present_late_and_leave(): void
    {
        $this->seedSchedule();
        Carbon::setTestNow(Carbon::parse('2026-07-09 10:00:00'));

        DB::table('attendances')->insert([
            ['employee_id' => 2, 'date' => '2026-07-09', 'clock_in' => '07:55:00', 'clock_out' => null, 'status' => 'present', 'is_late' => false, 'created_at' => now(), 'updated_at' => now()],
            ['employee_id' => 3, 'date' => '2026-07-09', 'clock_in' => '09:20:00', 'clock_out' => '17:05:00', 'status' => 'present', 'is_late' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('leave_types')->insert(['id' => 1, 'name' => 'Cuti Tahunan', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('leave_requests')->insert([
            'employee_id' => 6, 'leave_type_id' => 1, 'start_date' => '2026-07-09', 'end_date' => '2026-07-09',
            'total_days' => 1, 'status' => 'approved', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance')
            ->assertOk()
            ->assertSee('Hadir')
            ->assertSee('07:55')
            ->assertSee('Terlambat')
            ->assertSee('09:20')
            ->assertSee('Cuti: Cuti Tahunan')
            ->assertSee('Sudah hadir'); // ringkasan hari ini

        Carbon::setTestNow();
    }

    /**
     * Daftar tim hanya tentang hari ini. Rekap bulanan pindah ke halaman per karyawan —
     * memuatnya di daftar berarti satu MonthlyAttendance::build() per anggota.
     */
    public function test_index_shows_only_today_and_no_monthly_recap(): void
    {
        $this->seedSchedule();
        Carbon::setTestNow(Carbon::parse('2026-07-09 09:30:00'));

        DB::table('attendances')->insert([
            'employee_id' => 2, 'date' => '2026-07-09', 'clock_in' => '07:50:00', 'status' => 'present',
            'is_late' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance?period=2026-05')  // periode diabaikan di daftar
            ->assertOk()
            ->assertSee('07:50')                    // jam absen hari ini
            ->assertSee('Status Hari Ini')
            ->assertDontSee('Rekap Mei 2026')       // tak ada rekap bulanan
            ->assertDontSee('Rekap periode')        // tak ada pemilih periode
            ->assertDontSee('type="month"', false);

        Carbon::setTestNow();
    }

    /** Rekap bulanan tetap ada — di halaman per karyawan, lengkap dengan pemilih periode. */
    public function test_member_detail_still_shows_monthly_recap_and_period_picker(): void
    {
        $this->seedSchedule();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance/2?period=2026-05')
            ->assertOk()
            ->assertSee('Mei 2026')
            ->assertSee('type="month"', false)
            ->assertSee('Hadir')
            ->assertSee('Alpha');
    }

    /** Daftar tim harus murah: query-nya konstan, tidak tumbuh seiring jumlah anggota. */
    public function test_index_query_count_does_not_grow_with_team_size(): void
    {
        $this->seedSchedule();

        // Normalkan: buang isi IN(...) & angka, supaya query sejenis terlihat sama.
        $ukur = function (): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withSession(['employee_id' => 1])->get('/employee/team-attendance')->assertOk();
            $log = DB::getQueryLog();
            DB::disableQueryLog();

            return array_map(
                fn ($q) => preg_replace(['/\bin \([^)]*\)/i', '/\d+/'], ['in (...)', 'N'], $q['query']),
                $log
            );
        };

        // Panaskan dulu: pemeriksaan Schema::hasTable hanya jalan sekali per container.
        $ukur();

        $sedikit = $ukur();

        for ($i = 10; $i < 25; $i++) {
            $this->employee($i, "Anggota {$i}", 'employee', departmentId: 3);
        }

        $banyak = $ukur();

        $this->assertSame(
            $sedikit,
            $banyak,
            "Query bertambah saat tim membesar (".count($sedikit)." → ".count($banyak)."):\n"
                .implode("\n", array_diff($banyak, $sedikit))
        );
    }

    /** Foto selfie & koordinat GPS tidak boleh ikut terkirim ke halaman manager. */
    public function test_member_detail_hides_photo_and_gps(): void
    {
        DB::table('attendances')->insert([
            'employee_id' => 2,
            'date' => '2026-07-06',
            'clock_in' => '08:05:00',
            'clock_out' => '17:00:00',
            'clock_in_photo' => 'foto-rahasia.jpg',
            'clock_in_lat' => 1.0456789,
            'clock_in_lng' => 104.0305123,
            'status' => 'present',
            'is_late' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->withSession(['employee_id' => 1])
            ->get('/employee/team-attendance/2?period=2026-07')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('08:05', $html);          // jam masuk tetap terlihat
        $this->assertStringNotContainsString('foto-rahasia.jpg', $html);
        $this->assertStringNotContainsString('1.0456789', $html);
        $this->assertStringNotContainsString('104.0305123', $html);
    }
}
