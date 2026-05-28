<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalChainSeeder extends Seeder
{
    private const TYPES = ['leave', 'overtime', 'attendance', 'budget'];

    public function run(): void
    {
        $this->command->info('════════════════════════════════════════════');
        $this->command->info('  SEEDER APPROVAL CHAIN - PT. ARTA TEKNOLOGI');
        $this->command->info('════════════════════════════════════════════');

        // Hapus chain lama sebelum insert ulang
        DB::table('employee_approvers')->truncate();

        // Ambil semua karyawan yang punya approver_id
        $employees = DB::table('employees')
            ->whereNotNull('approver_id')
            ->get(['id', 'full_name', 'job_level', 'manager_id', 'approver_id']);

        // Buat lookup job_level by ID untuk menentukan urutan step
        $levelMap = DB::table('employees')
            ->pluck('job_level', 'id')
            ->toArray();

        $records = [];
        $now     = now()->toDateTimeString();

        foreach ($employees as $emp) {
            $approverId = $emp->approver_id;
            $managerId  = $emp->manager_id;

            // ── Single-step: approver sama dengan manager (atau manager tidak ada) ──
            if (!$managerId || $managerId === $approverId) {
                foreach (self::TYPES as $type) {
                    $records[] = $this->row($emp->id, $type, 1, $approverId, $now);
                }

                $this->command->line("  1-step | {$emp->full_name}");
                continue;
            }

            // ── Two-step: manager ≠ approver ──
            // Urutan: karyawan yang job_level-nya LEBIH TINGGI (angka lebih besar = lebih bawah
            // di hierarki) menjadi step 1, yang lebih senior menjadi step 2.
            $approverLevel = $levelMap[$approverId] ?? 99;
            $managerLevel  = $levelMap[$managerId]  ?? 99;

            if ($approverLevel >= $managerLevel) {
                // Approver lebih rendah/sama di hierarki → approver dulu, lalu manager
                [$step1, $step2] = [$approverId, $managerId];
            } else {
                // Manager lebih rendah di hierarki → manager dulu, lalu approver
                [$step1, $step2] = [$managerId, $approverId];
            }

            foreach (self::TYPES as $type) {
                $records[] = $this->row($emp->id, $type, 1, $step1, $now);
                $records[] = $this->row($emp->id, $type, 2, $step2, $now);
            }

            $step1Name = DB::table('employees')->where('id', $step1)->value('full_name');
            $step2Name = DB::table('employees')->where('id', $step2)->value('full_name');
            $this->command->line("  2-step | {$emp->full_name}");
            $this->command->line("          step1 → {$step1Name}");
            $this->command->line("          step2 → {$step2Name}");
        }

        DB::table('employee_approvers')->insert($records);

        $empCount  = $employees->count();
        $rowCount  = count($records);
        $typeCount = count(self::TYPES);

        $this->command->info('════════════════════════════════════════════');
        $this->command->info("  SELESAI! {$empCount} karyawan × {$typeCount} tipe = {$rowCount} approval rules.");
        $this->command->info('════════════════════════════════════════════');
    }

    private function row(int $empId, string $type, int $step, int $approverId, string $now): array
    {
        return [
            'employee_id'  => $empId,
            'request_type' => $type,
            'step_order'   => $step,
            'approver_id'  => $approverId,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }
}
