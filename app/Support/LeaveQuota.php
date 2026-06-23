<?php

namespace App\Support;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Str;

/**
 * Aturan saldo (kuota) per jenis pengajuan.
 *
 * - Cuti Tahunan : berkuota & MEMBLOKIR kalau saldo tidak cukup.
 * - Work From Home (WFH) : berkuota & mengurangi saldo, TAPI tidak memblokir
 *   walau saldo sudah 0, dan saldo tidak pernah minus (di-clamp di 0).
 * - Jenis lain : bebas saldo.
 */
class LeaveQuota
{
    public const ANNUAL_NAME = 'Cuti Tahunan';
    public const WFH_NAME = 'Work From Home';

    public static function isAnnualLeave(?LeaveType $type): bool
    {
        return $type !== null && $type->name === self::ANNUAL_NAME;
    }

    public static function isWfh(?LeaveType $type): bool
    {
        $name = Str::lower((string) ($type?->name ?? ''));

        return Str::contains($name, ['work from home', 'wfh']);
    }

    /** Jenis yang punya saldo (Cuti Tahunan & WFH). */
    public static function tracksBalance(?LeaveType $type): bool
    {
        return self::isAnnualLeave($type) || self::isWfh($type);
    }

    /** Hanya Cuti Tahunan yang menolak pengajuan saat saldo kurang. */
    public static function blocksWhenInsufficient(?LeaveType $type): bool
    {
        return self::isAnnualLeave($type);
    }

    /**
     * Kurangi saldo untuk jenis berkuota saat pengajuan disetujui.
     * Saldo sisa tidak pernah minus.
     */
    public static function deduct(LeaveRequest $item): void
    {
        if (! self::tracksBalance($item->leaveType)) {
            return;
        }

        $balance = LeaveBalance::where('employee_id', $item->employee_id)
            ->where('leave_type_id', $item->leave_type_id)
            ->where('year', now()->year)
            ->first();

        if (! $balance) {
            return;
        }

        $balance->update([
            'used_days' => $balance->used_days + $item->total_days,
            'remaining_days' => max(0, $balance->remaining_days - $item->total_days),
        ]);
    }
}
