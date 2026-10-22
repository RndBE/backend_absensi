<?php

namespace App\Support;

use App\Models\ApprovalLog;
use App\Models\AssignmentLetterApproval;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Lpj;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat keputusan (setuju/tolak) seorang pemberi persetujuan untuk app mobile: gabungan log
 * persetujuan semua jenis pengajuan dan paraf surat tugas yang ia putuskan, terbaru di atas.
 *
 * Keputusan yang diambil atas nama orang lain (superadmin menggantikan approver) dicatat atas nama
 * approver asli dengan jejak pelakunya (acted_by_id). Riwayat memuatnya di kedua sisi, ditandai
 * `on_behalf`, supaya approver asli tahu pengajuannya sudah diputuskan dan pelakunya punya jejak.
 */
final class ApprovalHistory
{
    public const PER_PAGE = 15;
    public const MAX_PER_PAGE = 50;

    public const LETTER = 'assignment_letter';

    /** @var array<string, class-string> jenis pengajuan => model yang dicatat di approval_logs */
    public const TYPES = [
        'leave' => LeaveRequest::class,
        'overtime' => OvertimeRequest::class,
        'attendance' => AttendanceRequest::class,
        'data-change' => DataChangeRequest::class,
        'budget' => BudgetRequest::class,
        'travel_report' => TravelReport::class,
        'lpj' => Lpj::class,
    ];

    public const LABELS = [
        'leave' => 'Cuti',
        'overtime' => 'Lembur',
        'attendance' => 'Presensi',
        'data-change' => 'Perubahan Data',
        'budget' => 'Anggaran',
        'travel_report' => 'LHP',
        'lpj' => 'LPJ',
        self::LETTER => 'Surat Tugas',
    ];

    private const DECISIONS = ['approved', 'rejected'];

    /** Jenis yang bisa disaring: semua jenis pengajuan, dan surat tugas. */
    public static function filterableTypes(): array
    {
        return [...array_keys(self::TYPES), self::LETTER];
    }

    /**
     * Satu halaman riwayat.
     *
     * @param  string|null  $status  approved | rejected | null (keduanya)
     * @param  string|null  $type    salah satu filterableTypes(), atau null (semua)
     * @param  string|null  $period  bulan keputusan, format Y-m
     * @return array{rows: array<int, array<string, mixed>>, has_more: bool}
     */
    public static function page(Employee $me, ?string $status, ?string $type, ?string $period, int $page, int $perPage): array
    {
        $decisions = in_array($status, self::DECISIONS, true) ? [$status] : self::DECISIONS;
        $from = $period ? Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth() : null;
        $to = $from?->copy()->addMonth();

        $sources = [];
        if ($type === null || $type !== self::LETTER) {
            $sources[] = self::logs($me, $decisions, $type, $from, $to);
        }
        if (($type === null || $type === self::LETTER) && Schema::hasTable('assignment_letter_approvals')) {
            $sources[] = self::letters($me, $decisions, $from, $to);
        }
        $sources = array_values(array_filter($sources));

        if ($sources === []) {
            return ['rows' => [], 'has_more' => false];
        }

        $union = array_shift($sources);
        foreach ($sources as $other) {
            $union->unionAll($other);
        }

        // Satu lebih dari satu halaman, supaya tahu ada halaman berikutnya tanpa menghitung semuanya.
        $keys = DB::query()->fromSub($union, 'history')
            ->orderByDesc('decided_at')
            ->orderBy('source')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        $hasMore = $keys->count() > $perPage;
        $keys = $keys->take($perPage);

        $logs = self::hydrateLogs($keys->where('source', 'log')->pluck('id'));
        $letters = self::hydrateLetters($keys->where('source', 'letter')->pluck('id'));

        $rows = $keys->map(fn ($key) => $key->source === 'log'
            ? self::logRow($logs->get($key->id), $me)
            : self::letterRow($letters->get($key->id)))
            ->filter()
            ->values()
            ->all();

        return ['rows' => $rows, 'has_more' => $hasMore];
    }

    private static function logs(Employee $me, array $decisions, ?string $type, ?Carbon $from, ?Carbon $to)
    {
        if (! Schema::hasTable('approval_logs')) {
            return null;
        }

        $classes = $type === null ? array_values(self::TYPES) : [self::TYPES[$type]];

        return DB::table('approval_logs')
            ->select(DB::raw("'log' as source"), 'id', DB::raw('created_at as decided_at'))
            ->whereIn('action', $decisions)
            ->whereIn('approvable_type', $classes)
            ->where(fn ($q) => $q->where('approver_id', $me->id)->orWhere('acted_by_id', $me->id))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from)->where('created_at', '<', $to));
    }

    private static function letters(Employee $me, array $decisions, ?Carbon $from, ?Carbon $to)
    {
        // Langkah yang dilewati HRD bukan keputusan pemegangnya, jadi tidak ikut.
        return DB::table('assignment_letter_approvals')
            ->select(DB::raw("'letter' as source"), 'id', DB::raw('acted_at as decided_at'))
            ->whereIn('status', $decisions)
            ->where('acted_by', $me->id)
            ->when($from, fn ($q) => $q->where('acted_at', '>=', $from)->where('acted_at', '<', $to));
    }

    /** @return Collection<int, ApprovalLog> */
    private static function hydrateLogs(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $withEmployee = ['employee:id,full_name,department_id', 'employee.department:id,name'];

        return ApprovalLog::whereIn('id', $ids)
            ->with([
                'approver:id,full_name',
                'actedBy:id,full_name',
                'approvable' => fn (MorphTo $morph) => $morph->morphWith([
                    LeaveRequest::class => [...$withEmployee, 'leaveType:id,name'],
                    OvertimeRequest::class => $withEmployee,
                    AttendanceRequest::class => $withEmployee,
                    DataChangeRequest::class => $withEmployee,
                    BudgetRequest::class => $withEmployee,
                    TravelReport::class => $withEmployee,
                    Lpj::class => [...$withEmployee, 'budgetRequest:id,title'],
                ]),
            ])
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, AssignmentLetterApproval> */
    private static function hydrateLetters(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return AssignmentLetterApproval::whereIn('id', $ids)
            ->with(['letter.employees', 'letter.destinations'])
            ->get()
            ->keyBy('id');
    }

    private static function logRow(?ApprovalLog $log, Employee $me): ?array
    {
        if (! $log) {
            return null;
        }

        $type = array_search($log->approvable_type, self::TYPES, true) ?: null;
        $request = $log->approvable;

        return [
            'key' => 'log-'.$log->id,
            'type' => $type,
            'type_label' => self::LABELS[$type] ?? 'Pengajuan',
            'request_id' => (int) $log->approvable_id,
            'decision' => $log->action,
            'decided_at' => $log->created_at?->toIso8601String(),
            'notes' => $log->notes,
            'step' => (int) $log->step_order,
            // Pengajuan yang sudah dihapus tetap tercatat di riwayat, tetapi tidak bisa dibuka.
            'available' => $request !== null,
            'title' => $request ? self::title($type, $request) : 'Pengajuan telah dihapus',
            'employee' => $request?->employee ? [
                'full_name' => $request->employee->full_name,
                'department' => $request->employee->department?->name,
            ] : null,
            'on_behalf' => self::onBehalf($log, $me),
        ];
    }

    private static function letterRow(?AssignmentLetterApproval $approval): ?array
    {
        if (! $approval) {
            return null;
        }

        $letter = $approval->letter;

        return [
            'key' => 'letter-'.$approval->id,
            'type' => self::LETTER,
            'type_label' => self::LABELS[self::LETTER],
            'request_id' => (int) $approval->assignment_letter_id,
            'decision' => $approval->status,
            'decided_at' => $approval->acted_at?->toIso8601String(),
            'notes' => $approval->notes,
            'step' => $approval->label,
            'available' => $letter !== null,
            'title' => $letter ? 'Surat tugas '.$letter->number.' ('.$approval->label.')' : 'Surat tugas telah dihapus',
            'employee' => $letter ? ['full_name' => $letter->employees->pluck('name')->implode(', '), 'department' => null] : null,
            'on_behalf' => null,
        ];
    }

    /**
     * Keputusan atas nama: `by_other` bila orang lain (mis. superadmin) memutuskannya untuk $me,
     * `for_other` bila $me yang memutuskannya untuk approver lain. Null bila $me memutuskan sendiri.
     *
     * @return array{direction: string, name: string|null}|null
     */
    private static function onBehalf(ApprovalLog $log, Employee $me): ?array
    {
        if (! $log->acted_by_id || (int) $log->acted_by_id === (int) $log->approver_id) {
            return null;
        }

        if ((int) $log->approver_id === (int) $me->id) {
            return ['direction' => 'by_other', 'name' => $log->actedBy?->full_name];
        }

        return ['direction' => 'for_other', 'name' => $log->approver?->full_name];
    }

    /** Satu baris ringkas isi pengajuan, tanpa angka yang perlu diformat di aplikasi. */
    private static function title(?string $type, $request): string
    {
        $date = fn ($value) => $value?->format('d/m/Y');

        return match ($type) {
            'leave' => trim(($request->leaveType?->name ?? 'Cuti').' '.self::range($request->start_date, $request->end_date)),
            'overtime' => 'Lembur '.$date($request->date),
            'attendance' => 'Presensi '.$date($request->date),
            'data-change' => 'Ubah '.$request->field_name,
            'budget' => (string) $request->title,
            'travel_report' => 'LHP '.$request->destination_city,
            'lpj' => 'LPJ '.($request->budgetRequest?->title ?? ''),
            default => 'Pengajuan',
        };
    }

    private static function range($from, $to): string
    {
        if (! $from) {
            return '';
        }

        $start = $from->format('d/m/Y');

        return $to && $to->format('d/m/Y') !== $start ? "$start - ".$to->format('d/m/Y') : $start;
    }
}
