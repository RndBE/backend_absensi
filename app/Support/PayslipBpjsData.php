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
            return self::fromComponentSnapshot($detail->components);
        }

        $payroll = $detail->employee?->activePayroll;
        if (! $payroll) {
            return self::empty('none');
        }

        $periodDate = Carbon::parse($detail->payrollRun->period.'-01');
        $bpjs = (new BpjsCalculator($periodDate->format('Y-m-d')))->calculate((float) $payroll->basic_salary);
        $bpjs = PayrollBpjs::applyEligibility($bpjs, $payroll, $periodDate);
        $items = PayrollBpjs::benefitItems($bpjs);

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

    private static function fromComponentSnapshot(mixed $components): array
    {
        $components = self::componentList($components);
        $explicitBasis = ['kesehatan' => 0.0, 'ketenagakerjaan' => 0.0];
        $inferredBasis = ['kesehatan' => 0.0, 'ketenagakerjaan' => 0.0];
        $contributions = [
            'jkk' => 0.0,
            'jkm' => 0.0,
            'jht' => 0.0,
            'jp' => 0.0,
            'kesehatan' => 0.0,
        ];

        foreach ($components as $component) {
            if (($component['type'] ?? '') !== 'info') {
                continue;
            }

            $name = self::normalizeLabel((string) ($component['name'] ?? ''));
            $amount = (float) ($component['amount'] ?? 0);

            if ($name === 'rate_bpjs_kesehatan') {
                $explicitBasis['kesehatan'] = $amount;

                continue;
            }
            if ($name === 'rate_bpjs_ketenagakerjaan') {
                $explicitBasis['ketenagakerjaan'] = $amount;

                continue;
            }

            $basis = self::basisFromDetail((string) ($component['detail'] ?? ''));

            if (str_contains($name, 'bpjs_kesehatan') && str_contains($name, 'perusahaan')) {
                $contributions['kesehatan'] = $amount;
                $inferredBasis['kesehatan'] = $basis;
            } elseif (str_contains($name, 'jkk')) {
                $contributions['jkk'] = $amount;
                $inferredBasis['ketenagakerjaan'] = $inferredBasis['ketenagakerjaan'] ?: $basis;
            } elseif (str_contains($name, 'jkm')) {
                $contributions['jkm'] = $amount;
                $inferredBasis['ketenagakerjaan'] = $inferredBasis['ketenagakerjaan'] ?: $basis;
            } elseif (str_contains($name, 'jht')) {
                $contributions['jht'] = $amount;
                $inferredBasis['ketenagakerjaan'] = $inferredBasis['ketenagakerjaan'] ?: $basis;
            } elseif (preg_match('/(^|_)jp(_|$)/', $name) && str_contains($name, 'perusahaan')) {
                $contributions['jp'] = $amount;
                $inferredBasis['ketenagakerjaan'] = $inferredBasis['ketenagakerjaan'] ?: $basis;
            }
        }

        $basis = [
            'kesehatan' => $explicitBasis['kesehatan'] ?: $inferredBasis['kesehatan'],
            'ketenagakerjaan' => $explicitBasis['ketenagakerjaan'] ?: $inferredBasis['ketenagakerjaan'],
        ];
        $items = [];

        self::pushSnapshotItem($items, 'Rate BPJS Kesehatan', $basis['kesehatan'], true);
        self::pushSnapshotItem($items, 'Rate BPJS Ketenagakerjaan', $basis['ketenagakerjaan'], true);
        self::pushSnapshotItem($items, 'JKK (Jaminan Kecelakaan Kerja)', $contributions['jkk']);
        self::pushSnapshotItem($items, 'JKM (Jaminan Kematian)', $contributions['jkm']);
        self::pushSnapshotItem($items, 'JHT Perusahaan (Jaminan Hari Tua)', $contributions['jht']);
        self::pushSnapshotItem($items, 'JP Perusahaan (Jaminan Pensiun)', $contributions['jp']);
        self::pushSnapshotItem($items, 'BPJS Kesehatan Perusahaan', $contributions['kesehatan']);

        return [
            'source' => 'components',
            'items' => $items,
            'total' => array_sum(array_column($items, 'amount')),
        ];
    }

    private static function componentList(mixed $components): array
    {
        if (is_array($components)) {
            return $components;
        }

        $decoded = json_decode((string) $components, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function basisFromDetail(string $detail): float
    {
        if (! preg_match('/x\s*Rp\s*([\d.,]+)/i', $detail, $matches)) {
            return 0.0;
        }

        $amount = str_replace('.', '', $matches[1]);
        $amount = str_replace(',', '.', $amount);

        return (float) $amount;
    }

    private static function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/[^a-z0-9]+/', '_', $label);

        return trim((string) $label, '_');
    }

    private static function pushSnapshotItem(array &$items, string $label, float $amount, bool $isBasis = false): void
    {
        if ($amount <= 0) {
            return;
        }

        $items[] = [
            'label' => $label,
            'amount' => $amount,
            'is_basis' => $isBasis,
        ];
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
