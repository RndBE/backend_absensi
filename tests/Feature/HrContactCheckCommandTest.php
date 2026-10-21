<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Setting;
use App\Support\HrContact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * `php artisan hr-contact:check` menjawab "kenapa Hubungi HRD kosong?" dari data server, termasuk
 * alasan per karyawan (tidak aktif, HP kosong atau tidak valid, tidak memegang hr_admin).
 */
class HrContactCheckCommandTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReflectionProperty(Setting::class, 'punyaKolomCompany'))->setValue(null, null);

        foreach (['employee_roles', 'roles', 'settings', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('employee_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
        });

        foreach (['employee', 'admin', 'manager', 'hr_admin'] as $slug) {
            DB::table('roles')->insert([
                'name' => $slug,
                'slug' => $slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function employee(string $primaryRole, ?string $phone, array $attached = [], bool $active = true): Employee
    {
        $this->sequence++;

        $employee = Employee::create([
            'employee_code' => 'EMP-'.$this->sequence,
            'full_name' => 'Karyawan '.$this->sequence,
            'email' => "karyawan{$this->sequence}@example.test",
            'password' => Hash::make('rahasia'),
            'phone' => $phone,
            'role' => $primaryRole,
            'is_active' => $active,
        ]);

        foreach ($attached as $slug) {
            DB::table('employee_roles')->insert([
                'employee_id' => $employee->id,
                'role_id' => DB::table('roles')->where('slug', $slug)->value('id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $employee;
    }

    public function test_reports_the_attached_hr_admin_as_the_one_in_use(): void
    {
        $this->employee('admin', '0812 3456 7890', attached: ['admin', 'hr_admin']);

        $this->artisan('hr-contact:check')
            ->expectsOutputToContain('DIPAKAI')
            ->expectsOutputToContain('6281234567890')
            ->assertExitCode(0);
    }

    public function test_explains_why_a_holder_cannot_be_used(): void
    {
        $this->employee('admin', null, attached: ['hr_admin']);
        $this->employee('admin', 'bukan nomor', attached: ['hr_admin']);
        $this->employee('admin', '081200000001', attached: ['hr_admin'], active: false);

        $this->artisan('hr-contact:check')
            ->expectsOutputToContain('nomor HP kosong')
            ->expectsOutputToContain('nomor HP tidak valid ("bukan nomor")')
            ->expectsOutputToContain('karyawan tidak aktif')
            ->expectsOutputToContain('Kontak kosong')
            ->assertExitCode(0);
    }

    public function test_search_shows_a_person_who_does_not_hold_the_role(): void
    {
        $this->employee('admin', '081200000001', attached: ['admin']);
        DB::table('employees')->where('id', 1)->update(['full_name' => 'Avissa Contoh']);

        // Nama dan alasannya ada pada baris tabel yang sama; satu ekspektasi hanya bisa memakai satu
        // baris, jadi periksa keluaran utuhnya.
        $this->assertSame(0, Artisan::call('hr-contact:check', ['--cari' => 'avissa']));
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/Avissa Contoh.*tidak memegang role hr_admin/', $output);
    }

    public function test_reports_no_holder_at_all(): void
    {
        $this->employee('employee', '081200000001', attached: ['employee']);

        $this->artisan('hr-contact:check')
            ->expectsOutputToContain('Tidak ada karyawan yang memegang role hr_admin')
            ->assertExitCode(0);
    }

    public function test_shows_the_admin_setting_and_what_the_app_receives(): void
    {
        Setting::setValue(HrContact::KEY_WHATSAPP, '6285555555555');
        Setting::setValue(HrContact::KEY_EMAIL, 'hrd@contoh.test');

        $this->artisan('hr-contact:check')
            ->expectsOutputToContain('6285555555555')
            ->expectsOutputToContain('hrd@contoh.test')
            ->assertExitCode(0);
    }

    public function test_command_never_changes_data(): void
    {
        $this->employee('admin', '081200000001', attached: ['hr_admin']);
        $before = [DB::table('employees')->get()->toArray(), DB::table('settings')->get()->toArray()];

        $this->artisan('hr-contact:check', ['--cari' => 'karyawan'])->assertExitCode(0);

        $this->assertEquals($before, [DB::table('employees')->get()->toArray(), DB::table('settings')->get()->toArray()]);
    }

    public function test_diagnose_matches_what_the_endpoint_would_pick(): void
    {
        $this->employee('admin', null, attached: ['hr_admin']);
        $usable = $this->employee('admin', '0813 1111 2222', attached: ['hr_admin']);

        $this->assertSame('6281311112222', HrContact::diagnose($usable)['number']);
        $this->assertSame('6281311112222', $this->getJson('/api/hr-contact')->json('data.whatsapp'));
    }
}
