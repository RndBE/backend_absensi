<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\ClockinReminderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Jalur pengiriman reminder clock-in oleh backend (command clockin:remind →
 * ClockinReminderService::remindForNow): kirim WhatsApp + catat notifikasi in-app,
 * dengan dedup harian agar tidak dobel walau dipanggil tiap menit.
 *
 * Jadwal diresolusi bertingkat: override schedule_assignments menang atas hari libur
 * dan atas template mingguan; template dipakai untuk yang tak punya override.
 */
class ClockinReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.endpoint' => 'http://wa.test/send']);

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('fcm_token')->nullable();
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
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->unsignedBigInteger('shift_id');
            $table->string('notes')->nullable();
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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('date');
            $table->string('name');
            $table->boolean('is_national')->default(true);
            $table->timestamps();
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** 2026-07-03 = Jumat (ISO day_of_week 5). */
    private const DATE = '2026-07-03';

    private function company(): Company
    {
        return Company::firstOrCreate(['name' => 'PT Tessa']);
    }

    private function makeEmployee(string $email, ?string $phone, ?int $templateId = null): Employee
    {
        return Employee::create([
            'employee_code' => 'EMP-'.substr(md5($email), 0, 8),
            'company_id' => $this->company()->id,
            'schedule_template_id' => $templateId,
            'full_name' => 'Emp '.$email,
            'email' => $email,
            'phone' => $phone,
            'is_active' => true,
            'role' => 'employee',
        ]);
    }

    private function shift(bool $isOff = false, ?string $startTime = '08:00'): int
    {
        return DB::table('shifts')->insertGetId([
            'company_id' => $this->company()->id,
            'name' => $isOff ? 'Libur' : 'Pagi',
            'is_off' => $isOff,
            'start_time' => $isOff ? null : $startTime,
        ]);
    }

    private function assign(int $employeeId, int $shiftId): void
    {
        DB::table('schedule_assignments')->insert([
            'employee_id' => $employeeId, 'date' => self::DATE, 'shift_id' => $shiftId,
        ]);
    }

    /** Template mingguan yang memakai $shiftId pada hari yang sama dengan self::DATE. */
    private function template(int $shiftId): int
    {
        $templateId = DB::table('schedule_templates')->insertGetId([
            'company_id' => $this->company()->id, 'name' => 'Template Uji',
        ]);
        DB::table('schedule_template_days')->insert([
            'template_id' => $templateId,
            'day_of_week' => Carbon::parse(self::DATE)->dayOfWeekIso,
            'shift_id' => $shiftId,
        ]);

        return $templateId;
    }

    private function holiday(): void
    {
        DB::table('holidays')->insert([
            'company_id' => $this->company()->id, 'date' => self::DATE, 'name' => 'Tanggal Merah', 'is_national' => true,
        ]);
    }

    private function enable(int $before = 15): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'clockin_reminder_enabled'], ['value' => '1']);
        DB::table('settings')->updateOrInsert(['key' => 'clockin_reminder_before'], ['value' => (string) $before]);
    }

    public function test_sends_whatsapp_and_records_notification_then_dedups(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00')); // 15 menit sebelum 08:00
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $a = $this->makeEmployee('a@t.test', '08120000001');
        $this->assign($a->id, $work);

        $first = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $first['sent']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => $a->id,
            'type' => 'clockin_reminder',
        ]);

        // Panggilan kedua di menit berikutnya (masih dalam jendela) → dedup, tak kirim lagi.
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:46:00'));
        $second = ClockinReminderService::remindForNow(now());

        $this->assertSame(0, $second['sent']);
        Http::assertSentCount(1); // tetap 1
        $this->assertSame(1, Notification::where('type', 'clockin_reminder')->count());
    }

    public function test_skips_already_clocked_in_and_off_and_disabled(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse(self::DATE.' 08:00:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $off = $this->shift(true);

        $clockedIn = $this->makeEmployee('b@t.test', '08120000002');
        $libur = $this->makeEmployee('c@t.test', '08120000003');
        $this->assign($clockedIn->id, $work);
        $this->assign($libur->id, $off);
        DB::table('attendances')->insert(['employee_id' => $clockedIn->id, 'date' => self::DATE, 'clock_in' => '07:59:00']);

        $result = ClockinReminderService::remindForNow(now());

        $this->assertSame(0, $result['sent']); // sudah clock-in & libur → tak ada yang dikirim
        Http::assertNothingSent();
    }

    public function test_disabled_sends_nothing(): void
    {
        Http::fake();
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        DB::table('settings')->updateOrInsert(['key' => 'clockin_reminder_enabled'], ['value' => '0']);

        $work = $this->shift(false, '08:00');
        $a = $this->makeEmployee('a@t.test', '08120000001');
        $this->assign($a->id, $work);

        $result = ClockinReminderService::remindForNow(now());

        $this->assertSame(0, $result['sent']);
        Http::assertNothingSent();
    }

    /**
     * Inti perbaikan: karyawan yang jadwalnya HANYA dari template mingguan (tanpa baris
     * schedule_assignments) tetap harus diingatkan. Sebelumnya mereka tak pernah terlihat.
     */
    public function test_sends_to_employee_scheduled_via_weekly_template(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $template = $this->template($work);
        $a = $this->makeEmployee('tpl@t.test', '08120000010', $template);

        $result = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $result['sent']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => $a->id,
            'type' => 'clockin_reminder',
            'reference_id' => null, // jalur template tak punya assignment untuk dirujuk
        ]);
    }

    /** Endpoint Tessa (read-only) juga harus melihat karyawan bertemplate. */
    public function test_due_for_date_includes_template_employee(): void
    {
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $a = $this->makeEmployee('tpl2@t.test', '08120000011', $this->template($work));

        $due = ClockinReminderService::dueForDate(Carbon::parse(self::DATE), null, Carbon::parse(self::DATE.' 07:30:00'));

        $this->assertCount(1, $due);
        $this->assertSame($a->id, $due->first()['employee_id']);
        $this->assertSame('08:00', $due->first()['shift_start']);
    }

    /** Override per tanggal menang: template bilang kerja, assignment bilang OFF → tak dikirim. */
    public function test_assignment_override_off_beats_working_template(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $off = $this->shift(true);
        $a = $this->makeEmployee('ovr@t.test', '08120000012', $this->template($work));
        $this->assign($a->id, $off); // hari ini diliburkan lewat override

        $result = ClockinReminderService::remindForNow(now());

        $this->assertSame(0, $result['sent']);
        Http::assertNothingSent();
    }

    /**
     * Hari libur membatalkan jalur template, tapi TIDAK membatalkan override — mis. security
     * yang tetap dijadwalkan masuk pada tanggal merah.
     */
    public function test_holiday_skips_template_but_not_assignment(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);
        $this->holiday();

        $work = $this->shift(false, '08:00');
        $template = $this->template($work);

        $libur = $this->makeEmployee('hol@t.test', '08120000013', $template); // hanya template → libur
        $masuk = $this->makeEmployee('sec@t.test', '08120000014', $template); // override → tetap masuk
        $this->assign($masuk->id, $work);

        $result = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $result['sent']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('notifications', ['employee_id' => $masuk->id, 'type' => 'clockin_reminder']);
        $this->assertDatabaseMissing('notifications', ['employee_id' => $libur->id, 'type' => 'clockin_reminder']);
    }
}
