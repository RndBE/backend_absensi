<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Support\AdminPermission;
use Illuminate\Database\Seeder;

/**
 * Menyiapkan akun GA HSE untuk Surat Tugas supaya sudah siap begitu kode naik ke server:
 *
 *  1. role "GA HSE" (role buatan, bukan bawaan) dengan permission Surat Tugas saja:
 *     lihat/cetak, buat & ubah draft, terbitkan/batalkan, dan kelola Kendaraan & HT.
 *     Tanpa dashboard dan menu lain;
 *  2. role itu dipasang ke karyawan GA HSE yang masih aktif (daftar nomor karyawan di
 *     config/assignment_letters.php → ga_hse_employee_codes) di samping role Employee;
 *     kolom lama employees.role tidak diubah, jadi absen, cuti, dan anggaran mereka tetap sama.
 *     Karyawan nonaktif (sudah keluar) di daftar itu dilewati;
 *  3. master kendaraan & HT lewat AssignmentFacilitySeeder.
 *
 * Aman dijalankan berulang: permission role yang sudah pernah diatur lewat halaman Role Permission
 * tidak ditimpa, dan role yang sudah terpasang tidak dobel. Nomor surat terakhir sengaja tidak
 * diisi di sini; atur lewat tombol "Atur nomor terakhir" di halaman Surat Tugas.
 *
 *   php artisan db:seed --class=GaHseSetupSeeder
 */
class GaHseSetupSeeder extends Seeder
{
    public const ROLE_SLUG = 'ga_hse';

    private const PERMISSIONS = [
        'assignment_letters.view',
        'assignment_letters.manage',
        'assignment_letters.issue',
        'assignment_facilities.manage',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'like', '%Arta Teknologi%')->first();

        if (! $company) {
            $this->command?->error('Perusahaan PT Arta Teknologi Comunindo tidak ditemukan. Tidak ada yang diisi.');

            return;
        }

        $role = Role::firstOrCreate(
            ['slug' => self::ROLE_SLUG],
            ['name' => 'GA HSE', 'description' => 'Mengurus surat tugas, kendaraan, dan HT.', 'is_system' => false]
        );
        $this->command?->info($role->wasRecentlyCreated ? 'Role GA HSE dibuat.' : 'Role GA HSE sudah ada.');

        if ($role->permissions()->exists()) {
            $this->command?->info('Permission role GA HSE sudah pernah diatur, tidak ditimpa.');
        } else {
            app(AdminPermission::class)->updateRole(self::ROLE_SLUG, self::PERMISSIONS);
            $this->command?->info('Permission role GA HSE: '.implode(', ', self::PERMISSIONS).'.');
        }

        $this->assignRole($company, $role);

        $this->call(AssignmentFacilitySeeder::class);
    }

    private function assignRole(Company $company, Role $role): void
    {
        $codes = array_values(array_filter((array) config('assignment_letters.ga_hse_employee_codes', [])));
        if (! $codes) {
            $this->command?->warn('ga_hse_employee_codes kosong: role GA HSE belum dipasang ke siapa pun. Pasang lewat menu Role.');

            return;
        }

        $found = Employee::where('company_id', $company->id)->whereIn('employee_code', $codes)->get();
        [$employees, $inactive] = $found->partition(fn (Employee $employee) => (bool) $employee->is_active);
        $roleIds = Role::whereIn('slug', ['employee', self::ROLE_SLUG])->pluck('id')->all();

        foreach ($inactive as $employee) {
            $this->command?->warn("{$employee->full_name} ({$employee->employee_code}) sudah nonaktif; role GA HSE tidak dipasang.");
        }

        foreach ($employees as $employee) {
            $employee->roles()->syncWithoutDetaching($roleIds);
            $this->command?->info("Role GA HSE dipasang ke {$employee->full_name} ({$employee->employee_code}).");
        }

        foreach (array_diff($codes, $found->pluck('employee_code')->all()) as $missing) {
            $this->command?->warn("Karyawan dengan nomor {$missing} tidak ditemukan; role GA HSE tidak dipasang ke nomor itu.");
        }
    }
}
