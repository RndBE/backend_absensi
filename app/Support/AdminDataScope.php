<?php

namespace App\Support;

use App\Models\Employee;

/**
 * Pembatas data untuk tampilan admin berbasis role.
 *
 * Aturan: role **manager** hanya boleh melihat data **departemennya sendiri**.
 * Role lain (superadmin/hr_admin/finance_admin/dll) TIDAK dibatasi (lihat se-perusahaan).
 */
class AdminDataScope
{
    /**
     * ID departemen yang membatasi pandangan admin ini, atau null bila tak dibatasi.
     * Hanya manager yang punya departemen yang dibatasi.
     */
    public static function departmentId(?Employee $admin): ?int
    {
        if (! $admin || $admin->role !== 'manager') {
            return null;
        }

        return $admin->department_id ?: null;
    }

    /** Apakah admin ini dibatasi ke satu departemen. */
    public static function isDepartmentScoped(?Employee $admin): bool
    {
        return self::departmentId($admin) !== null;
    }
}
