<?php

namespace App\Support;

use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Persetujuan (paraf digital) surat tugas.
 *
 *   draft ──terbitkan──▶ terbit (nomor keluar, peserta diberi tahu)
 *                          └─ saat itu juga kotak paraf diminta ke pemberi persetujuan:
 *                             atasan L3/L2, HRD, dan GA HSE sekaligus; tiap yang setuju mengisi
 *                             parafnya dengan tanda tangan digital. Surat dicetak untuk tanda
 *                             tangan basah Direktur, lalu hasil pindainya diunggah.
 *
 * Terbit lebih dulu, persetujuan menyusul: nomor surat tidak bergantung pada kecepatan atasan
 * memutuskan, dan isi surat boleh diperbaiki selama belum ada pindaian bertanda tangan.
 *
 * Tiga grup yang semuanya terbuka sejak surat terbit (tidak saling menunggu):
 *   1. atasan L3 dan L2 tiap peserta (rantai approval cuti), tanpa duplikat;
 *   2. HRD (pemegang izin assignment_letters.approve_hrd);
 *   3. GA HSE (pemegang izin assignment_letters.approve_hse).
 * Surat baru boleh dipindai setelah ketiganya selesai. Direktur tidak ikut: ia menandatangani
 * surat secara basah.
 *
 * Penolakan tidak membatalkan surat: kotak itu ditandai ditolak beserta alasannya, HRD
 * memperbaiki surat lalu "Ajukan ulang" (hanya yang menolak memutuskan lagi). Persetujuan
 * yang sudah masuk tidak diulang saat isi surat diubah; bila peserta berubah, atasan yang
 * baru diminta paraf dan atasan yang tidak lagi relevan dilepas (resync).
 */
final class AssignmentLetterApprovalFlow
{
    public const PERMISSION_HRD = 'assignment_letters.approve_hrd';
    public const PERMISSION_HSE = 'assignment_letters.approve_hse';

    public static function required(): bool
    {
        return (bool) config('assignment_letters.require_approval', true);
    }

    /**
     * Baris approval yang akan dibuat untuk surat ini, dan peserta yang belum bisa dicarikan atasan.
     *
     * @return array{rows: array<int, array<string, mixed>>, problems: array<int, string>}
     */
    public static function plan(AssignmentLetter $letter): array
    {
        $letter->loadMissing('employees');
        $ids = $letter->employees->pluck('employee_id')->map(fn ($id) => (int) $id)->all();

        $superiors = AssignmentLetterInitialBoxes::superiorsFor((int) $letter->company_id, $ids);
        $people = Employee::whereIn('id', $ids)->get(['id', 'full_name', 'job_level'])->keyBy('id');

        // Karyawan di bawah level 2 wajib punya atasan L3/L2. Level 2 ke atas memang tak butuh.
        $problems = [];
        foreach ($ids as $id) {
            $level = (int) ($people[$id]->job_level ?? 0);
            if (($level === 0 || $level > 2) && empty($superiors[$id])) {
                $problems[] = $people[$id]->full_name ?? "ID {$id}";
            }
        }

        $rows = [];
        foreach (AssignmentLetterInitialBoxes::slots($superiors) as $slot) {
            $rows[] = [
                'role' => $slot['level'] === 3 ? AssignmentLetterApproval::ROLE_LEVEL3 : AssignmentLetterApproval::ROLE_LEVEL2,
                'step_group' => AssignmentLetterApproval::GROUP_SUPERIORS,
                'label' => $slot['label'],
                'approver_id' => $slot['id'],
            ];
        }

        $fixed = array_values((array) config('assignment_letters.fixed_initial_boxes', ['HRD', 'GA HSE']));
        $rows[] = [
            'role' => AssignmentLetterApproval::ROLE_HRD,
            'step_group' => AssignmentLetterApproval::GROUP_HRD,
            'label' => $fixed[0] ?? 'HRD',
            'approver_id' => null,
        ];
        $rows[] = [
            'role' => AssignmentLetterApproval::ROLE_GA_HSE,
            'step_group' => AssignmentLetterApproval::GROUP_GA_HSE,
            'label' => $fixed[1] ?? 'GA HSE',
            'approver_id' => null,
        ];

        foreach ($rows as $index => &$row) {
            $row['sort_order'] = $index;
        }
        unset($row);

        return ['rows' => $rows, 'problems' => $problems];
    }

    /** Pesan bila peserta belum bisa dicarikan atasan (surat belum boleh terbit), selain itu null. */
    public static function problemMessage(array $plan): ?string
    {
        if (! $plan['problems']) {
            return null;
        }

        return 'Atasan level 3/2 belum terdaftar di rantai approval cuti untuk: '.implode(', ', $plan['problems'])
            .'. Lengkapi rantai approval karyawan itu (menu Karyawan → Approver) atau keluarkan dari surat.';
    }

    /**
     * Buat kotak paraf untuk surat yang baru terbit. Dipanggil di dalam transaksi penerbitan;
     * pemberitahuan ke pemberi persetujuan dikirim sesudah transaksi lewat start().
     */
    public static function createRows(AssignmentLetter $letter, Employee $actor): void
    {
        $letter->approvals()->delete();

        foreach (self::plan($letter)['rows'] as $row) {
            $letter->approvals()->create($row);
        }

        $letter->update([
            'submitted_at' => now(),
            'submitted_by' => $actor->id,
            'approved_at' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
        ]);
    }

    /** Beri tahu pemberi persetujuan di grup pertama yang terbuka; dipanggil sesudah surat terbit. */
    public static function start(AssignmentLetter $letter): void
    {
        self::advance($letter);
    }

    /**
     * Setelah HRD memperbaiki surat yang kotaknya ditolak: kotak yang ditolak diminta memutuskan lagi.
     * Kotak lain yang sudah menyetujui tidak diulang.
     */
    public static function resubmit(AssignmentLetter $letter, Employee $actor): array
    {
        $result = DB::transaction(function () use ($letter, $actor) {
            $letter = AssignmentLetter::whereKey($letter->id)->lockForUpdate()->firstOrFail();

            if (! $letter->isIssued() || $letter->hasSignedFile()) {
                return ['error' => 'Surat ini sudah tidak bisa diajukan ulang (belum terbit, dibatalkan, atau sudah ada pindaian bertanda tangan).'];
            }

            $rejected = $letter->approvals()->where('status', AssignmentLetterApproval::STATUS_REJECTED)->get();

            if ($rejected->isEmpty()) {
                return ['error' => 'Tidak ada persetujuan yang ditolak.'];
            }

            foreach ($rejected as $approval) {
                $approval->update([
                    'status' => AssignmentLetterApproval::STATUS_PENDING,
                    'acted_by' => null,
                    'acted_at' => null,
                    'notes' => null,
                    'signature_path' => null,
                ]);
            }

            $letter->update([
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return ['letter' => $letter, 'reopened' => $rejected->pluck('id')->all()];
        });

        if (isset($result['letter'])) {
            self::advance($result['letter'], null, $result['reopened']);
        }

        return $result;
    }

    /**
     * Sesuaikan kotak paraf dengan peserta terbaru setelah surat terbit diubah: keputusan atasan
     * yang masih relevan dipertahankan, atasan baru diminta paraf, yang tidak relevan lagi dilepas.
     */
    public static function resync(AssignmentLetter $letter): void
    {
        if (! $letter->approvals()->exists()) {
            return;
        }

        $letter->load('employees');
        $existing = $letter->approvals()->get();
        $kept = [];
        $added = collect();

        foreach (self::plan($letter)['rows'] as $row) {
            $match = $existing->first(fn (AssignmentLetterApproval $a) => ! in_array($a->id, $kept, true)
                && $a->role === $row['role']
                && (int) $a->approver_id === (int) ($row['approver_id'] ?? 0));

            if ($match) {
                $kept[] = $match->id;
                $match->update(['label' => $row['label'], 'step_group' => $row['step_group'], 'sort_order' => $row['sort_order']]);

                continue;
            }

            $created = $letter->approvals()->create($row);
            $kept[] = $created->id;
            $added->push($created);
        }

        $letter->approvals()->whereNotIn('id', $kept)->delete();

        if ($added->isNotEmpty()) {
            $letter->update(['approved_at' => null]);
        }

        self::advance($letter->fresh(), null, $added->pluck('id')->all());
    }

    /**
     * Langkah yang boleh diputuskan $actor sekarang: pending, dan ia
     * atasan yang ditunjuk atau pemegang izin HRD/GA HSE untuk langkah itu.
     *
     * @return Collection<int, AssignmentLetterApproval>
     */
    public static function actionableFor(AssignmentLetter $letter, Employee $actor): Collection
    {
        if (! $letter->isIssued() || $letter->hasSignedFile()) {
            return collect();
        }

        $approvals = $letter->relationLoaded('approvals') ? $letter->approvals : $letter->approvals()->get();
        $permissions = app(AdminPermission::class);

        return $approvals->filter(fn (AssignmentLetterApproval $a) => $a->isPending()
            && self::mayAct($a, $actor, $permissions))->values();
    }

    /**
     * Surat terbit yang sekarang menunggu keputusan $actor: ada kotak pending
     * yang boleh ia putuskan (atasan yang ditunjuk, atau pemegang izin HRD/GA HSE).
     * Dipakai banner, filter, dan angka di menu Surat Tugas admin supaya HRD/GA HSE tahu surat mana
     * yang menunggu mereka tanpa membuka satu per satu.
     *
     * @return Collection<int, AssignmentLetter>
     */
    public static function awaitingLetters(Employee $actor): Collection
    {
        if (! Schema::hasTable('assignment_letter_approvals')) {
            return collect();
        }

        return AssignmentLetter::where('company_id', $actor->company_id)
            ->where('status', AssignmentLetter::STATUS_ISSUED)
            ->whereNull('signed_file_path')
            ->whereHas('approvals', fn ($q) => $q->where('status', AssignmentLetterApproval::STATUS_PENDING))
            ->with(['approvals', 'employees', 'destinations'])
            ->get()
            ->filter(fn (AssignmentLetter $letter) => self::actionableFor($letter, $actor)->isNotEmpty())
            ->values();
    }

    /**
     * Langkah paraf yang menunggu $approver untuk daftar Persetujuan Tim di portal dan lencana
     * jumlahnya: atasan yang ditunjuk, atau pemegang izin HRD/GA HSE. Satu surat bisa muncul
     * lebih dari sekali bila $approver memegang beberapa langkah (mis. superadmin).
     *
     * @return Collection<int, AssignmentLetterApproval>
     */
    public static function pendingFor(Employee $approver): Collection
    {
        return self::awaitingLetters($approver)
            ->flatMap(fn (AssignmentLetter $letter) => self::actionableFor($letter, $approver)
                ->each(fn (AssignmentLetterApproval $a) => $a->setRelation('letter', $letter)))
            ->sortByDesc(fn (AssignmentLetterApproval $a) => $a->letter->submitted_at)
            ->values();
    }

    /**
     * Keputusan (setuju/tolak) surat tugas yang pernah diambil $actor, baik sebagai atasan di portal
     * maupun HRD/GA HSE di admin panel, untuk tab Riwayat. Langkah yang dilewati tidak dihitung:
     * itu keputusan HRD atas langkah orang lain.
     */
    public static function historyQuery(Employee $actor)
    {
        return AssignmentLetterApproval::where('acted_by', $actor->id)
            ->whereIn('status', [AssignmentLetterApproval::STATUS_APPROVED, AssignmentLetterApproval::STATUS_REJECTED]);
    }

    /** @return Collection<int, AssignmentLetterApproval> */
    public static function historyFor(Employee $actor, int $limit = 20): Collection
    {
        if (! Schema::hasTable('assignment_letter_approvals')) {
            return collect();
        }

        return self::historyQuery($actor)
            ->with(['letter.employees', 'letter.destinations'])
            ->orderByDesc('acted_at')
            ->limit($limit)
            ->get();
    }

    public static function historyCount(Employee $actor): int
    {
        return Schema::hasTable('assignment_letter_approvals') ? self::historyQuery($actor)->count() : 0;
    }

    public static function approve(AssignmentLetterApproval $approval, Employee $actor, ?string $notes = null): array
    {
        $result = DB::transaction(function () use ($approval, $actor, $notes) {
            $letter = AssignmentLetter::whereKey($approval->assignment_letter_id)->lockForUpdate()->firstOrFail();
            $approval = $approval->fresh();

            if (! $approval || ! $approval->isPending() || ! $letter->isIssued() || $letter->hasSignedFile()) {
                return ['error' => 'Persetujuan ini sudah diputuskan, atau surat sudah tidak menerima paraf.'];
            }

            if (! self::actionableFor($letter, $actor)->contains('id', $approval->id)) {
                return ['error' => 'Anda tidak bisa memutuskan langkah ini sekarang (bukan giliran atau bukan hak Anda).'];
            }

            $approval->update([
                'status' => AssignmentLetterApproval::STATUS_APPROVED,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'notes' => filled($notes) ? trim($notes) : null,
                'signature_path' => self::snapshotSignature($letter, $approval, $actor),
            ]);

            return ['letter' => $letter, 'group' => $approval->step_group];
        });

        if (isset($result['letter'])) {
            self::advance($result['letter'], $result['group']);
        }

        return $result;
    }

    /**
     * Tolak: alasan wajib. Surat tetap terbit; kotak itu ditandai ditolak dan HRD memperbaiki
     * surat lalu mengajukan ulang.
     */
    public static function reject(AssignmentLetterApproval $approval, Employee $actor, string $reason): array
    {
        $result = DB::transaction(function () use ($approval, $actor, $reason) {
            $letter = AssignmentLetter::whereKey($approval->assignment_letter_id)->lockForUpdate()->firstOrFail();
            $approval = $approval->fresh();

            if (! $approval || ! $approval->isPending() || ! $letter->isIssued() || $letter->hasSignedFile()) {
                return ['error' => 'Persetujuan ini sudah diputuskan, atau surat sudah tidak menerima paraf.'];
            }

            if (! self::actionableFor($letter, $actor)->contains('id', $approval->id)) {
                return ['error' => 'Anda tidak bisa memutuskan langkah ini sekarang (bukan giliran atau bukan hak Anda).'];
            }

            $approval->update([
                'status' => AssignmentLetterApproval::STATUS_REJECTED,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'notes' => trim($reason),
            ]);

            $letter->update([
                'rejected_at' => now(),
                'rejected_by' => $actor->id,
                'rejection_reason' => trim($reason),
            ]);

            return ['letter' => $letter, 'approval' => $approval];
        });

        if (isset($result['letter'])) {
            AssignmentLetterNotifier::approvalRejected($result['letter'], $actor);
        }

        return $result;
    }

    /**
     * Lewati langkah yang tak bisa diselesaikan (atasan cuti panjang, keluar). Kotak parafnya
     * kosong di cetak untuk diparaf basah. Pelakunya HRD (izin terbit), dan tercatat.
     */
    public static function skip(AssignmentLetterApproval $approval, Employee $actor, string $reason): array
    {
        $result = DB::transaction(function () use ($approval, $actor, $reason) {
            $letter = AssignmentLetter::whereKey($approval->assignment_letter_id)->lockForUpdate()->firstOrFail();
            $approval = $approval->fresh();

            if (! $approval || ! $approval->isPending() || ! $letter->isIssued() || $letter->hasSignedFile()) {
                return ['error' => 'Langkah ini sudah diputuskan, atau surat sudah tidak menerima paraf.'];
            }

            $approval->update([
                'status' => AssignmentLetterApproval::STATUS_SKIPPED,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'notes' => trim($reason),
            ]);

            return ['letter' => $letter, 'group' => $approval->step_group];
        });

        if (isset($result['letter'])) {
            self::advance($result['letter'], $result['group']);
        }

        return $result;
    }

    /**
     * Setelah satu langkah beres: kalau semua kotak selesai, tandai paraf lengkap dan kabari HRD.
     * Kalau belum, beri tahu pemberi persetujuan yang baru diminta: semua kotak saat surat terbit
     * ($actedGroup null), atau hanya $onlyIds (kotak baru hasil resync, atau kotak yang dibuka
     * lagi lewat Ajukan Ulang). Memutuskan satu langkah tidak membuka langkah lain, jadi tidak
     * ada yang dikabari lagi.
     *
     * @param  array<int, int>|null  $onlyIds  batasi pemberitahuan pada kotak-kotak ini
     */
    private static function advance(AssignmentLetter $letter, ?int $actedGroup = null, ?array $onlyIds = null): void
    {
        $letter = $letter->fresh(['approvals']);

        if (! $letter->isIssued()) {
            return;
        }

        if ($letter->approvals->isNotEmpty() && $letter->approvals->every(fn (AssignmentLetterApproval $a) => $a->isDone())) {
            if ($letter->approved_at === null) {
                $letter->update(['approved_at' => now()]);
                AssignmentLetterNotifier::approvalCompleted($letter);
            }

            return;
        }

        if ($actedGroup !== null) {
            return; // semua grup sudah terbuka sejak surat terbit; tak ada yang baru perlu dikabari
        }

        $open = $letter->approvals->filter(fn (AssignmentLetterApproval $a) => $a->isPending()
            && ($onlyIds === null || in_array($a->id, $onlyIds, true)));

        if ($open->isEmpty()) {
            return;
        }

        AssignmentLetterNotifier::approvalRequested($letter, $open->values());
    }

    private static function mayAct(AssignmentLetterApproval $approval, Employee $actor, AdminPermission $permissions): bool
    {
        return match ($approval->role) {
            AssignmentLetterApproval::ROLE_HRD => $permissions->can($actor, self::PERMISSION_HRD),
            AssignmentLetterApproval::ROLE_GA_HSE => $permissions->can($actor, self::PERMISSION_HSE),
            default => (int) $approval->approver_id === (int) $actor->id,
        };
    }

    /** Karyawan yang boleh memutuskan langkah ini: atasan yang ditunjuk, atau pemegang izin HRD/GA HSE. */
    public static function approversFor(AssignmentLetterApproval $approval, int $companyId): Collection
    {
        if ($approval->approver_id) {
            return Employee::whereKey($approval->approver_id)->where('is_active', true)->get();
        }

        $permission = $approval->role === AssignmentLetterApproval::ROLE_HRD ? self::PERMISSION_HRD : self::PERMISSION_HSE;
        $permissions = app(AdminPermission::class);

        // Pemegang izin hanya mungkin berada di antara yang punya role admin; karyawan biasa dilewati.
        $hasRoleTables = Schema::hasTable('roles') && Schema::hasTable('employee_roles');

        $holders = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q) use ($hasRoleTables) {
                $q->where('role', '!=', 'employee');

                if ($hasRoleTables) {
                    $q->orWhereHas('roles', fn ($r) => $r->where('slug', '!=', 'employee'));
                }
            })
            ->get()
            ->filter(fn (Employee $employee) => $permissions->can($employee, $permission))
            ->values();

        // Superadmin memegang semua izin, jadi selalu masuk daftar pemegang izin. Mereka tetap bisa
        // memutuskan, tapi tidak dibanjiri pemberitahuan kecuali memang tak ada pemegang izin lain.
        $others = $holders->reject(fn (Employee $employee) => in_array('superadmin', $permissions->roleSlugs($employee), true));

        return $others->isNotEmpty() ? $others->values() : $holders;
    }

    /** Salin ttd pemberi persetujuan ke folder surat supaya surat tak berubah bila ttd diganti kelak. */
    private static function snapshotSignature(AssignmentLetter $letter, AssignmentLetterApproval $approval, Employee $actor): ?string
    {
        $path = $actor->signature;
        $disk = Storage::disk('public');

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $target = sprintf('assignment-letters/signatures/%d-%d.%s', $letter->id, $approval->id, pathinfo($path, PATHINFO_EXTENSION) ?: 'png');

        return $disk->copy($path, $target) ? $target : null;
    }
}
