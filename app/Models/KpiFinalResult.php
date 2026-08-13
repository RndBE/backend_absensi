<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiFinalResult extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALIBRATED = 'calibrated';

    public const STATUS_APPROVED = 'approved';

    /** Batas bawah tiap predikat, urut dari tertinggi (Bab 8.2). */
    public const GRADE_THRESHOLDS = [
        'A' => 4.50,
        'B' => 4.00,
        'C' => 3.00,
        'D' => 2.00,
        'E' => 0.00,
    ];

    public const GRADE_LABELS = [
        'A' => 'Istimewa',
        'B' => 'Sangat Baik',
        'C' => 'Baik / Memenuhi Harapan',
        'D' => 'Perlu Perbaikan',
        'E' => 'Tidak Memenuhi',
    ];

    protected $fillable = [
        'kpi_period_id', 'employee_id', 'kpi_period_level_snapshot_id',
        'score_excellence', 'score_contribution', 'score_leadership',
        'final_score', 'grade', 'calibrated_score', 'calibration_note',
        'status', 'approved_by', 'approved_at', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'score_excellence' => 'decimal:4',
            'score_contribution' => 'decimal:4',
            'score_leadership' => 'decimal:4',
            'final_score' => 'decimal:4',
            'calibrated_score' => 'decimal:4',
            'approved_at' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function levelSnapshot(): BelongsTo
    {
        return $this->belongsTo(KpiPeriodLevelSnapshot::class, 'kpi_period_level_snapshot_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * Predikat dihitung dari nilai 4 desimal, bukan dari nilai yang sudah dibulatkan ke 2
     * desimal untuk tampilan (Bab 8.4) — pembulatan lebih dulu bisa menggeser 4,4951 ke
     * predikat A padahal seharusnya B.
     */
    public static function gradeFor(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach (self::GRADE_THRESHOLDS as $grade => $minimum) {
            if ($score >= $minimum) {
                return $grade;
            }
        }

        return 'E';
    }

    public function gradeLabel(): string
    {
        return self::GRADE_LABELS[$this->grade] ?? '-';
    }

    /** Nilai yang berlaku: hasil kalibrasi bila sudah ada, kalau tidak nilai perhitungan. */
    public function effectiveScore(): ?float
    {
        $score = $this->calibrated_score ?? $this->final_score;

        return $score === null ? null : (float) $score;
    }
}
