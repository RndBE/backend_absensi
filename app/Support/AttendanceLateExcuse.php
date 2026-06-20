<?php

namespace App\Support;

use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceLateExcuse
{
    public const STATUS_LABEL = 'Hadir - Izin Terlambat';
    public const SHORT_LABEL = 'Izin Terlambat';

    public static function isLateArrivalLeave(?LeaveRequest $leave): bool
    {
        $name = Str::lower((string) ($leave?->leaveType?->name ?? ''));

        return Str::contains($name, 'terlambat') && Str::contains($name, 'datang');
    }

    public static function firstForDate(Collection $leaves, CarbonInterface|string $date): ?LeaveRequest
    {
        $date = $date instanceof CarbonInterface ? Carbon::instance($date) : Carbon::parse($date);

        return $leaves->first(function (LeaveRequest $leave) use ($date) {
            return $date->between(
                Carbon::parse($leave->start_date)->startOfDay(),
                Carbon::parse($leave->end_date)->endOfDay()
            );
        });
    }

    public static function lateExcuseDates(Collection $leaves, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $dates = collect();

        foreach ($leaves->filter(fn (LeaveRequest $leave) => self::isLateArrivalLeave($leave)) as $leave) {
            $cursor = Carbon::parse($leave->start_date)->max(Carbon::instance($start))->startOfDay();
            $until = Carbon::parse($leave->end_date)->min(Carbon::instance($end))->startOfDay();

            while ($cursor->lte($until)) {
                $dates->put($cursor->toDateString(), $leave);
                $cursor->addDay();
            }
        }

        return $dates;
    }
}
