<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kuesioner Lapis A: seorang penilai menilai satu divisi mitra.
 *
 * `assessor_id` disimpan tapi TIDAK BOLEH ikut ditampilkan ke pihak yang dinilai — lihat
 * catatan di migration 2026_08_10_000013. Gunakan scope `anonymous()` untuk pengambilan
 * data yang akan sampai ke mata pihak dinilai.
 */
class KpiCrossLayerA extends Model
{
    protected $table = 'kpi_cross_layer_a';

    /** Alasan pembuangan otomatis (Bab 7.8). */
    public const INVALID_STRAIGHT_LINING = 'straight_lining';

    public const INVALID_NO_RELATION = 'no_relation';

    public const INVALID_DISPUTE = 'open_dispute';

    protected $fillable = [
        'kpi_period_id', 'assessor_id', 'assessor_department_id', 'target_department_id',
        'comment_positive', 'comment_improvement',
        'is_valid', 'invalid_reason', 'comment_hidden', 'hidden_reason', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
            'comment_hidden' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessor_id');
    }

    public function assessorDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assessor_department_id');
    }

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(KpiCrossLayerAScore::class, 'kpi_cross_layer_a_id');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true)->whereNotNull('submitted_at');
    }

    /** Kolom aman untuk tampilan pihak yang dinilai — tanpa jejak identitas penilai. */
    public function scopeAnonymous(Builder $query): Builder
    {
        return $query->select([
            'id', 'kpi_period_id', 'target_department_id',
            'comment_positive', 'comment_improvement', 'comment_hidden', 'submitted_at',
        ]);
    }

    /**
     * Standar deviasi skor dalam satu kuesioner. Nol berarti penilai memberi angka identik
     * untuk seluruh butir — penanda pengisian asal (Bab 7.8b).
     */
    public function scoreDeviation(): float
    {
        $values = $this->scores->pluck('score')->map(fn ($s) => (float) $s)->all();
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $count;

        return sqrt($variance);
    }
}
