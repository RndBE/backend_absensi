<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Setting;
use App\Support\HrContact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Menjawab "kenapa 'Hubungi HRD' di halaman login mobile kosong?" dari data server.
 * Hanya membaca; tidak mengubah data.
 *
 *   php artisan hr-contact:check
 *   php artisan hr-contact:check --cari=avissa
 */
class HrContactCheck extends Command
{
    protected $signature = 'hr-contact:check {--cari= : Nama karyawan yang ingin diperiksa (mis. avissa)}';

    protected $description = 'Periksa sumber kontak "Hubungi HRD" di login mobile dan alasan bila kosong. Hanya membaca.';

    public function handle(): int
    {
        $this->info('1. Pengaturan admin (Pengaturan Presensi > Kontak Bantuan Login (HRD))');
        $this->line('   WhatsApp : '.(trim((string) Setting::getValue(HrContact::KEY_WHATSAPP, '')) ?: '(kosong)'));
        $this->line('   Email    : '.(trim((string) Setting::getValue(HrContact::KEY_EMAIL, '')) ?: '(kosong)'));
        $this->newLine();

        $this->info('2. Karyawan pemegang role hr_admin (cadangan WhatsApp)');
        $holders = $this->holders();
        if ($holders->isEmpty()) {
            $this->warn('   Tidak ada karyawan yang memegang role hr_admin.');
        } else {
            $chosen = null;
            $rows = [];
            foreach ($holders as $employee) {
                $result = HrContact::diagnose($employee);
                $status = $result['problem'] ?? ($chosen === null ? 'DIPAKAI' : 'cadangan berikutnya');
                if ($result['number'] !== null && $chosen === null) {
                    $chosen = $result['number'];
                }
                $rows[] = [
                    $employee->id,
                    $employee->full_name,
                    $employee->role ?: '-',
                    implode(', ', HrContact::roleSlugsOf($employee)),
                    $employee->phone ?: '-',
                    $status,
                ];
            }
            $this->table(['ID', 'Nama', 'Role utama', 'Role tercatat', 'HP', 'Status'], $rows);
        }

        if ($search = trim((string) $this->option('cari'))) {
            $this->newLine();
            $this->info("3. Pencarian \"{$search}\"");
            $found = Employee::query()->where('full_name', 'like', "%{$search}%")->orderBy('id')->get();
            if ($found->isEmpty()) {
                $this->warn('   Tidak ada karyawan dengan nama itu.');
            } else {
                $this->table(
                    ['ID', 'Nama', 'Aktif', 'Role utama', 'Role tercatat', 'HP', 'Hasil'],
                    $found->map(function (Employee $employee) {
                        $result = HrContact::diagnose($employee);

                        return [
                            $employee->id,
                            $employee->full_name,
                            $employee->is_active ? 'ya' : 'tidak',
                            $employee->role ?: '-',
                            implode(', ', HrContact::roleSlugsOf($employee)) ?: '-',
                            $employee->phone ?: '-',
                            $result['number'] !== null ? 'bisa jadi kontak' : $result['problem'],
                        ];
                    })->all(),
                );
            }
        }

        $resolved = HrContact::resolve();
        $this->newLine();
        $this->info('Yang dikirim ke aplikasi sekarang:');
        $this->line('   WhatsApp : '.($resolved['whatsapp'] ?? '(kosong)'));
        $this->line('   Email    : '.($resolved['email'] ?? '(kosong)'));

        if ($resolved['whatsapp'] === null && $resolved['email'] === null) {
            $this->newLine();
            $this->warn('Kontak kosong: "Hubungi HRD" di aplikasi hanya menampilkan penjelasan. Isi pengaturan admin atau perbaiki data di tabel di atas.');
        }

        return self::SUCCESS;
    }

    /** Karyawan yang tercatat hr_admin di kolom role atau di employee_roles, urut id. */
    private function holders()
    {
        $query = Employee::query()->orderBy('id')->where(function ($who) {
            $who->where('role', 'hr_admin');

            if (Schema::hasTable('roles') && Schema::hasTable('employee_roles')) {
                $who->orWhereHas('roles', fn ($role) => $role->where('roles.slug', 'hr_admin'));
            }
        });

        return $query->get();
    }
}
