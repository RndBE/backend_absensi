<?php

namespace App\Support;

use App\Models\AssignmentLetter;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\TravelZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Aturan penautan pengajuan anggaran ke surat tugas, dipakai portal karyawan dan API mobile.
 *
 * - Hanya surat tugas yang sudah terbit, dari perusahaan yang sama, dan mencantumkan pengaju.
 * - Satu surat tugas = satu anggaran aktif. Peserta lain ikut sebagai participants (dan bisa
 *   memilih anggaran itu saat membuat LHP). Anggaran yang ditolak melepas surat tugasnya.
 * - Surat tugas menjadi sumber data: nomor, tanggal, jadwal, peserta, dan jarak terjauh
 *   ditimpa dari surat, bukan dari isian form, supaya tidak ada dua versi data perjalanan.
 */
class AssignmentLetterBudgetLink
{
    private const RELEASING_STATUSES = ['rejected'];

    /** Surat tugas yang boleh dipilih pengaju untuk anggaran baru, atau untuk $current saat edit. */
    public static function availableFor(Employee $employee, ?BudgetRequest $current = null): Collection
    {
        // Server yang kodenya sudah baru tapi belum menjalankan migrasi tetap bisa membuka form anggaran.
        if (! self::tablesReady()) {
            return new Collection();
        }

        return self::query($employee, $current)
            ->with(['employees', 'destinations'])
            ->orderByDesc('letter_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Ambil surat tugas yang dipilih, atau lempar ValidationException. Panggil sekali sebelum
     * transaksi (pesan ramah) dan sekali lagi di dalam transaksi dengan $lock = true supaya
     * dua peserta yang mengajukan bersamaan tidak sama-sama lolos.
     */
    public static function resolve(Employee $employee, int|string|null $letterId, ?BudgetRequest $current = null, bool $lock = false): ?AssignmentLetter
    {
        if (blank($letterId)) {
            return null;
        }

        $letter = ! self::tablesReady() ? null : self::query($employee, $current)
            ->whereKey((int) $letterId)
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())
            ->with(['employees', 'destinations'])
            ->first();

        if (! $letter) {
            throw ValidationException::withMessages([
                'assignment_letter_id' => 'Surat tugas tidak bisa dipakai: belum terbit, sudah dibatalkan, Anda tidak tercantum di dalamnya, atau sudah dipakai pengajuan anggaran lain.',
            ]);
        }

        return $letter;
    }

    /** Kolom anggaran yang diisi dari surat tugas. */
    public static function budgetAttributes(AssignmentLetter $letter): array
    {
        $attributes = [
            'assignment_letter_id' => $letter->id,
            'surat_tugas_no' => $letter->number,
            'surat_tugas_date' => $letter->letter_date->toDateString(),
            'departure_date' => $letter->departure_date->toDateString(),
            'return_date' => $letter->return_date->toDateString(),
        ];

        // Zona perjalanan mengikuti lokasi terjauh. Surat tanpa jarak membiarkan jarak dari form.
        if (($km = self::farthestKm($letter)) !== null) {
            $attributes['distance_km'] = $km;
            $attributes['travel_zone_id'] = TravelZone::findByKm($km)?->id;
        }

        return $attributes;
    }

    /** Peserta anggaran = semua orang di surat tugas selain pengaju. */
    public static function participantIds(AssignmentLetter $letter, Employee $requester): array
    {
        return $letter->employees
            ->pluck('employee_id')
            ->reject(fn ($id) => (int) $id === (int) $requester->id)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function farthestKm(AssignmentLetter $letter): ?int
    {
        $km = $letter->destinations->whereNotNull('distance_km')->max(fn ($d) => (float) $d->distance_km);

        return $km === null ? null : (int) round($km);
    }

    /** Data ringkas untuk form (portal) dan respons API. */
    public static function summary(AssignmentLetter $letter): array
    {
        $km = self::farthestKm($letter);
        $zone = $km !== null ? TravelZone::findByKm($km) : null;

        return [
            'id' => $letter->id,
            'number' => $letter->number,
            'letter_date' => $letter->letter_date->toDateString(),
            'departure_date' => $letter->departure_date->toDateString(),
            'work_start_date' => $letter->work_start_date->toDateString(),
            'return_date' => $letter->return_date->toDateString(),
            'working_days' => $letter->workingDays(),
            'title' => mb_strimwidth((string) $letter->destinations->first()?->activity, 0, 255, '…'),
            'destinations' => $letter->destinations->map(fn ($d) => [
                'activity' => $d->activity,
                'address' => $d->address,
                'distance_km' => $d->distance_km !== null ? (float) $d->distance_km : null,
            ])->values()->all(),
            'purpose_text' => trim(implode("\n", array_filter([$letter->purposeHeading(), ...$letter->destinationSentences()]))),
            'employees' => $letter->employees->map(fn ($e) => [
                'employee_id' => $e->employee_id,
                'name' => $e->name,
            ])->values()->all(),
            'distance_km' => $km,
            'travel_zone' => $zone ? [
                'id' => $zone->id,
                'name' => $zone->name,
                'km_range' => $zone->km_range_label,
                'meal_allowance' => (float) $zone->meal_allowance,
            ] : null,
        ];
    }

    private static function tablesReady(): bool
    {
        // Tidak di-cache statis: hasil bisa berbeda antar test dalam satu proses PHP.
        return Schema::hasTable('assignment_letters')
            && Schema::hasColumn('budget_requests', 'assignment_letter_id');
    }

    private static function query(Employee $employee, ?BudgetRequest $current): Builder
    {
        return AssignmentLetter::query()
            ->where('company_id', $employee->company_id)
            ->where('status', AssignmentLetter::STATUS_ISSUED)
            ->whereHas('employees', fn (Builder $q) => $q->where('employee_id', $employee->id))
            ->where(function (Builder $q) use ($current) {
                $q->whereDoesntHave('budgetRequests', fn (Builder $b) => $b
                    ->whereNotIn('status', self::RELEASING_STATUSES)
                    ->when($current?->exists, fn (Builder $b) => $b->where('id', '!=', $current->id)));
            });
    }
}
