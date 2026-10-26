<?php

namespace App\Models;

use App\Support\BudgetDestination;
use App\Support\WorkingDays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BudgetRequest extends Model
{
    /** Default batas pengumpulan LHP (hari kerja) bila tidak ada override & setting global. */
    public const DEFAULT_LHP_DEADLINE_DAYS = 5;

    protected $fillable = [
        'employee_id', 'type', 'title', 'description',
        'status', 'current_step', 'total_amount',
        'surat_tugas_no', 'surat_tugas_date', 'assignment_letter_id', 'rejection_reason',
        'distance_km', 'travel_zone_id',
        'destination_label', 'destination_lat', 'destination_lng', 'distance_source', 'destination_regency_id',
        'departure_date', 'return_date', 'lhp_deadline_days',
    ];

    /** Untuk app: asal jarak yang terbaca dan tautan peta tujuan (lihat BudgetDestination). */
    protected $appends = ['distance_source_label', 'destination_map_url'];

    protected function casts(): array
    {
        return [
            'surat_tugas_date' => 'date',
            'departure_date' => 'date',
            'return_date' => 'date',
            'lhp_deadline_days' => 'integer',
            'total_amount' => 'decimal:2',
            'destination_lat' => 'float',
            'destination_lng' => 'float',
        ];
    }

    public function getDistanceSourceLabelAttribute(): ?string
    {
        return BudgetDestination::sourceLabel($this->attributes['distance_source'] ?? null);
    }

    public function getDestinationMapUrlAttribute(): ?string
    {
        return BudgetDestination::mapUrl($this->destination_lat, $this->destination_lng);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetRequestItem::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'budget_request_participants')->withTimestamps();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RequestAttachment::class, 'attachable');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }

    /** Perubahan item oleh approver, terbaru di atas. */
    public function revisions(): HasMany
    {
        return $this->hasMany(BudgetRequestRevision::class)->latest('id');
    }

    public function travelZone()
    {
        return $this->belongsTo(TravelZone::class);
    }

    public function assignmentLetter(): BelongsTo
    {
        return $this->belongsTo(AssignmentLetter::class);
    }

    public function travelReport()
    {
        // LHP yang ditolak tidak dihitung, supaya anggarannya bisa dipakai lagi untuk pengajuan ulang.
        return $this->hasOne(TravelReport::class)->where('status', '!=', 'rejected');
    }

    public function lpj()
    {
        return $this->hasOne(Lpj::class);
    }

    /** Satu LPJ per pelapor: pemilik anggaran, dan tiap peserta yang punya biaya pribadi. */
    public function lpjs(): HasMany
    {
        return $this->hasMany(Lpj::class);
    }

    /**
     * Baris biaya yang menjadi bahan LPJ $employeeId. Biaya bersama (tanpa pemilik) dilaporkan
     * pemilik anggaran saja, supaya tidak muncul dobel di LPJ peserta; biaya pribadi dilaporkan
     * pemiliknya sendiri.
     *
     * @return Collection<int, BudgetRequestItem>
     */
    public function itemsForLpj(int $employeeId): Collection
    {
        // Relasi yang dimuat dengan daftar kolom tanpa employee_id akan salah menyangka tak ada pemilik.
        $ownerId = array_key_exists('employee_id', $this->attributes) || ! $this->exists
            ? $this->employee_id
            : static::whereKey($this->getKey())->value('employee_id');
        $isBudgetOwner = (int) $ownerId === $employeeId;

        return $this->items->filter(fn (BudgetRequestItem $item) => $isBudgetOwner
            ? $item->employee_id === null || (int) $item->employee_id === $employeeId
            : (int) $item->employee_id === $employeeId)->values();
    }

    /**
     * Id karyawan yang harus membuat LPJ untuk anggaran ini: pemilik bila ia punya biaya
     * (bersama atau miliknya), dan peserta yang punya biaya pribadi.
     *
     * @return array<int, int>
     */
    public function lpjAuthorIds(): array
    {
        return collect([$this->employee_id])
            ->merge($this->participants()->pluck('employees.id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $this->itemsForLpj($id)->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * Anggaran disetujui/cair yang masih menunggu LPJ dari $employeeId: ia punya baris biaya
     * untuk dilaporkan (lihat itemsForLpj) dan belum membuat LPJ-nya.
     */
    public function scopeAwaitingLpjFrom(Builder $query, int $employeeId): Builder
    {
        return $query
            ->whereIn('status', ['approved', 'paid'])
            ->whereDoesntHave('lpjs', fn (Builder $lpj) => $lpj->where('employee_id', $employeeId))
            ->where(fn (Builder $who) => $who
                ->where(fn (Builder $owner) => $owner
                    ->where('employee_id', $employeeId)
                    ->whereHas('items', fn (Builder $item) => $item->where(fn (Builder $mine) => $mine
                        ->whereNull('employee_id')
                        ->orWhere('employee_id', $employeeId))))
                ->orWhere(fn (Builder $participant) => $participant
                    ->whereHas('participants', fn (Builder $p) => $p->where('employees.id', $employeeId))
                    ->whereHas('items', fn (Builder $item) => $item->where('employee_id', $employeeId))));
    }

    public function payments()
    {
        return $this->hasMany(BudgetPayment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(BudgetPayment::class)->latestOfMany();
    }

    /**
     * Recalculate total_amount from items.
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('amount'),
        ]);
    }

    /**
     * Batas pengumpulan LHP efektif dalam hari kerja.
     * Prioritas: override per-pengajuan (keringanan HR) → setting global → default konstanta.
     */
    public function effectiveLhpDeadlineDays(): int
    {
        if ($this->lhp_deadline_days) {
            return (int) $this->lhp_deadline_days;
        }

        return (int) Setting::getValue('lhp_deadline_working_days', self::DEFAULT_LHP_DEADLINE_DAYS);
    }

    /**
     * Tanggal batas akhir pengumpulan LHP = tanggal pulang + N hari kerja.
     * Null bila belum ada tanggal pulang (mis. pengajuan non-perjalanan).
     */
    public function lhpDeadlineDate(): ?Carbon
    {
        if (! $this->return_date) {
            return null;
        }

        return WorkingDays::add(
            $this->return_date,
            $this->effectiveLhpDeadlineDays(),
            $this->employee?->company_id,
        );
    }
}
