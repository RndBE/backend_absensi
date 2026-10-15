<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EmployeeController;
use App\Models\Employee;
use App\Models\Role;
use App\Support\AdminPermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tab Role di halaman Role Permission: tambah, ubah, hapus role, dan pemakaian role buatan admin.
 * Tabel role dibuat lewat migrasi aslinya supaya perilaku role_id/slug ikut teruji.
 */
class AdminRoleManagementTest extends TestCase
{
    private const SUPERADMIN = 1;
    private const STAFF = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        (require database_path('migrations/2026_05_26_100000_create_admin_permission_tables.php'))->up();
        (require database_path('migrations/2026_05_28_120000_create_role_master_tables.php'))->up();

        DB::table('employees')->insert([
            ['id' => self::SUPERADMIN, 'full_name' => 'Super Admin', 'role' => 'superadmin'],
            ['id' => self::STAFF, 'full_name' => 'Staf GA', 'role' => 'employee'],
        ]);
        $this->assignRoles(self::SUPERADMIN, ['superadmin']);
        $this->assignRoles(self::STAFF, ['employee']);
    }

    private function assignRoles(int $employeeId, array $slugs): void
    {
        DB::table('employee_roles')->where('employee_id', $employeeId)->delete();
        foreach (Role::whereIn('slug', $slugs)->pluck('id') as $roleId) {
            DB::table('employee_roles')->insert(['employee_id' => $employeeId, 'role_id' => $roleId]);
        }
    }

    private function asSuperadmin(): static
    {
        $this->app->forgetInstance(AdminPermission::class);

        return $this->withSession(['admin_id' => self::SUPERADMIN]);
    }

    private function permissions(): AdminPermission
    {
        $this->app->forgetInstance(AdminPermission::class);

        return app(AdminPermission::class);
    }

    public function test_page_has_permission_and_role_tabs(): void
    {
        $this->asSuperadmin()->get(route('admin.role-permissions.index'))
            ->assertOk()
            ->assertSee('Atur Permission')
            ->assertSee('?tab=role', false);

        $this->asSuperadmin()->get(route('admin.role-permissions.index', ['tab' => 'role']))
            ->assertOk()
            ->assertSee('Daftar Role')
            ->assertSee('Tambah Role')
            ->assertSee('hr_admin')
            ->assertSee('Bawaan sistem');
    }

    public function test_admin_can_add_role_and_copy_permissions(): void
    {
        $this->asSuperadmin()->post(route('admin.roles.store'), [
            'name' => 'GA & HSE',
            'description' => 'Surat tugas, kendaraan, HT',
            'copy_from' => 'manager',
        ])->assertRedirect(route('admin.role-permissions.index', ['tab' => 'role']));

        $role = Role::where('name', 'GA & HSE')->sole();
        $this->assertSame('ga_hse', $role->slug);
        $this->assertFalse($role->is_system);

        $permissions = $this->permissions();
        $this->assertArrayHasKey('ga_hse', $permissions->roles());
        $this->assertSame(
            array_keys(array_filter($permissions->roleState('manager'))),
            array_keys(array_filter($permissions->roleState('ga_hse')))
        );
        $this->assertContains('ga_hse', $permissions->editableRoles());
    }

    public function test_names_must_be_unique_and_slugs_never_collide(): void
    {
        $this->asSuperadmin()->post(route('admin.roles.store'), ['name' => 'HR Admin'])
            ->assertSessionHasErrors('name');

        // Beda nama, tapi slug-nya sama dengan role bawaan: diberi akhiran.
        $this->asSuperadmin()->post(route('admin.roles.store'), ['name' => 'hr-admin'])->assertSessionHasNoErrors();
        $this->assertSame('hr_admin_2', Role::where('name', 'hr-admin')->value('slug'));

        // "Admin" adalah nilai lama employees.role walau tidak ada di tabel roles.
        $this->asSuperadmin()->post(route('admin.roles.store'), ['name' => 'Admin'])->assertSessionHasNoErrors();
        $this->assertSame('admin_2', Role::where('name', 'Admin')->value('slug'));
    }

    public function test_role_can_be_renamed_but_slug_and_superadmin_are_fixed(): void
    {
        $manager = Role::where('slug', 'manager')->sole();
        $this->asSuperadmin()->put(route('admin.roles.update', $manager), ['name' => 'Kepala Divisi', 'description' => 'Level 2'])
            ->assertSessionHas('success');
        $this->assertSame(['Kepala Divisi', 'manager'], [$manager->fresh()->name, $manager->fresh()->slug]);

        $superadmin = Role::where('slug', 'superadmin')->sole();
        $this->asSuperadmin()->put(route('admin.roles.update', $superadmin), ['name' => 'Raja'])
            ->assertSessionHas('error');
        $this->assertSame('Superadmin', $superadmin->fresh()->name);
    }

    public function test_only_unused_custom_roles_can_be_deleted(): void
    {
        $system = Role::where('slug', 'finance_admin')->sole();
        $this->asSuperadmin()->delete(route('admin.roles.destroy', $system))->assertSessionHas('error');
        $this->assertNotNull($system->fresh());

        $this->asSuperadmin()->post(route('admin.roles.store'), ['name' => 'GA & HSE', 'copy_from' => 'manager']);
        $custom = Role::where('slug', 'ga_hse')->sole();
        $this->assignRoles(self::STAFF, ['ga_hse']);

        $this->asSuperadmin()->delete(route('admin.roles.destroy', $custom))->assertSessionHas('error');
        $this->assertNotNull($custom->fresh());

        $this->assignRoles(self::STAFF, ['employee']);
        $this->asSuperadmin()->delete(route('admin.roles.destroy', $custom))->assertSessionHas('success');
        $this->assertNull($custom->fresh());
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $custom->id)->orWhere('role', 'ga_hse')->count());
    }

    public function test_custom_role_grants_admin_access_without_touching_legacy_role_column(): void
    {
        $this->asSuperadmin()->post(route('admin.roles.store'), ['name' => 'GA & HSE']);
        $this->permissions()->updateRole('ga_hse', ['dashboard.view', 'assignment_letters.view', 'assignment_letters.manage']);

        $this->asSuperadmin()->put(route('admin.roles.employees.update', self::STAFF), ['roles' => ['ga_hse']])
            ->assertSessionHas('success');

        $staff = Employee::findOrFail(self::STAFF);
        // Kolom ENUM lama tidak menerima slug baru; diisi nilai bawaan.
        $this->assertSame('employee', $staff->role);

        $permissions = $this->permissions();
        $this->assertTrue($permissions->isAdminUser($staff));
        $this->assertTrue($permissions->can($staff, 'assignment_letters.manage'));
        $this->assertFalse($permissions->can($staff, 'payroll.runs.view'));

        // Role bawaan pertama yang dipilih tetap mengisi kolom lama.
        $this->asSuperadmin()->put(route('admin.roles.employees.update', self::STAFF), ['roles' => ['ga_hse', 'manager']]);
        $this->assertSame('manager', $staff->fresh()->role);
    }

    public function test_saving_employee_data_keeps_custom_roles(): void
    {
        $this->asSuperadmin()->post(route('admin.roles.store'), ['name' => 'GA & HSE']);
        $this->assignRoles(self::STAFF, ['employee', 'ga_hse']);
        $staff = Employee::findOrFail(self::STAFF);

        // Form karyawan hanya memilih role utama (bawaan); role buatan admin tidak boleh hilang.
        (fn () => $this->syncPrimaryRole($staff, 'manager'))->call(new EmployeeController());

        $this->assertEqualsCanonicalizing(['manager', 'ga_hse'], $staff->roles()->pluck('slug')->all());
    }
}
