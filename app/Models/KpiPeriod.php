<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_FILLING = 'filling';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_CALIBRATION = 'calibration';

    public const STATUS_FINAL = 'final';

    /** Urutan alur Bab 9.3. Status hanya boleh maju satu langkah ke kanan. */
    public const STATUS_FLOW = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_FILLING,
        self::STATUS_PROCESSING,
        self::STATUS_CALIBRATION,
        self::STATUS_FINAL,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_OPEN => 'Dibuka',
        self::STATUS_FILLING => 'Pengisian',
        self::STATUS_PROCESSING => 'Pemrosesan',
        self::STATUS_CALIBRATION => 'Kalibrasi',
        self::STATUS_FINAL => 'Final',
    ];

    protected $fillable = [
        'company_id', 'name', 'start_date', 'end_date',
        'cross_fill_start', 'cross_fill_end', 'fill_start', 'fill_end',
        'status', 'is_trial', 'opened_at', 'finalized_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cross_fill_start' => 'date',
            'cross_fill_end' => 'date',
            'fill_start' => 'date',
            'fill_end' => 'date',
            'is_trial' => 'boolean',
            'opened_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function levelSnapshots(): HasMany
    {
        return $this->hasMany(KpiPeriodLevelSnapshot::class)->orderBy('sort_order');
    }

    public function indicatorSnapshots(): HasMany
    {
        return $this->hasMany(KpiPeriodIndicatorSnapshot::class)->orderBy('sort_order');
    }

    public function crossItemSnapshots(): HasMany
    {
        return $this->hasMany(KpiPeriodCrossItemSnapshot::class)->orderBy('sort_order');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Master masih boleh dipakai apa adanya; snapshot belum dibuat. */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    /**
     * Setelah snapshot dibuat, master tidak lagi relevan bagi periode ini — seluruh
     * perhitungan wajib membaca snapshot (Bab 11.4).
     */
    public function usesSnapshot(): bool
    {
        return ! $this->isDraft();
    }

    public function canTransitionTo(string $status): bool
    {
        $current = array_search($this->status, self::STATUS_FLOW, true);
        $target = array_search($status, self::STATUS_FLOW, true);

        return $current !== false && $target !== false && $target === $current + 1;
    }

    /** Periode uji coba: skor tetap dihitung, tapi tidak boleh dipakai untuk konsekuensi (Bab 11.1). */
    public function hasConsequence(): bool
    {
        return ! $this->is_trial;
    }
}
