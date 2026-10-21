<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Setting;
use App\Support\HrContact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Kontak bantuan HRD untuk halaman login mobile: endpoint publik yang hanya mengeluarkan nomor
 * WhatsApp dan email. Sumbernya pengaturan admin, dengan cadangan nomor karyawan aktif ber-role
 * hr_admin (bukan teks jabatan).
 */
class ApiHrContactTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Setting::setValue menyimpan hasil cek kolom company_id secara statis; kosongkan supaya
        // test ini tidak bergantung pada urutan test lain di proses yang sama.
        (new ReflectionProperty(Setting::class, 'punyaKolomCompany'))->setValue(null, null);

        Schema::dropIfExists('settings');
        Schema::dropIfExists('employees');

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
    }

    private function employee(string $role, ?string $phone, bool $active = true, string $position = 'Staff', string $name = 'Karyawan'): Employee
    {
        $this->sequence++;

        return Employee::create([
            'employee_code' => 'EMP'.$this->sequence,
            'full_name' => $name.' '.$this->sequence,
            'email' => "karyawan{$this->sequence}@example.test",
            'password' => Hash::make('rahasia-123'),
            'phone' => $phone,
            'position' => $position,
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_returns_null_contact_when_nothing_is_configured(): void
    {
        $this->getJson('/api/hr-contact')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['whatsapp' => null, 'email' => null]]);
    }

    public function test_endpoint_is_public_and_needs_no_token(): void
    {
        Setting::setValue(HrContact::KEY_WHATSAPP, '6281234567890');

        $this->getJson('/api/hr-contact')->assertOk()->assertJsonPath('data.whatsapp', '6281234567890');
    }

    public function test_admin_setting_is_returned_in_whatsapp_format(): void
    {
        Setting::setValue(HrContact::KEY_WHATSAPP, '0812-3456-7890');
        Setting::setValue(HrContact::KEY_EMAIL, 'hrd@contoh.test');

        $this->getJson('/api/hr-contact')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['whatsapp' => '6281234567890', 'email' => 'hrd@contoh.test']]);
    }

    public function test_falls_back_to_the_first_active_hr_admin_with_a_valid_phone(): void
    {
        $this->employee('hr_admin', '0811 000 1111', active: false);        // nonaktif
        $this->employee('hr_admin', null);                                   // tanpa nomor
        $this->employee('hr_admin', '12');                                   // nomor tidak masuk akal
        $this->employee('employee', '0899 999 9999', position: 'Hrd');       // jabatan HRD tapi bukan hr_admin
        $this->employee('hr_admin', '0821-1112-2222');                       // ini yang dipakai
        $this->employee('hr_admin', '0822-3334-4444');

        $this->getJson('/api/hr-contact')
            ->assertOk()
            ->assertJsonPath('data.whatsapp', '6282111122222')
            ->assertJsonPath('data.email', null);
    }

    public function test_job_title_alone_never_makes_someone_the_contact(): void
    {
        $this->employee('employee', '0899 999 9999', position: 'Hrd');
        $this->employee('manager', '0898 888 8888', position: 'HR Manager');

        $this->getJson('/api/hr-contact')->assertOk()->assertJsonPath('data.whatsapp', null);
    }

    public function test_admin_setting_wins_over_the_hr_admin_fallback(): void
    {
        $this->employee('hr_admin', '0821-1112-2222');
        Setting::setValue(HrContact::KEY_WHATSAPP, '+62 811 5555 6666');

        $this->getJson('/api/hr-contact')->assertOk()->assertJsonPath('data.whatsapp', '628115555666'.'6');
    }

    public function test_email_setting_does_not_disable_the_whatsapp_fallback(): void
    {
        $this->employee('hr_admin', '0821-1112-2222');
        Setting::setValue(HrContact::KEY_EMAIL, 'hrd@contoh.test');

        $this->getJson('/api/hr-contact')
            ->assertOk()
            ->assertJsonPath('data.whatsapp', '6282111122222')
            ->assertJsonPath('data.email', 'hrd@contoh.test');
    }

    public function test_invalid_email_setting_is_dropped(): void
    {
        Setting::setValue(HrContact::KEY_EMAIL, 'bukan-email');

        $this->getJson('/api/hr-contact')->assertOk()->assertJsonPath('data.email', null);
    }

    public function test_response_never_contains_employee_identity(): void
    {
        $hr = $this->employee('hr_admin', '0821-1112-2222', name: 'Rahasia Banget');

        $response = $this->getJson('/api/hr-contact')->assertOk();

        $response->assertExactJson(['success' => true, 'data' => ['whatsapp' => '6282111122222', 'email' => null]]);
        $this->assertStringNotContainsString('Rahasia', $response->getContent());
        $this->assertStringNotContainsString($hr->email, $response->getContent());
    }

    public function test_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/hr-contact')->assertOk();
        }

        $this->getJson('/api/hr-contact')->assertStatus(429);
    }

    #[DataProvider('whatsappNumbers')]
    public function test_whatsapp_numbers_are_normalized(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, HrContact::normalizeWhatsapp($input));
    }

    public static function whatsappNumbers(): array
    {
        return [
            'format lokal dengan strip' => ['0812-3456-7890', '6281234567890'],
            'plus dan spasi' => ['+62 812 3456 7890', '6281234567890'],
            'sudah format WhatsApp' => ['6281234567890', '6281234567890'],
            'tanpa nol dan kode negara' => ['81234567890', '6281234567890'],
            'awalan panggilan internasional' => ['0062 812 3456 7890', '6281234567890'],
            'kode negara plus nol' => ['62 0812 3456 7890', '6281234567890'],
            'tanda kurung' => ['(0812) 3456-7890', '6281234567890'],
            'negara lain tetap dipakai' => ['+65 9123 4567', '6591234567'],
            'terlalu pendek' => ['0812', null],
            'terlalu panjang' => ['08123456789012345678', null],
            'huruf saja' => ['abc', null],
            'kosong' => ['', null],
            'null' => [null, null],
        ];
    }

    private function baseSettings(array $override = []): array
    {
        return array_merge([
            'office_latitude' => '1.0456',
            'office_longitude' => '104.0305',
            'office_radius_meters' => '100',
        ], $override);
    }

    public function test_admin_saves_hr_contact_and_whatsapp_is_stored_normalized(): void
    {
        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->put(route('admin.attendance-settings.update'), $this->baseSettings([
                'hr_contact_whatsapp' => '+62 812-3456-7890',
                'hr_contact_email' => ' hrd@contoh.test ',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('6281234567890', Setting::getValue(HrContact::KEY_WHATSAPP));
        $this->assertSame('hrd@contoh.test', Setting::getValue(HrContact::KEY_EMAIL));
    }

    public function test_admin_can_clear_the_contact_to_return_to_the_fallback(): void
    {
        Setting::setValue(HrContact::KEY_WHATSAPP, '6281234567890');

        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->put(route('admin.attendance-settings.update'), $this->baseSettings([
                'hr_contact_whatsapp' => '',
                'hr_contact_email' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('', Setting::getValue(HrContact::KEY_WHATSAPP));
        $this->getJson('/api/hr-contact')->assertOk()->assertJsonPath('data.whatsapp', null);
    }

    public function test_admin_rejects_an_invalid_whatsapp_number_or_email(): void
    {
        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->put(route('admin.attendance-settings.update'), $this->baseSettings([
                'hr_contact_whatsapp' => '12',
                'hr_contact_email' => 'bukan-email',
            ]))
            ->assertSessionHasErrors(['hr_contact_whatsapp', 'hr_contact_email']);

        $this->assertNull(Setting::getValue(HrContact::KEY_WHATSAPP));
    }

    public function test_saving_other_settings_without_the_contact_fields_keeps_the_contact(): void
    {
        Setting::setValue(HrContact::KEY_WHATSAPP, '6281234567890');

        $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->put(route('admin.attendance-settings.update'), $this->baseSettings())
            ->assertSessionHasNoErrors();

        $this->assertSame('6281234567890', Setting::getValue(HrContact::KEY_WHATSAPP));
    }
}
