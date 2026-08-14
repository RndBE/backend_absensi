<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\KpiWorkChainEditLog;
use App\Models\KpiWorkChainReviewer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Menerbitkan dan memverifikasi tautan tinjauan peta rantai kerja, serta mencatat perubahannya.
 *
 * Token asli hanya hidup sekali: dikembalikan `issue()` lalu dilupakan. Yang tersimpan `sha256`-nya,
 * mengikuti pola EmployeePortalMagicLink — kebocoran basis data tidak menyerahkan tautan yang bisa
 * dipakai orang lain.
 */
class KpiWorkChainReviewLinks
{
    /** Panjang token. 64 karakter acak — menebaknya tidak masuk hitungan praktis. */
    private const TOKEN_LENGTH = 64;

    public const DEFAULT_DAYS = 30;

    /**
     * Terbitkan tautan baru untuk satu orang. Tautan lamanya dicabut supaya hanya ada satu yang
     * berlaku per orang — kalau tidak, tautan yang sudah tersebar di grup obrolan tetap hidup
     * padahal admin merasa sudah menggantinya.
     *
     * @return array{reviewer: KpiWorkChainReviewer, url: string}  `url` hanya ada di sini, sekali.
     */
    public function issue(Employee $employee, int $days = self::DEFAULT_DAYS, ?int $createdBy = null): array
    {
        KpiWorkChainReviewer::where('employee_id', $employee->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = Str::random(self::TOKEN_LENGTH);

        $reviewer = KpiWorkChainReviewer::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays($days),
            'created_by' => $createdBy,
        ]);

        return [
            'reviewer' => $reviewer,
            'url' => route('kpi-review.show', ['token' => $token]),
        ];
    }

    /** Cari pemilik tautan dari token mentah. Null kalau tokennya tidak dikenal sama sekali. */
    public function resolve(string $token): ?KpiWorkChainReviewer
    {
        if ($token === '') {
            return null;
        }

        return KpiWorkChainReviewer::with('employee.department')
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /** Catat pembukaan halaman. Bukan untuk audit perubahan, hanya jejak pemakaian tautan. */
    public function markUsed(KpiWorkChainReviewer $reviewer, Request $request): void
    {
        $reviewer->forceFill([
            'last_used_at' => now(),
            'use_count' => $reviewer->use_count + 1,
            'last_ip_address' => $request->ip(),
        ])->save();
    }

    /**
     * Catat satu perubahan. Dipanggil dari kedua jalur — tautan tinjauan dan halaman admin —
     * supaya riwayatnya tidak terpecah.
     *
     * @param  array<string, mixed>  $detail
     */
    public function log(
        int $companyId,
        string $source,
        string $action,
        string $label,
        array $detail,
        Request $request,
        ?int $actorEmployeeId = null,
        ?int $reviewerId = null,
    ): void {
        KpiWorkChainEditLog::create([
            'company_id' => $companyId,
            'reviewer_id' => $reviewerId,
            'actor_employee_id' => $actorEmployeeId,
            'source' => $source,
            'action' => $action,
            'label' => mb_substr($label, 0, 80),
            'detail' => $detail,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
