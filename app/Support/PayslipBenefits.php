<?php

namespace App\Support;

class PayslipBenefits
{
    public static function from(array $bpjsData = [], mixed $components = []): array
    {
        $items = [];
        $seen = [];
        $notes = [];
        $seenNotes = [];
        $componentList = self::componentList($components);

        foreach (($bpjsData['items'] ?? []) as $item) {
            self::pushItem(
                $items,
                $seen,
                (string) ($item['label'] ?? ''),
                (float) ($item['amount'] ?? 0),
                (bool) ($item['is_basis'] ?? false)
            );
        }

        foreach ($componentList as $component) {
            if (($component['type'] ?? '') !== 'info') {
                continue;
            }

            $name = trim((string) ($component['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            self::pushItem(
                $items,
                $seen,
                $name,
                (float) ($component['amount'] ?? 0),
                self::isBasisLabel($name)
            );

            self::pushNote(
                $notes,
                $seenNotes,
                $name,
                trim((string) ($component['detail'] ?? ''))
            );
        }

        self::pushImportedRateContributions($items, $seen, $notes, $seenNotes, $componentList);
        self::pushBpjsRawNotes($notes, $seenNotes, $bpjsData['raw'] ?? []);

        return [
            'items' => $items,
            'total' => array_sum(array_column($items, 'amount')),
            'notes' => $notes,
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

    private static function pushItem(array &$items, array &$seen, string $label, float $amount, bool $isBasis): void
    {
        $label = trim($label);
        if ($label === '' || $amount <= 0) {
            return;
        }

        $key = self::itemKey($label, $isBasis);
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $items[] = [
            'label' => $label,
            'amount' => $amount,
            'is_basis' => $isBasis,
        ];
    }

    private static function pushImportedRateContributions(
        array &$items,
        array &$seen,
        array &$notes,
        array &$seenNotes,
        array $components
    ): void {
        if (self::hasImportedCompanyContribution($components)) {
            return;
        }

        $basis = self::importedRateBasis($components);

        if ($basis['ketenagakerjaan'] > 0) {
            self::pushCalculatedContribution($items, $seen, $notes, $seenNotes, 'JKK (Jaminan Kecelakaan Kerja)', 'JKK Perusahaan', $basis['ketenagakerjaan'], 0.24);
            self::pushCalculatedContribution($items, $seen, $notes, $seenNotes, 'JKM (Jaminan Kematian)', 'JKM Perusahaan', $basis['ketenagakerjaan'], 0.3);
            self::pushCalculatedContribution($items, $seen, $notes, $seenNotes, 'JHT Perusahaan (Jaminan Hari Tua)', 'JHT Perusahaan', $basis['ketenagakerjaan'], 3.7);
        }

        if ($basis['kesehatan'] > 0) {
            self::pushCalculatedContribution($items, $seen, $notes, $seenNotes, 'BPJS Kesehatan Perusahaan', 'BPJS Kesehatan Perusahaan', $basis['kesehatan'], 4);
        }
    }

    private static function hasImportedCompanyContribution(array $components): bool
    {
        foreach ($components as $component) {
            if (($component['type'] ?? '') !== 'info') {
                continue;
            }

            $name = self::normalizeLabel((string) ($component['name'] ?? ''));
            if ($name === '' || self::isBasisLabel($name)) {
                continue;
            }

            foreach (['perusahaan', 'jkk', 'jkm', 'jht', 'jaminan'] as $keyword) {
                if (str_contains($name, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function importedRateBasis(array $components): array
    {
        $basis = [
            'kesehatan' => 0.0,
            'ketenagakerjaan' => 0.0,
        ];

        foreach ($components as $component) {
            if (($component['type'] ?? '') !== 'info') {
                continue;
            }

            $name = self::normalizeLabel((string) ($component['name'] ?? ''));
            $amount = (float) ($component['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            if ($name === 'rate_bpjs_kesehatan') {
                $basis['kesehatan'] = $amount;
            } elseif ($name === 'rate_bpjs_ketenagakerjaan') {
                $basis['ketenagakerjaan'] = $amount;
            }
        }

        return $basis;
    }

    private static function pushCalculatedContribution(
        array &$items,
        array &$seen,
        array &$notes,
        array &$seenNotes,
        string $itemLabel,
        string $noteLabel,
        float $basis,
        float $percent
    ): void {
        $amount = round($basis * $percent / 100, 0);
        if ($amount <= 0) {
            return;
        }

        self::pushItem($items, $seen, $itemLabel, $amount, false);
        self::pushNote(
            $notes,
            $seenNotes,
            $noteLabel,
            self::formatPercent($percent).' x Rp '.number_format($basis, 0, ',', '.')
        );
    }

    private static function pushNote(array &$notes, array &$seenNotes, string $label, string $detail): void
    {
        $label = trim($label);
        $detail = trim($detail);
        if ($label === '' || $detail === '') {
            return;
        }

        $key = self::normalizeLabel($label);
        if (isset($seenNotes[$key])) {
            return;
        }

        $seenNotes[$key] = true;
        $notes[] = [
            'label' => $label,
            'detail' => $detail,
        ];
    }

    private static function pushBpjsRawNotes(array &$notes, array &$seenNotes, mixed $raw): void
    {
        if (! is_array($raw)) {
            return;
        }

        $rawNotes = [
            'kesehatan' => 'BPJS Kesehatan Perusahaan',
            'jht' => 'JHT Perusahaan',
            'jkk' => 'JKK Perusahaan',
            'jkm' => 'JKM Perusahaan',
            'jp' => 'JP Perusahaan',
        ];

        foreach ($rawNotes as $key => $label) {
            $basis = (float) ($raw[$key]['basis'] ?? 0);
            $company = (float) ($raw[$key]['company'] ?? 0);
            if ($basis <= 0 || $company <= 0) {
                continue;
            }

            $percent = ($company / $basis) * 100;
            self::pushNote(
                $notes,
                $seenNotes,
                $label,
                self::formatPercent($percent).' x Rp '.number_format($basis, 0, ',', '.')
            );
        }
    }

    private static function isBasisLabel(string $label): bool
    {
        return str_starts_with(self::normalizeLabel($label), 'rate_bpjs');
    }

    private static function formatPercent(float $percent): string
    {
        $formatted = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return $formatted.'%';
    }

    private static function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/[^a-z0-9]+/', '_', $label);

        return trim((string) $label, '_');
    }

    private static function itemKey(string $label, bool $isBasis): string
    {
        $normalized = self::normalizeLabel($label);

        if ($isBasis) {
            return $normalized;
        }

        if (str_contains($normalized, 'bpjs_kesehatan') && str_contains($normalized, 'perusahaan')) {
            return 'bpjs_kesehatan_perusahaan';
        }

        if (str_contains($normalized, 'jkk')) {
            return 'bpjs_jkk_perusahaan';
        }

        if (str_contains($normalized, 'jkm')) {
            return 'bpjs_jkm_perusahaan';
        }

        if (str_contains($normalized, 'jht') && (str_contains($normalized, 'perusahaan') || str_contains($normalized, 'jaminan_hari_tua'))) {
            return 'bpjs_jht_perusahaan';
        }

        if (preg_match('/(^|_)jp(_|$)/', $normalized) && str_contains($normalized, 'perusahaan')) {
            return 'bpjs_jp_perusahaan';
        }

        return $normalized;
    }
}
