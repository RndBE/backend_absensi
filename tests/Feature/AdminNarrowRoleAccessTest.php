<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Support\AdminPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Role sempit buatan admin (mis. GA HSE yang hanya mengurus surat tugas): bisa masuk admin tanpa
 * izin dashboard, langsung diarahkan ke halaman yang boleh dibuka, dan tiap aksi surat tugas
 * mengikuti permission-nya sendiri.
 */
class AdminNarrowRoleAccessTest extends AssignmentLetterTestCase
{
    private const GA_HSE = self::PERMANENT;

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_05_26_100000_create_admin_permission_tables.php'))->up();
        (require database_path('migrations/2026_05_28_120000_create_role_master_tables.php'))->up();

        Role::create(['name' => 'GA HSE', 'slug' => 'ga_hse', 'is_system' => false]);
        DB::table('employees')->where('id', self::GA_HSE)->update([
            'email' => 'ga@example.test',
            'password' => Hash::make('rahasia123'),
        ]);
        // Migrasi role mengisi employee_roles dari employees.role, jadi role Employee sudah ada.
        DB::table('employee_roles')->insert(['employee_id' => self::GA_HSE, 'role_id' => Role::where('slug', 'ga_hse')->value('id')]);
    }

    private function grant(array $permissions): void
    {
        $this->app->forgetInstance(AdminPermission::class);
        app(AdminPermission::class)->updateRole('ga_hse', $permissions);
        $this->app->forgetInstance(AdminPermission::class);
    }

    private function asGaHse(): static
    {
        $this->app->forgetInstance(AdminPermission::class);

        return $this->withSession(['admin_id' => self::GA_HSE]);
    }

    public function test_role_without_dashboard_logs_in_straight_to_assignment_letters(): void
    {
        $this->grant(['assignment_letters.view', 'assignment_letters.manage']);

        $this->post('/admin/login', ['email' => 'ga@example.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.assignment-letters.index'))
            ->assertSessionHas('admin_id', self::GA_HSE);

        // Logo, tombol kembali, dan halaman 403 menuju dashboard: dialihkan, bukan 403.
        $this->asGaHse()->get(route('admin.dashboard'))->assertRedirect(route('admin.assignment-letters.index'));

        $this->asGaHse()->get(route('admin.assignment-letters.index'))
            ->assertOk()
            ->assertSee(route('admin.assignment-letters.create'))
            ->assertDontSee(route('admin.assignment-facilities.index'))
            ->assertDontSee(route('admin.employees.index'));
        $this->asGaHse()->get(route('admin.assignment-letters.create'))->assertOk();
        $this->asGaHse()->get(route('admin.employees.index'))->assertForbidden();
    }

    public function test_role_without_any_admin_permission_is_still_rejected(): void
    {
        $this->grant([]);

        $this->from(route('admin.login'))
            ->post('/admin/login', ['email' => 'ga@example.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Akses admin ditolak.')
            ->assertSessionMissing('admin_id');
    }

    public function test_issuing_and_facilities_follow_their_own_permissions(): void
    {
        $this->grant(['assignment_letters.view', 'assignment_letters.manage']);

        $this->asGaHse()->post(route('admin.assignment-letters.store'), $this->payload())->assertSessionHasNoErrors();
        $letter = \App\Models\AssignmentLetter::latest('id')->firstOrFail();

        $this->asGaHse()->get(route('admin.assignment-letters.show', $letter->id))
            ->assertOk()
            ->assertSee(route('admin.assignment-letters.edit', $letter->id))
            ->assertDontSee(route('admin.assignment-letters.issue', $letter->id));
        $this->asGaHse()->post(route('admin.assignment-letters.issue', $letter->id))->assertForbidden();
        $this->asGaHse()->put(route('admin.assignment-letters.numbering'), ['year' => 2026, 'last_number' => 70])->assertForbidden();
        $this->asGaHse()->get(route('admin.assignment-facilities.index'))->assertForbidden();
        $this->assertNull($letter->fresh()->number);

        $this->grant(['assignment_letters.view', 'assignment_letters.manage', 'assignment_letters.issue', 'assignment_facilities.manage']);

        $this->asGaHse()->post(route('admin.assignment-letters.issue', $letter->id))->assertSessionHas('success');
        $this->assertNotNull($letter->fresh()->number);
        $this->asGaHse()->get(route('admin.assignment-facilities.index'))->assertOk();
    }
}
