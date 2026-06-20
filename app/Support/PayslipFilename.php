<?php

namespace App\Support;

class PayslipFilename
{
    public static function make(?string $employeeCode, ?string $period): string
    {
        return 'Payslip_'.self::safeSegment($employeeCode ?: 'employee').'_'.self::safeSegment($period ?: 'period').'.pdf';
    }

    private static function safeSegment(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\/\\\\]+/', '-', $value);
        $value = preg_replace('/[^\pL\pN._-]+/u', '-', $value);
        $value = trim((string) $value, '.-_');

        return $value !== '' ? $value : 'file';
    }
}
