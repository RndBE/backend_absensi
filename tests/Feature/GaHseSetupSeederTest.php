<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Vehicle;
use App\Support\AdminPermission;
use Database\Seeders\GaHseSetupSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder akun GA HSE: role sempit Surat Tugas + pemasangan ke karyawan + master Kendaraan & HT,
 * aman dijalankan berulang di server.
 */
class GaHseSetupSeederTest extends AssignmentLetterTestCase
{
    private const GA_HSE = self::PERMANENT;

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_05_26_100000_create_admin_permission_tables.php'))->up();
        (require database_path('migrations/2026_05_28_120000_create_role_master_tables.php'))->up();

        // Nomor karyawan GA HSE (Fadel di data uji) dan satu nomor yang tidak ada.
        config(['assignment_letters.ga_hse_employee_codes' => ['002/SOFTW/I/2024', '999/NONE/I/2024']]);
    }

    private function permissions(): AdminPermission
    {
        $this->app->forgetInstance(AdminPermission::class);

        return app(AdminPermission::class);
    }

    public function test_seeder_prepares_a_narrow_ga_hse_account(): void
    {
        $this->seed(GaHseSetupSeeder::class);

        $role = Role::where('slug', 'ga_hse')->sole();
        $this->assertSame('GA HSE', $role->name);
        $this->assertFalse($role->is_system);

        $employee = Employee::findOrFail(self::GA_HSE);
        $this->assertEqualsCanonicalizing(['employee', 'ga_hse'], $this->permissions()->roleSlugs($employee));
        $this->assertSame('employee', $employee->fresh()->role, 'kolom role lama tidak berubah');

        $permissions = $this->permissions();
        $this->assertTrue($permissions->isAdminUser($employee));
        foreach (['assignment_letters.view', 'assignment_letters.manage', 'assignment_letters.issue', 'assignment_facilities.manage'] as $allowed) {
            $this->assertTrue($permissions->can($employee, $allowed), $allowed);
        }
        foreach (['dashboard.view', 'employees.view', 'budget.view', 'security.permissions.manage'] as $denied) {
            $this->assertFalse($permissions->can($employee, $denied), $denied);
        }

        // Master kendaraan & HT ikut siap.
        $this->assertSame(7, Vehicle::where('company_id', 1)->count());

        // Karyawan lain tidak tersentuh.
        $this->assertSame(['employee'], $this->permissions()->roleSlugs(Employee::findOrFail(self::CONTRACT)));
    }

    public function test_seeder_is_safe_to_rerun_and_keeps_permission_edits(): void
    {
        $this->seed(GaHseSetupSeeder::class);

        // Admin mencabut hak terbitkan lewat halaman Role Permission.
        $this->permissions()->updateRole('ga_hse', ['assignment_letters.view', 'assignment_letters.manage']);

        $this->seed(GaHseSetupSeeder::class);

        $this->assertSame(1, Role::where('slug', 'ga_hse')->count());
        $this->assertSame(2, DB::table('employee_roles')->where('employee_id', self::GA_HSE)->count());
        $this->assertSame(7, Vehicle::count());

        $permissions = $this->permissions();
        $employee = Employee::findOrFail(self::GA_HSE);
        $this->assertTrue($permissions->can($employee, 'assignment_letters.manage'));
        $this->assertFalse($permissions->can($employee, 'assignment_letters.issue'));
    }

    public function test_inactive_employee_in_the_list_is_skipped(): void
    {
        // GA HSE lama sudah keluar, penggantinya ada di daftar yang sama.
        DB::table('employees')->where('id', self::GA_HSE)->update(['is_active' => false]);
        config(['assignment_letters.ga_hse_employee_codes' => ['002/SOFTW/I/2024', '003/SOFTW/I/2024']]);

        $this->seed(GaHseSetupSeeder::class);

        $this->assertSame(['employee'], $this->permissions()->roleSlugs(Employee::findOrFail(self::GA_HSE)));
        $this->assertEqualsCanonicalizing(['employee', 'ga_hse'], $this->permissions()->roleSlugs(Employee::findOrFail(self::CONTRACT)));
    }

    public function test_without_configured_employees_only_role_and_master_data_are_prepared(): void
    {
        config(['assignment_letters.ga_hse_employee_codes' => []]);

        $this->seed(GaHseSetupSeeder::class);

        $this->assertSame(1, Role::where('slug', 'ga_hse')->count());
        $this->assertSame(0, DB::table('employee_roles')->where('role_id', Role::where('slug', 'ga_hse')->value('id'))->count());
        $this->assertSame(7, Vehicle::count());
    }
}
