<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Cadangan kontak HRD harus membaca role seperti sistem akses lainnya (AdminPermission::roleSlugs):
 * hr_admin biasanya MENEMPEL pada karyawan yang role utamanya lain (mis. admin) lewat tabel
 * employee_roles. Mengecek kolom employees.role saja membuat HR yang sebenarnya tidak ketemu.
 */
class ApiHrContactAttachedRoleTest extends TestCase
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

    private function whatsapp(): ?string
    {
        return $this->getJson('/api/hr-contact')->assertOk()->json('data.whatsapp');
    }

    public function test_hr_admin_attached_to_an_admin_is_found(): void
    {
        // Keadaan nyata: role utama admin, hr_admin menempel lewat employee_roles.
        $this->employee('admin', '0812 3456 7890', attached: ['admin', 'hr_admin']);

        $this->assertSame('6281234567890', $this->whatsapp());
    }

    public function test_column_only_hr_admin_without_attached_roles_still_counts(): void
    {
        $this->employee('hr_admin', '081200000001');

        $this->assertSame('6281200000001', $this->whatsapp());
    }

    public function test_attached_roles_decide_when_they_exist(): void
    {
        // Kolom bilang hr_admin, tetapi tabel role mencatat manager saja: yang tercatat menang,
        // sama seperti roleSlugs().
        $this->employee('hr_admin', '081200000001', attached: ['manager']);

        $this->assertNull($this->whatsapp());
    }

    public function test_someone_without_the_hr_admin_role_is_never_the_contact(): void
    {
        $this->employee('admin', '081200000001', attached: ['admin']);
        $this->employee('manager', '081200000002', attached: ['manager']);

        $this->assertNull($this->whatsapp());
    }

    public function test_inactive_and_phoneless_holders_are_skipped_for_the_next_one(): void
    {
        $this->employee('admin', '081200000001', attached: ['hr_admin'], active: false);
        $this->employee('admin', null, attached: ['hr_admin']);
        $this->employee('admin', '', attached: ['hr_admin']);
        $this->employee('admin', 'bukan nomor', attached: ['hr_admin']);
        $this->employee('admin', '0813 1111 2222', attached: ['hr_admin']);

        $this->assertSame('6281311112222', $this->whatsapp());
    }

    public function test_smallest_id_among_holders_wins(): void
    {
        $this->employee('employee', '081200000009', attached: ['employee']);
        $first = $this->employee('admin', '081200000003', attached: ['hr_admin']);
        $this->employee('admin', '081200000004', attached: ['hr_admin']);

        $this->assertSame('6281200000003', $this->whatsapp());
        $this->assertSame(2, $first->id);
    }

    public function test_admin_setting_still_wins_over_an_attached_hr_admin(): void
    {
        $this->employee('admin', '081200000001', attached: ['hr_admin']);
        Setting::setValue('hr_contact_whatsapp', '6285555555555');

        $this->assertSame('6285555555555', $this->whatsapp());
    }

    public function test_response_still_exposes_no_identity(): void
    {
        $this->employee('admin', '081200000001', attached: ['hr_admin']);

        $json = $this->getJson('/api/hr-contact')->assertOk()->json();

        $this->assertSame(['success', 'data'], array_keys($json));
        $this->assertSame(['whatsapp', 'email'], array_keys($json['data']));
        $this->assertStringNotContainsString('Karyawan', json_encode($json));
    }
}
