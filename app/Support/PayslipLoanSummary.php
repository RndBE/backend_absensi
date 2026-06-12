<?php

namespace App\Support;

class PayslipLoanSummary
{
    public static function forComponent(array $component): ?array
    {
        if (! self::isLoanDeduction($component)) {
            return null;
        }

        return self::summaryForComponent($component);
    }

    public static function fromComponents(mixed $components): ?array
    {
        $components = self::normalizeComponents($components);

        foreach ($components as $component) {
            if (! self::isLoanDeduction($component)) {
                continue;
            }

            return self::summaryForComponent($component);
        }

        return null;
    }

    public static function detailLinesForComponent(?array $component): array
    {
        if (! $component) {
            return [];
        }

        $summary = self::forComponent($component);
        if (! $summary) {
            return [];
        }

        $lines = [];
        if ($summary['installment_number'] !== null || $summary['installment_count'] !== null) {
            $lines[] = sprintf(
                'cicilan ke %s dari %s',
                $summary['installment_number'] ?? '-',
                $summary['installment_count'] ?? '-'
            );
        }

        if ($summary['interest_rate'] > 0 || $summary['interest_amount'] > 0) {
            $lines[] = sprintf(
                'bunga %s%% Rp%s',
                self::formatPercent($summary['interest_rate']),
                number_format($summary['interest_amount'], 0, ',', '.')
            );
        }

        if ($summary['remaining_amount'] !== null) {
            $lines[] = 'sisa pinjaman Rp' . number_format($summary['remaining_amount'], 0, ',', '.');
        }

        return $lines;
    }

    private static function normalizeComponents(mixed $components): array
    {
        if (is_array($components)) {
            return $components;
        }

        if (is_string($components)) {
            $decoded = json_decode($components, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private static function isLoanDeduction(array $component): bool
    {
        if (($component['type'] ?? '') !== 'deduction') {
            return false;
        }

        if (isset($component['loan']) || isset($component['loan_summary']) || isset($component['meta']['loan'])) {
            return true;
        }

        $name = strtolower((string) ($component['name'] ?? ''));

        return str_contains($name, 'kasbon') || str_contains($name, 'pinjaman');
    }

    private static function loanPayload(array $component): array
    {
        foreach (['loan', 'loan_summary'] as $key) {
            if (isset($component[$key]) && is_array($component[$key])) {
                return $component[$key];
            }
        }

        if (isset($component['meta']['loan']) && is_array($component['meta']['loan'])) {
            return $component['meta']['loan'];
        }

        return $component;
    }

    private static function summaryForComponent(array $component): array
    {
        $loan = self::loanPayload($component);
        $principalAmount = self::nullableFloatValue($loan, ['principal_amount', 'total_loan', 'total_pinjaman']) ?? 0.0;
        $interestRate = self::nullableFloatValue($loan, ['interest_rate', 'bunga_persen']) ?? 0.0;
        $interestAmount = self::nullableFloatValue($loan, ['interest_amount', 'total_bunga']) ?? 0.0;

        return [
            'principal_amount' => $principalAmount,
            'interest_rate' => $interestRate,
            'interest_amount' => $interestAmount,
            'total_repayable' => self::nullableFloatValue($loan, ['total_repayable', 'total_tagihan', 'total_pembayaran']) ?? ($principalAmount + $interestAmount),
            'current_deduction' => (float) ($component['amount'] ?? 0),
            'installment_number' => self::intValue($loan, ['installment_number', 'cicilan_ke']),
            'installment_count' => self::intValue($loan, ['installment_count', 'total_installments', 'tenor']),
            'paid_amount' => self::nullableFloatValue($loan, ['paid_amount', 'total_paid', 'sudah_dibayar']) ?? 0.0,
            'remaining_amount' => self::nullableFloatValue($loan, ['remaining_amount', 'remaining_loan', 'sisa_pinjaman']),
            'status' => (string) ($loan['status'] ?? 'berjalan'),
        ];
    }

    private static function formatPercent(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, ',', '.'), '0'), ',');
    }

    private static function intValue(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (int) $payload[$key];
            }
        }

        return null;
    }

    private static function nullableFloatValue(array $payload, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (float) $payload[$key];
            }
        }

        return null;
    }
}
