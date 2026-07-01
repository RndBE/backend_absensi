<?php

namespace App\Support;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WorkingDays
{
    /**
     * Maju sejumlah $days hari kerja dari $start (tanggal $start sendiri tidak dihitung).
     * Melewati Sabtu, Minggu, dan tanggal libur perusahaan pada tabel holidays.
     *
     * Contoh: pulang Jumat + 5 hari kerja → jatuh di Jumat berikutnya (lompati Sab-Min).
     */
    public static function add(CarbonInterface $start, int $days, ?int $companyId = null): Carbon
    {
        $cursor = Carbon::parse($start)->startOfDay();

        if ($days <= 0) {
            return $cursor;
        }

        $holidays = self::holidaySet($companyId, $cursor, $days);

        $added = 0;
        while ($added < $days) {
            $cursor->addDay();
            if (self::isWorkingDay($cursor, $holidays)) {
                $added++;
            }
        }

        return $cursor;
    }

    /**
     * Set tanggal libur (Y-m-d sebagai key) dalam rentang perhitungan.
     * Batas atas dilebihkan karena tiap weekend/libur memperpanjang rentang.
     */
    private static function holidaySet(?int $companyId, Carbon $start, int $days): array
    {
        if (! $companyId) {
            return [];
        }

        $upperBound = $start->copy()->addDays(max($days * 3, $days + 14));

        return Holiday::where('company_id', $companyId)
            ->whereBetween('date', [$start->toDateString(), $upperBound->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip()
            ->all();
    }

    private static function isWorkingDay(Carbon $date, array $holidays): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! isset($holidays[$date->toDateString()]);
    }
}
