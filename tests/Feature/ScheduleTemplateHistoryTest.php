<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeScheduleTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Employee::scheduleTemplateOn() — template yang BERLAKU pada suatu tanggal.
 *
 * Kasus nyata: karyawan 6 hari kerja (09:00, Sabtu masuk) pindah ke 5 hari kerja (08:00,
 * Sabtu off) pada 18 Mei 2026. Tanpa riwayat, template baru berlaku surut ke seluruh masa
 * lalunya — Sabtu-Sabtu sebelum 18 Mei ikut hilang, dan alpha/keterlambatan jadi salah.
 */
class ScheduleTemplateHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->date('join_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->timestamps();
        });
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->boolean('is_off')->default(false);
            $table->time('start_time')->nullable();
            $table->timestamps();
        });
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('description')->nullable();
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

            // Sama seperti migrasi: satu baris per karyawan per tanggal berlaku.
            $table->unique(['employee_id', 'effective_from']);
        });
    }

    private int $shiftPagi;   // 08:00, kerja

    private int $shiftSiang;  // 09:00, kerja

    private int $shiftOff;

    private function company(): Company
    {
        return Company::firstOrCreate(['name' => 'PT Tessa']);
    }

    private function shift(string $name, ?string $start, bool $isOff = false): int
    {
        return DB::table('shifts')->insertGetId([
            'company_id' => $this->company()->id, 'name' => $name, 'is_off' => $isOff, 'start_time' => $start,
        ]);
    }

    /** @param  array<int,int>  $daysToShift  day_of_week => shift_id */
    private function template(string $name, array $daysToShift): int
    {
        $id = DB::table('schedule_templates')->insertGetId([
            'company_id' => $this->company()->id, 'name' => $name,
        ]);
        foreach ($daysToShift as $dow => $shiftId) {
            DB::table('schedule_template_days')->insert([
                'template_id' => $id, 'day_of_week' => $dow, 'shift_id' => $shiftId,
            ]);
        }

        return $id;
    }

    /** Template 6 hari (Sen–Sab kerja 09:00) dan 5 hari (Sen–Jum kerja 08:00, Sab off). */
    private function seedTemplates(): array
    {
        $this->shiftPagi = $this->shift('Pagi', '08:00');
        $this->shiftSiang = $this->shift('Siang', '09:00');
        $this->shiftOff = $this->shift('Off', null, true);

        $enam = $this->template('6 Hari Kerja', [
            1 => $this->shiftSiang, 2 => $this->shiftSiang, 3 => $this->shiftSiang,
            4 => $this->shiftSiang, 5 => $this->shiftSiang, 6 => $this->shiftSiang,
            7 => $this->shiftOff,
        ]);
        $lima = $this->template('5 Hari Kerja', [
            1 => $this->shiftPagi, 2 => $this->shiftPagi, 3 => $this->shiftPagi,
            4 => $this->shiftPagi, 5 => $this->shiftPagi,
            6 => $this->shiftOff, 7 => $this->shiftOff,
        ]);

        return [$enam, $lima];
    }

    private function employee(?int $currentTemplate = null, array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code' => 'EMP-'.uniqid(),
            'company_id' => $this->company()->id,
            'schedule_template_id' => $currentTemplate,
            'full_name' => 'Akhmad',
            'email' => uniqid().'@t.test',
            'join_date' => '2023-01-03',
            'is_active' => true,
            'role' => 'employee',
        ], $attributes));
    }

    public function test_resolves_template_effective_on_that_date(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($lima); // penunjuk "sekarang" = 5 hari

        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $enam, 'effective_from' => '2023-01-03']);
        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $lima, 'effective_from' => '2026-05-18']);

        $this->assertSame($enam, $e->scheduleTemplateOn('2026-05-17')->id);
        $this->assertSame($lima, $e->scheduleTemplateOn('2026-05-18')->id); // hari pergantian
        $this->assertSame($lima, $e->scheduleTemplateOn('2026-06-01')->id);
    }

    /** Sabtu 23 Mei: dulu kerja (6 hari), sesudah pergantian jadi Off. */
    public function test_saturday_before_and_after_switch(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($lima);

        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $enam, 'effective_from' => '2023-01-03']);
        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $lima, 'effective_from' => '2026-05-18']);

        $sabtuSebelum = Carbon::parse('2026-05-16'); // Sabtu, masih 6 hari
        $sabtuSesudah = Carbon::parse('2026-05-23'); // Sabtu, sudah 5 hari

        $this->assertNotNull($e->templateShiftOn($sabtuSebelum));
        $this->assertNull($e->templateShiftOn($sabtuSesudah)); // Off → null
    }

    /** Jam masuk lampau dinilai terhadap template yang berlaku saat itu, bukan yang sekarang. */
    public function test_start_time_follows_history(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($lima);

        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $enam, 'effective_from' => '2023-01-03']);
        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $lima, 'effective_from' => '2026-05-18']);

        $this->assertSame('09:00', substr($e->templateShiftOn('2026-05-11')->start_time, 0, 5)); // Senin, 6 hari
        $this->assertSame('08:00', substr($e->templateShiftOn('2026-05-25')->start_time, 0, 5)); // Senin, 5 hari
    }

    /** Tanpa baris riwayat, perilaku lama dipertahankan: pakai schedule_template_id. */
    public function test_falls_back_to_current_pointer_when_no_history(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($enam);

        $this->assertSame($enam, $e->scheduleTemplateOn('2020-01-01')->id);
        $this->assertSame($enam, $e->scheduleTemplateOn('2026-06-01')->id);
    }

    /** Tanggal sebelum baris riwayat paling awal → tak ada template. */
    public function test_no_template_before_earliest_history_row(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($lima, ['join_date' => '2026-05-18']);

        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $lima, 'effective_from' => '2026-05-18']);

        $this->assertNull($e->scheduleTemplateOn('2026-05-17'));
        $this->assertNotNull($e->scheduleTemplateOn('2026-05-18'));
    }

    /** Riwayat tidak membatalkan guard masa kerja — keduanya harus berlaku. */
    public function test_employment_guard_still_applies(): void
    {
        [, $lima] = $this->seedTemplates();
        $e = $this->employee($lima, ['join_date' => '2026-06-01']);

        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $lima, 'effective_from' => '2020-01-01']);

        // Senin 2026-05-25: template berlaku, tapi ia belum bekerja.
        $this->assertNotNull($e->scheduleTemplateOn('2026-05-25'));
        $this->assertNull($e->templateShiftOn('2026-05-25'));
    }

    /**
     * Melepas template harus TEREKAM sebagai baris `template_id = null`. Tanpa itu, penunjuk
     * `schedule_template_id` dikosongkan sementara riwayat lama tetap berlaku — keduanya
     * bertentangan dan karyawan tetap dianggap bertemplate.
     */
    public function test_null_template_id_marks_employee_as_untemplated_from_that_date(): void
    {
        [, $lima] = $this->seedTemplates();
        $e = $this->employee($lima);

        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => $lima, 'effective_from' => '2026-06-24']);
        EmployeeScheduleTemplate::create(['employee_id' => $e->id, 'template_id' => null, 'effective_from' => '2026-07-01']);

        $this->assertNotNull($e->scheduleTemplateOn('2026-06-30'));
        $this->assertNull($e->scheduleTemplateOn('2026-07-01'));
        $this->assertNull($e->templateShiftOn('2026-07-02'));
    }

    /** applyScheduleTemplate() menulis riwayat DAN menyegarkan penunjuk "berlaku sekarang". */
    public function test_apply_schedule_template_writes_history_and_syncs_pointer(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee(null, ['join_date' => '2023-01-03']);

        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));

        $e->applyScheduleTemplate($enam, '2023-01-03');
        $this->assertSame($enam, $e->fresh()->schedule_template_id);

        // Pergantian yang BELUM berlaku tidak boleh menggeser penunjuk.
        $e->refresh()->applyScheduleTemplate($lima, '2026-06-15');
        $this->assertSame($enam, $e->fresh()->schedule_template_id);

        // Setelah tanggal berlakunya lewat, penunjuk ikut berpindah.
        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00'));
        $e->refresh()->applyScheduleTemplate($lima, '2026-06-15');
        $this->assertSame($lima, $e->fresh()->schedule_template_id);

        Carbon::setTestNow();
    }

    /**
     * Karyawan yang sudah bertemplate tapi BELUM punya baris riwayat (mis. dibuat setelah
     * migrasi, atau barisnya hilang) harus otomatis dapat baris dasar saat pergantian pertama
     * dicatat. Tanpa itu, seluruh tanggal sebelum pergantian kehilangan template.
     */
    public function test_first_change_seeds_baseline_row_from_existing_pointer(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($enam, ['join_date' => '2023-01-03']); // penunjuk terisi, riwayat kosong

        $this->assertSame(0, EmployeeScheduleTemplate::where('employee_id', $e->id)->count());

        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));
        $e->applyScheduleTemplate($lima, '2026-05-18');

        $rows = EmployeeScheduleTemplate::where('employee_id', $e->id)->orderBy('effective_from')->get();
        $this->assertCount(2, $rows);
        $this->assertSame($enam, $rows[0]->template_id);
        $this->assertSame('2023-01-03', $rows[0]->effective_from->toDateString());
        $this->assertSame($lima, $rows[1]->template_id);

        // Sebelum pergantian tetap memakai template lama, bukan "tanpa jadwal".
        $e->refresh();
        $this->assertSame($enam, $e->scheduleTemplateOn('2026-04-11')->id);
        $this->assertSame($lima, $e->scheduleTemplateOn('2026-05-18')->id);

        Carbon::setTestNow();
    }

    /** Karyawan yang memang belum pernah bertemplate tidak dibuatkan baris dasar palsu. */
    public function test_no_baseline_seeded_when_employee_never_had_template(): void
    {
        [, $lima] = $this->seedTemplates();
        $e = $this->employee(null, ['join_date' => '2023-01-03']);

        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));
        $e->applyScheduleTemplate($lima, '2026-05-18');

        $rows = EmployeeScheduleTemplate::where('employee_id', $e->id)->get();
        $this->assertCount(1, $rows);
        $this->assertNull($e->refresh()->scheduleTemplateOn('2026-04-11'));

        Carbon::setTestNow();
    }

    /**
     * Membolak-balik template pada tanggal yang sama harus kembali PERSIS ke bentuk semula —
     * baris yang tidak mengubah apa pun (template sama dengan yang sudah berlaku) dibuang.
     */
    public function test_flip_flopping_does_not_accumulate_rows(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($enam, ['join_date' => '2023-01-03']);

        Carbon::setTestNow(Carbon::parse('2026-07-09 09:00:00'));
        $e->applyScheduleTemplate($enam, '2023-01-03'); // baris dasar

        foreach ([$lima, $enam, $lima, $enam, $lima] as $t) {
            $e->refresh()->applyScheduleTemplate($t, '2026-05-18');
        }

        // Berakhir di template 5 hari → dasar + satu baris pergantian.
        $rows = EmployeeScheduleTemplate::where('employee_id', $e->id)->orderBy('effective_from')->get();
        $this->assertCount(2, $rows);
        $this->assertSame($enam, $rows[0]->template_id);
        $this->assertSame($lima, $rows[1]->template_id);

        // Kembalikan ke 6 hari → baris pergantian jadi mubazir dan dibuang.
        $e->refresh()->applyScheduleTemplate($enam, '2026-05-18');
        $this->assertCount(1, EmployeeScheduleTemplate::where('employee_id', $e->id)->get());

        // Masa lalu tetap utuh sepanjang proses.
        $this->assertSame($enam, $e->refresh()->scheduleTemplateOn('2026-04-11')->id);

        Carbon::setTestNow();
    }

    /** Tanggal berlaku yang salah bisa dikoreksi: baris usang dibuang otomatis. */
    public function test_correcting_a_wrong_effective_date_removes_the_stale_row(): void
    {
        [$enam, $lima] = $this->seedTemplates();
        $e = $this->employee($enam, ['join_date' => '2023-01-03']);

        Carbon::setTestNow(Carbon::parse('2026-07-09 09:00:00'));
        $e->applyScheduleTemplate($enam, '2023-01-03');

        $e->refresh()->applyScheduleTemplate($lima, '2026-06-01'); // tanggal keliru
        $e->refresh()->applyScheduleTemplate($lima, '2026-05-18'); // tanggal yang benar

        $rows = EmployeeScheduleTemplate::where('employee_id', $e->id)->orderBy('effective_from')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('2026-05-18', $rows[1]->effective_from->toDateString());
        $this->assertFalse(
            EmployeeScheduleTemplate::where('employee_id', $e->id)->where('effective_from', '2026-06-01')->exists()
        );

        $e->refresh();
        $this->assertSame($enam, $e->scheduleTemplateOn('2026-05-17')->id);
        $this->assertSame($lima, $e->scheduleTemplateOn('2026-05-25')->id);

        Carbon::setTestNow();
    }

    /** Melepas template lewat helper → penunjuk jadi null, riwayat merekam pelepasannya. */
    public function test_apply_null_template_clears_pointer(): void
    {
        [, $lima] = $this->seedTemplates();
        $e = $this->employee($lima, ['join_date' => '2023-01-03']);

        Carbon::setTestNow(Carbon::parse('2026-07-05 09:00:00'));

        $e->applyScheduleTemplate($lima, '2023-01-03');
        $e->refresh()->applyScheduleTemplate(null, '2026-07-01');

        $this->assertNull($e->fresh()->schedule_template_id);
        $this->assertNotNull($e->refresh()->scheduleTemplateOn('2026-06-30'));
        $this->assertNull($e->scheduleTemplateOn('2026-07-01'));

        Carbon::setTestNow();
    }
}
