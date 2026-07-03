<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Endpoint sistem /tessa/reminders/due (service key) — daftar reminder jatuh tempo
 * untuk dikirim Tessa via WhatsApp. Fokus uji jalur clock-in + gate/validasi.
 */
class TessaReminderDueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tessa.api_key' => 'svc-key']);

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
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->timestamps();
        });
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->boolean('is_off')->default(false);
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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->timestamps();
        });
    }

    private const DATE = '2026-07-03';

    private function makeEmployee(string $email, ?string $phone): Employee
    {
        $company = Company::firstOrCreate(['name' => 'PT Tessa']);

        return Employee::create([
            'employee_code' => 'EMP-'.substr(md5($email), 0, 8),
            'company_id' => $company->id,
            'full_name' => 'Emp '.$email,
            'email' => $email,
            'phone' => $phone,
            'is_active' => true,
            'role' => 'employee',
        ]);
    }

    private function shift(bool $isOff = false): int
    {
        return DB::table('shifts')->insertGetId([
            'company_id' => Company::firstOrCreate(['name' => 'PT Tessa'])->id,
            'name' => $isOff ? 'Libur' : 'Pagi',
            'is_off' => $isOff,
        ]);
    }

    private function assign(int $employeeId, int $shiftId): void
    {
        DB::table('schedule_assignments')->insert([
            'employee_id' => $employeeId, 'date' => self::DATE, 'shift_id' => $shiftId,
        ]);
    }

    private function enableClockin(string $value = '1'): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'clockin_reminder_enabled'], ['value' => $value]);
    }

    public function test_requires_service_key(): void
    {
        $this->getJson('/api/tessa/reminders/due?type=clockin')->assertStatus(401);
    }

    public function test_invalid_type_rejected(): void
    {
        $this->getJson('/api/tessa/reminders/due?type=foo', ['X-Api-Key' => 'svc-key'])->assertStatus(422);
    }

    public function test_clockin_lists_scheduled_but_unclocked_employees(): void
    {
        $this->enableClockin();
        $work = $this->shift(false);
        $off = $this->shift(true);

        $a = $this->makeEmployee('a@t.test', '08120000001'); // kerja, belum clock-in → MUNCUL
        $b = $this->makeEmployee('b@t.test', '08120000002'); // kerja, sudah clock-in → keluar
        $c = $this->makeEmployee('c@t.test', '08120000003'); // libur → keluar
        $d = $this->makeEmployee('d@t.test', null);          // kerja tapi tanpa nomor → skipped_no_phone

        $this->assign($a->id, $work);
        $this->assign($b->id, $work);
        $this->assign($c->id, $off);
        $this->assign($d->id, $work);

        DB::table('attendances')->insert(['employee_id' => $b->id, 'date' => self::DATE, 'clock_in' => '08:05:00']);

        $resp = $this->getJson('/api/tessa/reminders/due?type=clockin&date='.self::DATE, ['X-Api-Key' => 'svc-key']);
        $resp->assertOk();

        $ids = collect($resp->json('reminders'))->pluck('employee_id')->all();
        $this->assertSame([$a->id], $ids);            // hanya A
        $this->assertSame(1, $resp->json('count'));
        $this->assertSame(1, $resp->json('skipped_no_phone')); // D tanpa nomor
    }

    public function test_clockin_disabled_returns_empty(): void
    {
        $this->enableClockin('0');
        $work = $this->shift(false);
        $a = $this->makeEmployee('a@t.test', '08120000001');
        $this->assign($a->id, $work);

        $resp = $this->getJson('/api/tessa/reminders/due?type=clockin&date='.self::DATE, ['X-Api-Key' => 'svc-key']);
        $resp->assertOk();
        $this->assertSame(0, $resp->json('count'));
    }
}
