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

        return [
            'principal_amount' => self::nullableFloatValue($loan, ['principal_amount', 'total_loan', 'total_pinjaman']) ?? 0.0,
            'current_deduction' => (float) ($component['amount'] ?? 0),
            'installment_number' => self::intValue($loan, ['installment_number', 'cicilan_ke']),
            'installment_count' => self::intValue($loan, ['installment_count', 'total_installments', 'tenor']),
            'paid_amount' => self::nullableFloatValue($loan, ['paid_amount', 'total_paid', 'sudah_dibayar']) ?? 0.0,
            'remaining_amount' => self::nullableFloatValue($loan, ['remaining_amount', 'remaining_loan', 'sisa_pinjaman']),
            'status' => (string) ($loan['status'] ?? 'berjalan'),
        ];
    }

    private static function floatValue(array $payload, array $keys): float
    {
        return self::nullableFloatValue($payload, $keys) ?? 0.0;
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
