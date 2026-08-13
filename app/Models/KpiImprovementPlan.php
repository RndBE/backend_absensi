<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rencana perbaikan wajib atas hasil KPI (Bab 10.1).
 */
class KpiImprovementPlan extends Model
{
    public const SUBJECT_DIVISION = 'division';

    public const SUBJECT_EMPLOYEE = 'employee';

    public const SUBJECT_LABELS = [
        self::SUBJECT_DIVISION => 'Divisi',
        self::SUBJECT_EMPLOYEE => 'Karyawan',
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Belum Disusun',
        self::STATUS_IN_PROGRESS => 'Sedang Berjalan',
        self::STATUS_DONE => 'Selesai',
        self::STATUS_OVERDUE => 'Lewat Tenggat',
    ];

    protected $fillable = [
        'kpi_period_id', 'subject_type', 'subject_id', 'trigger_reason', 'trigger_score',
        'plan_text', 'due_date', 'review_date', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger_score' => 'decimal:4',
            'due_date' => 'date',
            'review_date' => 'date',
        ];
    }

    /**
     * Subjek disimpan sebagai pasangan subject_type + subject_id, bukan relasi morph Laravel,
     * karena migrasi menyimpan nilai domain ("division"/"employee") dan bukan nama kelas.
     */
    private ?Model $subjectModel = null;

    private bool $subjectLoaded = false;

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_OVERDUE]);
    }

    public function subject(): ?Model
    {
        if (! $this->subjectLoaded) {
            $this->subjectModel = $this->isDivision()
                ? Department::find($this->subject_id)
                : Employee::find($this->subject_id);
            $this->subjectLoaded = true;
        }

        return $this->subjectModel;
    }

    public function setSubject(?Model $subject): void
    {
        $this->subjectModel = $subject;
        $this->subjectLoaded = true;
    }

    /**
     * Isi cache subjek untuk sekumpulan rencana sekaligus — tabel daftar memuat dua jenis
     * subjek sehingga tanpa ini setiap baris memicu query sendiri.
     *
     * @param  Collection<int, self>  $plans
     */
    public static function preloadSubjects(Collection $plans): void
    {
        $departments = Department::whereIn('id', $plans->where('subject_type', self::SUBJECT_DIVISION)->pluck('subject_id'))
            ->get()
            ->keyBy('id');

        $employees = Employee::whereIn('id', $plans->where('subject_type', self::SUBJECT_EMPLOYEE)->pluck('subject_id'))
            ->get()
            ->keyBy('id');

        foreach ($plans as $plan) {
            $plan->setSubject($plan->isDivision()
                ? $departments->get($plan->subject_id)
                : $employees->get($plan->subject_id));
        }
    }

    public function isDivision(): bool
    {
        return $this->subject_type === self::SUBJECT_DIVISION;
    }

    public function subjectName(): string
    {
        $subject = $this->subject();

        if (! $subject) {
            return '—';
        }

        return $this->isDivision() ? $subject->name : $subject->full_name;
    }

    public function subjectLabel(): string
    {
        return self::SUBJECT_LABELS[$this->subject_type] ?? $this->subject_type;
    }

    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_DONE || ! $this->due_date) {
            return false;
        }

        return Carbon::today()->gt($this->due_date);
    }

    /**
     * Status "lewat tenggat" tidak pernah ditulis ke basis data oleh proses terjadwal —
     * dihitung saat ditampilkan supaya tidak ada baris yang telat berubah karena cron mati.
     */
    public function effectiveStatus(): string
    {
        return $this->isOverdue() ? self::STATUS_OVERDUE : $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->effectiveStatus()] ?? $this->status;
    }
}
