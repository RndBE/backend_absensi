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

    private function makeEmployee(string $email, ?string $phone, ?int $templateId = null, array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code' => 'EMP-'.substr(md5($email), 0, 8),
            'company_id' => $this->company()->id,
            'schedule_template_id' => $templateId,
            'full_name' => 'Emp '.$email,
            'email' => $email,
            'phone' => $phone,
            'is_active' => true,
            'role' => 'employee',
        ], $attributes));
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

        $this->assertSame(1, $first['in_app']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => $a->id,
            'type' => 'clockin_reminder',
        ]);

        // Panggilan kedua di menit berikutnya (masih dalam jendela) → dedup, tak kirim lagi.
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:46:00'));
        $second = ClockinReminderService::remindForNow(now());

        $this->assertSame(0, $second['in_app']);
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

        $this->assertSame(0, $result['in_app']); // sudah clock-in & libur → tak ada yang dikirim
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

        $this->assertSame(0, $result['in_app']);
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

        $this->assertSame(1, $result['in_app']);
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

        $this->assertSame(0, $result['in_app']);
        Http::assertNothingSent();
    }

    /**
     * Template mingguan tak punya masa berlaku. Karyawan yang baru masuk minggu depan, tapi
     * sudah di-assign template, TIDAK boleh diingatkan clock-in sebelum hari pertamanya.
     */
    public function test_skips_employee_who_has_not_joined_yet(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $template = $this->template($work);

        $belumMasuk = $this->makeEmployee('baru@t.test', '08120000015', $template, ['join_date' => '2026-07-10']);
        $sudahMasuk = $this->makeEmployee('lama@t.test', '08120000016', $template, ['join_date' => '2024-01-01']);

        $result = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $result['in_app']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('notifications', ['employee_id' => $sudahMasuk->id, 'type' => 'clockin_reminder']);
        $this->assertDatabaseMissing('notifications', ['employee_id' => $belumMasuk->id, 'type' => 'clockin_reminder']);
    }

    /** Karyawan yang sudah lewat hari kerja terakhirnya juga tak diingatkan. */
    public function test_skips_employee_past_last_working_date(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $keluar = $this->makeEmployee('keluar@t.test', '08120000017', $this->template($work), [
            'join_date' => '2024-01-01', 'last_working_date' => '2026-07-02',
        ]);

        $result = ClockinReminderService::remindForNow(now());

        $this->assertSame(0, $result['in_app']);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('notifications', ['employee_id' => $keluar->id, 'type' => 'clockin_reminder']);
    }

    /**
     * WA gagal → JANGAN catat notifikasi in-app. Baris itu penanda dedup harian; kalau
     * dibuat, orangnya dianggap "sudah diingatkan" padahal tak ada pesan yang sampai, dan
     * tak akan dicoba ulang hari itu.
     */
    public function test_whatsapp_failure_does_not_consume_daily_dedup(): void
    {
        // fakeSequence: panggilan pertama gagal (gateway mati), kedua berhasil.
        // Http::fake() yang dipanggil dua kali justru MENAMBAH stub — yang pertama tetap menang.
        Http::fakeSequence()
            ->push(['error' => 'gateway mati'], 500)
            ->push(['ok' => true], 200);

        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $a = $this->makeEmployee('a@t.test', '08120000001');
        $this->assign($a->id, $work);

        $gagal = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $gagal['wa_failed'], 'HTTP 500 harus dihitung gagal, bukan sukses');
        $this->assertSame(0, $gagal['wa_sent']);
        $this->assertSame(0, $gagal['in_app'], 'notifikasi tak boleh dibuat saat WA gagal');
        $this->assertSame(0, Notification::where('type', 'clockin_reminder')->count());

        // Menit berikutnya gateway hidup → dicoba ulang dan berhasil.
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:46:00'));

        $berhasil = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $berhasil['wa_sent']);
        $this->assertSame(1, $berhasil['in_app']);
        $this->assertSame(1, Notification::where('type', 'clockin_reminder')->count());
    }

    /**
     * Pengaman: kalau gateway mati sepanjang jendela, pada menit TERAKHIR notifikasi in-app
     * tetap dibuat — kalau tidak, karyawan itu tak dapat apa pun sama sekali.
     */
    public function test_last_minute_of_window_still_records_in_app_notification(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $a = $this->makeEmployee('a@t.test', '08120000001');
        $this->assign($a->id, $work);

        // Jendela berakhir 08:30 (jam masuk + 30 menit toleransi).
        Carbon::setTestNow(Carbon::parse(self::DATE.' 08:00:00'));
        $this->assertSame(0, ClockinReminderService::remindForNow(now())['in_app']);

        Carbon::setTestNow(Carbon::parse(self::DATE.' 08:29:30'));
        $akhir = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $akhir['wa_failed']);
        $this->assertSame(1, $akhir['in_app'], 'menit terakhir harus tetap mencatat in-app');
        $this->assertSame(1, Notification::where('type', 'clockin_reminder')->count());
    }

    /** Karyawan tanpa nomor HP: WA tak relevan, in-app tetap dicatat sekali. */
    public function test_employee_without_phone_still_gets_in_app_notification(): void
    {
        Http::fake();
        Carbon::setTestNow(Carbon::parse(self::DATE.' 07:45:00'));
        $this->enable(15);

        $work = $this->shift(false, '08:00');
        $a = $this->makeEmployee('nohp@t.test', null);
        $this->assign($a->id, $work);

        $hasil = ClockinReminderService::remindForNow(now());

        $this->assertSame(1, $hasil['in_app']);
        $this->assertSame(0, $hasil['wa_sent']);
        $this->assertSame(0, $hasil['wa_failed']);
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

        $this->assertSame(1, $result['in_app']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('notifications', ['employee_id' => $masuk->id, 'type' => 'clockin_reminder']);
        $this->assertDatabaseMissing('notifications', ['employee_id' => $libur->id, 'type' => 'clockin_reminder']);
    }
}
