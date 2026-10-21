<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Kontak bantuan HRD yang tampil di halaman login aplikasi mobile ("Lupa password? Hubungi HRD").
 *
 * Halaman login belum punya sesi, jadi datanya dibuka lewat endpoint publik. Karena itu hanya
 * nomor WhatsApp dan email yang keluar, tanpa nama atau id karyawan.
 *
 * Sumber, urut prioritas:
 * 1. Pengaturan "Kontak Bantuan Login (HRD)" di admin. Pilihan yang disengaja, bisa nomor kantor
 *    atau nomor bersama, bukan nomor pribadi siapa pun.
 * 2. Cadangan khusus WhatsApp: nomor HP karyawan aktif pemegang role hr_admin (id terkecil yang
 *    punya nomor valid). Teks jabatan tidak dipakai karena bebas ("Hrd", "Staff HR", "HR Intern").
 *    Role dibaca seperti AdminPermission::roleSlugs: hr_admin sering MENEMPEL pada karyawan yang
 *    role utamanya lain (mis. admin) lewat tabel employee_roles, jadi kolom employees.role saja
 *    tidak cukup.
 */
class HrContact
{
    public const KEY_WHATSAPP = 'hr_contact_whatsapp';

    public const KEY_EMAIL = 'hr_contact_email';

    /** @return array{whatsapp: ?string, email: ?string} */
    public static function resolve(): array
    {
        $whatsapp = self::normalizeWhatsapp((string) Setting::getValue(self::KEY_WHATSAPP, ''));

        return [
            'whatsapp' => $whatsapp ?? self::fallbackWhatsapp(),
            'email' => self::cleanEmail((string) Setting::getValue(self::KEY_EMAIL, '')),
        ];
    }

    /**
     * Nomor ke format WhatsApp (wa.me): digit saja dengan kode negara, tanpa "+". Menerima
     * "0812-3456-7890", "+62 812 3456 7890", "62812...", "812...", juga "62 0812..." (kode negara
     * ditambah 0 di depan). Mengembalikan null bila hasilnya bukan nomor yang masuk akal.
     */
    public static function normalizeWhatsapp(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2); // awalan panggilan internasional
        }

        if (str_starts_with($digits, '620')) {
            $digits = '62'.substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits; // 8xx tanpa 0 atau kode negara
        }

        $length = strlen($digits);

        return $length >= 9 && $length <= 15 ? $digits : null;
    }

    private static function cleanEmail(string $email): ?string
    {
        $email = trim($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function fallbackWhatsapp(): ?string
    {
        $query = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id');

        if (Schema::hasTable('roles') && Schema::hasTable('employee_roles')) {
            // Aturan yang sama dengan roleSlugs(): role di employee_roles yang menentukan; kolom
            // employees.role hanya dipakai bila karyawan belum punya baris di sana.
            $query->where(fn ($who) => $who
                ->whereHas('roles', fn ($role) => $role->where('roles.slug', 'hr_admin'))
                ->orWhere(fn ($legacy) => $legacy
                    ->where('role', 'hr_admin')
                    ->whereDoesntHave('roles')));
        } else {
            $query->where('role', 'hr_admin');
        }

        $phones = $query->pluck('phone');

        foreach ($phones as $phone) {
            if ($number = self::normalizeWhatsapp($phone)) {
                return $number;
            }
        }

        return null;
    }
}
