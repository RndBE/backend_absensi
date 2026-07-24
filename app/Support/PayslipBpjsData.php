<?php

namespace App\Support;

use App\Models\PayrollRunDetail;
use App\Services\BpjsCalculator;
use Carbon\Carbon;

class PayslipBpjsData
{
    public static function fromDetail(PayrollRunDetail $detail): array
    {
        if (self::shouldUseComponentSnapshot($detail)) {
            return self::empty('components');
        }

        $payroll = $detail->employee?->activePayroll;
        if (! $payroll) {
            return self::empty('none');
        }

        $periodDate = Carbon::parse($detail->payrollRun->period.'-01');
        $bpjs = (new BpjsCalculator($periodDate->format('Y-m-d')))->calculate((float) $payroll->basic_salary);
        $bpjs = PayrollBpjs::applyEligibility($bpjs, $payroll, $periodDate);
        $resigned = PayrollBpjs::isResignedInMonth($detail->employee, $periodDate);
        $items = PayrollBpjs::benefitItems($bpjs, $resigned);

        return [
            'source' => 'calculated',
            'raw' => $bpjs,
            'items' => $items,
            'total' => collect($items)->sum('amount'),
        ];
    }

    private static function shouldUseComponentSnapshot(PayrollRunDetail $detail): bool
    {
        if ((bool) $detail->is_manual_edited) {
            return true;
        }

        $period = $detail->payrollRun?->period;
        if (! $period) {
            return false;
        }

        return Carbon::parse($period.'-01')->lt(Carbon::now()->startOfMonth());
    }

    private static function empty(string $source): array
    {
        return [
            'source' => $source,
            'items' => [],
            'total' => 0,
        ];
    }
}
