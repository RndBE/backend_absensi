<?php

namespace App\Support;

use App\Models\PayrollRunDetail;

class PayrollManualOverrides
{
    public function capture(
        ?array $existingLedger,
        array $before,
        array $submitted,
        ?float $basicBefore = null,
        ?float $basicSubmitted = null
    ): array {
        $ledger = $this->normalizeLedger($existingLedger);
        $beforeByKey = $this->componentsByKey($before);
        $submittedByKey = $this->componentsByKey($submitted);

        foreach ($submittedByKey as $key => $component) {
            $previous = $beforeByKey[$key] ?? null;

            if ($previous === null || $this->componentsDiffer($previous, $component)) {
                $ledger['components'][$key] = $component;
                unset($ledger['removed'][$key]);
                if ($previous === null) {
                    $ledger['added'][$key] = true;
                }
            }
        }

        foreach ($beforeByKey as $key => $component) {
            if (array_key_exists($key, $submittedByKey)) {
                continue;
            }

            $wasManuallyAdded = ! empty($ledger['added'][$key]);
            unset($ledger['components'][$key], $ledger['added'][$key]);
            if (! $wasManuallyAdded) {
                $ledger['removed'][$key] = $component;
            } else {
                unset($ledger['removed'][$key]);
            }
        }

        if ($basicBefore !== null && $basicSubmitted !== null && round($basicBefore, 2) !== round($basicSubmitted, 2)) {
            $ledger['basic_salary'] = round($basicSubmitted, 2);
        }

        return $this->withoutEmptySections($ledger);
    }

    public function deriveLegacy(PayrollRunDetail $old, PayrollRunDetail $fresh): array
    {
        $ledger = $this->normalizeLedger(null);
        $oldBasic = (float) $old->basic_salary;
        $freshBasic = (float) $fresh->basic_salary;

        if (round($oldBasic, 2) !== round($freshBasic, 2)) {
            $ledger['basic_salary'] = round($oldBasic, 2);
        }

        $freshByKey = $this->componentsByKey($fresh->components ?? []);
        foreach ($this->componentsByKey($old->components ?? []) as $key => $component) {
            $generated = $freshByKey[$key] ?? null;

            if (! $this->isAutomatic($component)
                || ($generated !== null && $this->componentsDiffer($component, $generated))) {
                $ledger['components'][$key] = $component;
                if ($generated === null) {
                    $ledger['added'][$key] = true;
                }
            }
        }

        return $this->withoutEmptySections($ledger);
    }

    public function apply(array $generated, array $ledger, ?callable $filter = null): array
    {
        $result = array_values(array_filter($generated, 'is_array'));
        $ledger = $this->normalizeLedger($ledger);

        foreach ($ledger['removed'] as $key => $removed) {
            if ($filter !== null && ! $filter($removed)) {
                continue;
            }

            $result = $this->removeByKey($result, $key);
        }

        foreach ($ledger['components'] as $key => $component) {
            if ($filter !== null && ! $filter($component)) {
                continue;
            }

            $result = $this->replaceOrAppend($result, $key, $component);
        }

        return array_values($result);
    }

    public function basicSalary(float $generated, array $ledger): float
    {
        $manual = $ledger['basic_salary'] ?? null;

        return is_numeric($manual) ? (float) $manual : $generated;
    }

    /**
     * @return array{total_earning: float, total_deduction: float, net_salary: float}
     */
    public function totals(float $basicSalary, array $components): array
    {
        $totalEarning = $basicSalary;
        $totalDeduction = 0.0;

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            if (($component['type'] ?? null) === 'earning') {
                $totalEarning += (float) ($component['amount'] ?? 0);
            } elseif (($component['type'] ?? null) === 'deduction') {
                $totalDeduction += (float) ($component['amount'] ?? 0);
            }
        }

        return [
            'total_earning' => $totalEarning,
            'total_deduction' => $totalDeduction,
            'net_salary' => $totalEarning - $totalDeduction,
        ];
    }

    private function normalizeLedger(?array $ledger): array
    {
        return [
            'basic_salary' => isset($ledger['basic_salary']) && is_numeric($ledger['basic_salary'])
                ? (float) $ledger['basic_salary']
                : null,
            'components' => is_array($ledger['components'] ?? null)
                ? array_filter($ledger['components'], 'is_array')
                : [],
            'removed' => is_array($ledger['removed'] ?? null)
                ? array_filter($ledger['removed'], 'is_array')
                : [],
            'added' => is_array($ledger['added'] ?? null)
                ? array_filter($ledger['added'], fn ($value) => (bool) $value)
                : [],
        ];
    }

    private function withoutEmptySections(array $ledger): array
    {
        if ($ledger['basic_salary'] === null) {
            unset($ledger['basic_salary']);
        }
        if ($ledger['components'] === []) {
            unset($ledger['components']);
        }
        if ($ledger['removed'] === []) {
            unset($ledger['removed']);
        }
        if ($ledger['added'] === []) {
            unset($ledger['added']);
        }

        return $ledger;
    }

    private function componentsByKey(array $components): array
    {
        $result = [];
        $occurrences = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $baseKey = $this->baseKey($component);
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;
            $key = $baseKey;
            if ($occurrences[$baseKey] > 1) {
                $key .= '#'.$occurrences[$baseKey];
            }

            $result[$key] = $this->normalizeComponent($component);
        }

        return $result;
    }

    private function baseKey(array $component): string
    {
        return strtolower(trim((string) ($component['type'] ?? '')))
            .'|'.strtolower(trim((string) ($component['name'] ?? '')));
    }

    private function normalizeComponent(array $component): array
    {
        $component['name'] = trim((string) ($component['name'] ?? ''));
        $component['type'] = strtolower(trim((string) ($component['type'] ?? '')));
        $component['amount'] = round((float) ($component['amount'] ?? 0), 2);
        $component['is_auto'] = filter_var($component['is_auto'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $component['is_taxable'] = filter_var($component['is_taxable'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $component;
    }

    private function componentsDiffer(array $before, array $after): bool
    {
        $fields = ['name', 'type', 'category', 'amount', 'is_taxable', 'is_auto', 'detail'];
        $before = $this->normalizeComponent($before);
        $after = $this->normalizeComponent($after);

        foreach ($fields as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function isAutomatic(array $component): bool
    {
        return filter_var($component['is_auto'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function removeByKey(array $components, string $targetKey): array
    {
        $occurrences = [];

        return array_values(array_filter($components, function (array $component) use ($targetKey, &$occurrences) {
            $baseKey = $this->baseKey($component);
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;
            $key = $baseKey.($occurrences[$baseKey] > 1 ? '#'.$occurrences[$baseKey] : '');

            return $key !== $targetKey;
        }));
    }

    private function replaceOrAppend(array $components, string $targetKey, array $replacement): array
    {
        $occurrences = [];

        foreach ($components as $index => $component) {
            $baseKey = $this->baseKey($component);
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;
            $key = $baseKey.($occurrences[$baseKey] > 1 ? '#'.$occurrences[$baseKey] : '');

            if ($key === $targetKey) {
                $components[$index] = $replacement;

                return $components;
            }
        }

        $components[] = $replacement;

        return $components;
    }
}
