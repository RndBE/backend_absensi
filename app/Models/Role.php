<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /**
     * Nilai yang diterima kolom ENUM `employees.role` (lihat migrasi create_role_master_tables).
     * Role buatan admin tidak ada di sini: role itu hanya hidup di tabel `roles`/`employee_roles`
     * dan mengatur permission menu admin, bukan logika lama yang membaca `employees.role`.
     */
    public const LEGACY_SLUGS = ['superadmin', 'hr_admin', 'payroll_admin', 'finance_admin', 'manager', 'employee', 'admin'];

    protected $fillable = ['name', 'slug', 'description', 'is_system'];

    /** Nilai untuk kolom `employees.role`: role bawaan pertama yang dipilih, atau "employee". */
    public static function legacySlugFor(array $slugs): string
    {
        foreach ($slugs as $slug) {
            if (in_array($slug, self::LEGACY_SLUGS, true)) {
                return $slug;
            }
        }

        return 'employee';
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_roles')->withTimestamps();
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }
}
