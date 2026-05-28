<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class KaryawanPtArtaSeederApprovalMappingTest extends TestCase
{
    public function test_approval_mapping_matches_level_rules(): void
    {
        $expected = [
            3 => [2, 2],
            4 => [2, 2],
            5 => [2, 2],
            18 => [2, 2],
            11 => [2, 2],

            6 => [18, 18],
            7 => [5, 5],
            8 => [4, 4],
            9 => [3, 3],
            10 => [3, 3],

            12 => [5, 5],
            13 => [2, 11],
            14 => [5, 7],
            15 => [5, 7],
            16 => [5, 5],
            17 => [2, 11],
            19 => [4, 8],
            20 => [3, 10],
            21 => [3, 3],
            22 => [3, 9],
            23 => [3, 9],
            25 => [2, 11],
            26 => [2, 11],
            27 => [2, 11],
            28 => [18, 6],
            29 => [5, 7],
            30 => [3, 10],
            31 => [4, 8],
            32 => [4, 8],
            33 => [18, 6],
            34 => [5, 7],
            35 => [5, 5],
        ];

        foreach ($expected as $origId => [$managerId, $approverId]) {
            $row = $this->employeeSeederRow($origId);

            $this->assertSame(
                $managerId,
                $row['manager'],
                "Unexpected manager for orig_id {$origId}."
            );
            $this->assertSame(
                $approverId,
                $row['approver'],
                "Unexpected approver for orig_id {$origId}."
            );
        }
    }

    /**
     * @return array{manager:int|null, approver:int|null}
     */
    private function employeeSeederRow(int $origId): array
    {
        $source = file_get_contents(__DIR__ . '/../../database/seeders/KaryawanPtArtaSeeder.php');
        $source = preg_replace('/^\s*\/\/.*$/m', '', $source);

        $pattern = "/\\['orig_id'=>{$origId},.*?'schedule_template_id'=>.*?,'orig_manager_id'=>(?<manager>null|\\d+),'orig_approver_id'=>(?<approver>null|\\d+)\\]/s";

        $this->assertSame(1, preg_match($pattern, $source, $matches), "Missing orig_id {$origId} in seeder.");

        return [
            'manager' => $matches['manager'] === 'null' ? null : (int) $matches['manager'],
            'approver' => $matches['approver'] === 'null' ? null : (int) $matches['approver'],
        ];
    }
}
