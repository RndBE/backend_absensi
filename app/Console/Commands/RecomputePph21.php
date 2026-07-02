<?php

namespace App\Console\Commands;

use App\Models\EmployeePayroll;
use App\Models\PayrollRun;
use App\Services\BpjsCalculator;
use App\Services\Pph21Calculator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Recompute PPh 21 in-place untuk payslip yang sudah tergenerate, memakai basis
 * bruto pajak yang benar (termasuk premi pemberi kerja objek pajak: JKK, JKM,
 * BPJS Kesehatan 4%). Tidak menyentuh komponen lain — hanya mengganti Tunjangan
 * Pajak (Gross Up) + PPh 21 dan menyesuaikan total.
 *
 * Hanya menangani jalur TER bulanan (Jan-Nov). Detail bulan Desember dan bulan
 * resign DILEWATI (butuh penghitungan ulang tahunan/progresif) dan dilaporkan.
 *
 * Default DRY-RUN (tidak menulis). Gunakan --apply untuk menyimpan.
 */
class RecomputePph21 extends Command
{
    protected $signature = 'payroll:recompute-pph
        {run? : ID payroll run tertentu; default semua run non-draft}
        {--apply : Simpan perubahan (tanpa flag ini hanya dry-run)}';

    protected $description = 'Recompute PPh 21 payslip lama dengan basis bruto pajak yang benar';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $runs = PayrollRun::query()
            ->when($this->argument('run'), fn ($q) => $q->where('id', $this->argument('run')))
            ->when(! $this->argument('run'), fn ($q) => $q->where('status', '!=', 'draft'))
            ->orderBy('period')
            ->get();

        if ($runs->isEmpty()) {
            $this->warn('Tidak ada payroll run yang cocok.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'APPLY' : 'DRY-RUN').' — memproses '.$runs->count().' run');

        $totalChanged = 0;
        $totalDelta = 0.0;
        $skipped = [];
        $rows = [];

        foreach ($runs as $run) {
            $month = (int) substr($run->period, 5, 2);
            $periodStart = Carbon::parse($run->period.'-01')->startOfMonth();
            $periodEnd = (clone $periodStart)->endOfMonth();

            foreach ($run->details()->with('employee')->get() as $detail) {
                $employee = $detail->employee;
                $payroll = EmployeePayroll::where('employee_id', $detail->employee_id)
                    ->where('is_active', true)->first();

                if (! $employee || ! $payroll) {
                    $skipped[] = "detail {$detail->id}: tidak ada employee/payroll aktif";
                    continue;
                }

                // Lewati Desember (penghitungan kembali tahunan)
                if ($month === 12) {
                    $skipped[] = "detail {$detail->id} ({$employee->full_name}): Desember — regenerate manual";
                    continue;
                }

                // Lewati bulan resign (penghitungan progresif)
                $exitDate = $employee->last_working_date ?: $employee->resign_date;
                if ($exitDate && Carbon::parse($exitDate)->between($periodStart, $periodEnd)) {
                    $skipped[] = "detail {$detail->id} ({$employee->full_name}): bulan resign — regenerate manual";
                    continue;
                }

                // Lewati payslip skema LEGACY (generator/impor lama): nama komponen
                // berbeda (PPH 21, Tax Allowance, Rate BPJS...) dan premi pemberi
                // kerja tidak tersimpan sebagai nominal premi. Tidak bisa direcompute
                // dengan aman — harus regenerate ulang di sistem baru.
                if ($this->isLegacySchema($detail)) {
                    $skipped[] = "detail {$detail->id} ({$employee->full_name}): skema legacy — regenerate ulang";
                    continue;
                }

                $result = $this->recomputeDetail($detail, $payroll, $run, $periodStart);
                if ($result === null) {
                    continue;
                }

                [$oldPph, $newPph, $newComps, $totalEarning, $totalDeduction] = $result;
                $delta = $newPph - $oldPph;

                if (abs($delta) < 0.5) {
                    continue; // tidak berubah
                }

                $rows[] = [
                    $detail->id,
                    $employee->full_name,
                    $run->period,
                    number_format($oldPph, 0, ',', '.'),
                    number_format($newPph, 0, ',', '.'),
                    ($delta >= 0 ? '+' : '').number_format($delta, 0, ',', '.'),
                ];
                $totalChanged++;
                $totalDelta += $delta;

                if ($apply) {
                    $detail->update([
                        'components' => $newComps,
                        'total_earning' => $totalEarning,
                        'total_deduction' => $totalDeduction,
                        'net_salary' => $totalEarning - $totalDeduction,
                    ]);
                }
            }
        }

        if ($rows) {
            $this->table(['Detail', 'Karyawan', 'Periode', 'PPh Lama', 'PPh Baru', 'Selisih'], $rows);
        }

        $this->info("Detail berubah: {$totalChanged} | Total selisih PPh: ".number_format($totalDelta, 0, ',', '.'));

        if ($skipped) {
            $this->warn('Dilewati ('.count($skipped).'):');
            foreach ($skipped as $s) {
                $this->line('  - '.$s);
            }
        }

        if (! $apply && $totalChanged > 0) {
            $this->newLine();
            $this->comment('Dry-run. Jalankan dengan --apply untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /**
     * Deteksi payslip skema lama yang tidak kompatibel dengan generator saat ini.
     */
    private function isLegacySchema($detail): bool
    {
        $comps = is_array($detail->components)
            ? $detail->components
            : (json_decode($detail->components, true) ?? []);

        foreach ($comps as $c) {
            $n = $c['name'] ?? '';
            if (str_contains($n, 'Rate BPJS') || $n === 'Tax Allowance' || $n === 'PPH 21') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:float,1:float,2:array,3:float,4:float}|null
     */
    private function recomputeDetail($detail, EmployeePayroll $payroll, PayrollRun $run, Carbon $periodStart): ?array
    {
        $comps = is_array($detail->components)
            ? $detail->components
            : (json_decode($detail->components, true) ?? []);

        // PPh lama = jumlah komponen PPh 21 bertipe deduction
        $oldPph = 0.0;
        foreach ($comps as $c) {
            if (str_contains($c['name'] ?? '', 'PPh 21') && ($c['type'] ?? '') === 'deduction') {
                $oldPph += (float) ($c['amount'] ?? 0);
            }
        }

        // Buang komponen pajak lama (Tunjangan Pajak + PPh 21), sisakan basis
        $base = array_values(array_filter($comps, function ($c) {
            $n = $c['name'] ?? '';

            return ! str_contains($n, 'Tunjangan Pajak') && ! str_contains($n, 'PPh 21');
        }));

        // Total dasar (pra-pajak)
        $totalEarning = (float) $detail->basic_salary;
        $totalDeduction = 0.0;
        foreach ($base as $c) {
            if (($c['type'] ?? '') === 'earning') {
                $totalEarning += (float) ($c['amount'] ?? 0);
            } elseif (($c['type'] ?? '') === 'deduction') {
                $totalDeduction += (float) ($c['amount'] ?? 0);
            }
        }

        $ptkpStatus = $payroll->ptkp_status ?: 'TK/0';
        $taxMethod = $payroll->tax_method ?? 'gross_up';

        $brutoTaxable = Pph21Calculator::taxableBrutoFromComponents((float) $detail->basic_salary, $base);

        $bpjs = (new BpjsCalculator($periodStart->format('Y-m-d')))->calculate((float) $payroll->basic_salary);
        $tax = (new Pph21Calculator($periodStart->format('Y-m-d')))
            ->calculateMonthly($brutoTaxable, $ptkpStatus, $taxMethod, $bpjs['employee_total']);

        // Tunjangan Pajak (gross up)
        if ($taxMethod === 'gross_up' && ($tax['tunjangan_pajak'] ?? 0) > 0) {
            $base[] = [
                'id' => null,
                'name' => 'Tunjangan Pajak (Gross Up)',
                'type' => 'earning',
                'category' => 'recurring',
                'amount' => $tax['tunjangan_pajak'],
                'is_taxable' => true,
                'is_auto' => true,
                'detail' => 'PPh 21 ditanggung perusahaan',
            ];
            $totalEarning += $tax['tunjangan_pajak'];
        }

        $newPph = 0.0;
        if (($tax['pph21_deduction'] ?? 0) > 0) {
            $isDtp = $payroll->pph21_dtp ?? false;
            $base[] = [
                'id' => null,
                'name' => 'PPh 21'.($isDtp ? ' (DTP)' : ''),
                'type' => $isDtp ? 'info' : 'deduction',
                'category' => 'recurring',
                'amount' => $tax['pph21_deduction'],
                'is_taxable' => false,
                'is_auto' => true,
                'detail' => 'Recompute basis bruto pajak — TER '.number_format((float) ($tax['ter_rate'] ?? 0), 2, ',', '.').'%',
            ];
            if (! $isDtp) {
                $totalDeduction += $tax['pph21_deduction'];
                $newPph = (float) $tax['pph21_deduction'];
            }
        }

        return [$oldPph, $newPph, $base, $totalEarning, $totalDeduction];
    }
}
