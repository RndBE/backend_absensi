<?php

namespace App\Support;

use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceLateExcuse
{
    public const LATE_EXCUSE_STATUS = 'late_excuse';
    public const EARLY_DEPARTURE_STATUS = 'early_departure';
    public const STATUS_LABEL = 'Hadir - Izin Terlambat';
    public const SHORT_LABEL = 'Izin Terlambat';
    public const EARLY_DEPARTURE_STATUS_LABEL = 'Hadir - Izin Pulang Cepat';
    public const EARLY_DEPARTURE_SHORT_LABEL = 'Izin Pulang Cepat';

    public static function manualPermissionStatusLabel(?string $status): ?string
    {
        return match ($status) {
            self::LATE_EXCUSE_STATUS => self::STATUS_LABEL,
            self::EARLY_DEPARTURE_STATUS => self::EARLY_DEPARTURE_STATUS_LABEL,
            default => null,
        };
    }

    public static function isLateArrivalLeave(?LeaveRequest $leave): bool
    {
        $name = Str::lower((string) ($leave?->leaveType?->name ?? ''));

        return Str::contains($name, 'terlambat') && Str::contains($name, 'datang');
    }

    public static function isEarlyDepartureLeave(?LeaveRequest $leave): bool
    {
        $name = Str::lower((string) ($leave?->leaveType?->name ?? ''));

        return (
            Str::contains($name, 'pulang') && Str::contains($name, ['cepat', 'awal'])
        ) || (
            Str::contains($name, 'early') && Str::contains($name, ['leave', 'departure'])
        );
    }

    public static function isPartialDayLeave(?LeaveRequest $leave): bool
    {
        return self::isLateArrivalLeave($leave) || self::isEarlyDepartureLeave($leave);
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
        return self::matchingLeaveDates(
            $leaves->filter(fn (LeaveRequest $leave) => self::isLateArrivalLeave($leave)),
            $start,
            $end
        );
    }

    public static function earlyDepartureDates(Collection $leaves, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return self::matchingLeaveDates(
            $leaves->filter(fn (LeaveRequest $leave) => self::isEarlyDepartureLeave($leave)),
            $start,
            $end
        );
    }

    private static function matchingLeaveDates(Collection $leaves, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $dates = collect();

        foreach ($leaves as $leave) {
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
