<?php

namespace App\Support;

final class ApprovalChainInput
{
    public static function steps(mixed $steps): mixed
    {
        if (! is_array($steps)) {
            return $steps;
        }

        return array_values(array_filter(
            $steps,
            static fn (mixed $employeeId): bool => $employeeId !== null && $employeeId !== ''
        ));
    }

    public static function chains(mixed $chains): mixed
    {
        if (! is_array($chains)) {
            return $chains;
        }

        return array_map(
            static fn (mixed $steps): mixed => self::steps($steps),
            $chains
        );
    }
}
