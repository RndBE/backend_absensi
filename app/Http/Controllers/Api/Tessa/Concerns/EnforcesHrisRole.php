<?php

namespace App\Http\Controllers\Api\Tessa\Concerns;

use App\Models\Employee;
use App\Support\AdminPermission;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Penegak role HRIS untuk Tessa.
 *
 * Prinsip: Tessa TIDAK punya role sendiri. Aktor = karyawan pemilik token (hasil
 * login di /tessa/session), dan kapabilitasnya PERSIS mengikuti role HRIS orang itu
 * lewat resolver yang sama dengan website ({@see AdminPermission}).
 *
 * - Bukan admin (role employee) → hanya self-service & data miliknya sendiri.
 * - Admin (role selain employee) → sesuai permission-nya.
 * - Payroll tetap terkunci di middleware, apa pun role-nya.
 */
trait EnforcesHrisRole
{
    protected function actor(): ?Employee
    {
        $user = request()->user();

        return $user instanceof Employee ? $user : null;
    }

    protected function permission(): AdminPermission
    {
        return app(AdminPermission::class);
    }

    /** Aktor tergolong pengguna admin (boleh fitur admin), bukan sekadar employee. */
    protected function actorIsAdmin(): bool
    {
        $actor = $this->actor();

        return $actor ? $this->permission()->isAdminUser($actor) : false;
    }

    /** Hentikan (403 JSON) bila role aktor tak punya satu pun permission yang diminta. */
    protected function requirePermission(string ...$permissions): void
    {
        $actor = $this->actor();

        if (! $actor || ! $this->permission()->canAny($actor, $permissions)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Role Anda di HRIS tidak mengizinkan aksi ini.',
            ], 403));
        }
    }

    /** Scope perusahaan = perusahaan aktor (tiap pengguna Tessa terikat perusahaannya). */
    protected function companyId(Request $request): ?int
    {
        return $this->actor()?->company_id;
    }

    /**
     * employee_id efektif untuk endpoint baca data personal:
     * - non-admin DIPAKSA hanya melihat datanya sendiri
     * - admin boleh memakai filter ?employee_id (atau semua bila kosong)
     */
    protected function scopedEmployeeId(Request $request): ?int
    {
        if (! $this->actorIsAdmin()) {
            return $this->actor()?->id;
        }

        $id = $request->query('employee_id');

        return $id ? (int) $id : null;
    }
}
