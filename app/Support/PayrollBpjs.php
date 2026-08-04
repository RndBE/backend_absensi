<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeePayroll;
use Carbon\Carbon;

class PayrollBpjs
{
    /** Tanggal batas registrasi BPJS. Join setelah tanggal ini → BPJS mulai bulan depan. */
    public const REGISTRATION_CUTOFF_DAY = 20;

    /** Nama komponen iuran BPJS yang dipotong dari karyawan (type deduction). */
    public const EMPLOYEE_COMPONENT_NAMES = ['BPJS Kesehatan', 'JHT Karyawan', 'JP Karyawan'];

    /** Nama komponen premi BPJS yang ditanggung perusahaan (type info). */
    public const COMPANY_COMPONENT_NAMES = [
        'BPJS Kesehatan Perusahaan', 'JHT Perusahaan', 'JKK Perusahaan', 'JKM Perusahaan', 'JP Perusahaan',
    ];

    /**
     * Kenali komponen iuran BPJS di daftar komponen payslip. Nama dicocokkan PERSIS
     * (bukan str_contains) supaya "BPJS Kesehatan" tidak tertukar dengan
     * "BPJS Kesehatan Perusahaan". Dipakai untuk membuka kunci edit nominal BPJS di
     * payroll run dan untuk menghitung ulang PPh 21 setelahnya.
     */
    public static function isEmployeeComponent(array $component): bool
    {
        return ($component['type'] ?? '') === 'deduction'
            && in_array(trim((string) ($component['name'] ?? '')), self::EMPLOYEE_COMPONENT_NAMES, true);
    }

    public static function isCompanyComponent(array $component): bool
    {
        return ($component['type'] ?? '') === 'info'
            && in_array(trim((string) ($component['name'] ?? '')), self::COMPANY_COMPONENT_NAMES, true);
    }

    public static function isBpjsComponent(array $component): bool
    {
        return self::isEmployeeComponent($component) || self::isCompanyComponent($component);
    }

    /**
     * Total iuran BPJS karyawan dari daftar komponen. Dipakai sebagai pengurang dasar
     * PPh 21 menggantikan $bpjs['employee_total'] hasil BpjsCalculator, agar nominal
     * yang sudah diedit manual ikut terpakai saat pajak dihitung ulang.
     */
    public static function employeeTotalFromComponents(array $components): float
    {
        $total = 0.0;

        foreach ($components as $component) {
            if (is_array($component) && self::isEmployeeComponent($component)) {
                $total += (float) ($component['amount'] ?? 0);
            }
        }

        return $total;
    }

    /** Karyawan baru yang join SETELAH tanggal cutoff di bulan ini (BPJS belum jalan bulan ini). */
    public static function isJoinedAfterCutoff(?Employee $employee, Carbon $periodStart): bool
    {
        $joinDate = $employee?->join_date ? Carbon::parse($employee->join_date) : null;

        return $joinDate
            && $joinDate->isSameMonth($periodStart)
            && $joinDate->day > self::REGISTRATION_CUTOFF_DAY;
    }

    /**
     * Terapkan SEMUA aturan kelayakan BPJS ke array hasil BpjsCalculator, agar tampilan
     * benefit di slip sama persis dengan komponen payroll. Dipakai bersama oleh
     * perhitungan payroll (generateDetails) dan tampilan benefit (buildBpjsData).
     *
     * Urutan: join setelah cutoff → semua 0; tanpa nomor registrasi → program terkait 0.
     *
     * Karyawan yang KELUAR di bulan periode tidak diperlakukan khusus: iuran bulan
     * terakhirnya tetap dihitung penuh, karena laporan mutasi keluar ke BPJS baru
     * efektif bulan berikutnya sehingga tagihan bulan itu masih memuat JHT/JKK/JKM/JP.
     */
    public static function applyEligibility(array $bpjs, EmployeePayroll $payroll, Carbon $periodStart): array
    {
        $employee = $payroll->employee;

        // Karyawan baru join setelah cutoff → semua BPJS 0 (mulai dihitung bulan depan).
        if (self::isJoinedAfterCutoff($employee, $periodStart)) {
            return self::refreshTotals(self::zero($bpjs, ['kesehatan', 'jht', 'jkk', 'jkm', 'jp']));
        }

        // Tanpa nomor registrasi (termasuk placeholder "-") → program terkait 0.
        if (! self::hasRegistrationNumber($payroll->bpjs_kesehatan)) {
            $bpjs = self::zero($bpjs, ['kesehatan']);
        }
        if (! self::hasRegistrationNumber($payroll->bpjs_ketenagakerjaan)) {
            $bpjs = self::zero($bpjs, ['jht', 'jkk', 'jkm', 'jp']);
        }

        return self::refreshTotals($bpjs);
    }

    /**
     * Rakit baris benefit BPJS (ditanggung perusahaan) dari hasil applyEligibility.
     * Baris rate/basis hanya muncul bila iuran terkait aktif bulan ini. Dipakai bersama
     * oleh semua buildBpjsData (Admin/Employee/Api/Job) agar tampilan benefit konsisten.
     */
    public static function benefitItems(array $bpjs): array
    {
        $company = fn (string $k) => (float) ($bpjs[$k]['company'] ?? 0);
        $items = [];

        // Rate/basis Kesehatan hanya tampil bila iuran Kesehatan memang aktif.
        if ($company('kesehatan') > 0) {
            $items[] = ['label' => 'Rate BPJS Kesehatan', 'amount' => $bpjs['kesehatan']['basis'], 'is_basis' => true];
        }

        // Rate/basis Ketenagakerjaan tampil bila ada iuran salah satu programnya.
        if ($company('jht') + $company('jkk') + $company('jkm') + $company('jp') > 0) {
            $items[] = ['label' => 'Rate BPJS Ketenagakerjaan', 'amount' => $bpjs['jht']['basis'], 'is_basis' => true];
        }

        if ($company('jkk') > 0) {
            $items[] = ['label' => 'JKK (Jaminan Kecelakaan Kerja)', 'amount' => $bpjs['jkk']['company'], 'is_basis' => false];
        }
        if ($company('jkm') > 0) {
            $items[] = ['label' => 'JKM (Jaminan Kematian)', 'amount' => $bpjs['jkm']['company'], 'is_basis' => false];
        }
        if ($company('jht') > 0) {
            $items[] = ['label' => 'JHT Perusahaan (Jaminan Hari Tua)', 'amount' => $bpjs['jht']['company'], 'is_basis' => false];
        }
        if ($company('jp') > 0) {
            $items[] = ['label' => 'JP Perusahaan (Jaminan Pensiun)', 'amount' => $bpjs['jp']['company'], 'is_basis' => false];
        }
        if ($company('kesehatan') > 0) {
            $items[] = ['label' => 'BPJS Kesehatan Perusahaan', 'amount' => $bpjs['kesehatan']['company'], 'is_basis' => false];
        }

        return $items;
    }

    /**
     * Nomor registrasi BPJS dianggap ADA hanya jika ada karakter selain spasi/tanda hubung.
     * Placeholder seperti "-", "--", atau kosong berarti belum terdaftar → program di-nol-kan.
     */
    public static function hasRegistrationNumber(mixed $value): bool
    {
        return trim((string) $value, " \t\n\r\0\x0B-") !== '';
    }

    private static function zero(array $bpjs, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($bpjs[$key])) {
                $bpjs[$key]['company'] = 0;
                $bpjs[$key]['employee'] = 0;
            }
        }

        return $bpjs;
    }

    private static function refreshTotals(array $bpjs): array
    {
        $keys = ['kesehatan', 'jht', 'jkk', 'jkm', 'jp'];
        $bpjs['company_total'] = collect($keys)->sum(fn ($k) => (float) ($bpjs[$k]['company'] ?? 0));
        $bpjs['employee_total'] = collect($keys)->sum(fn ($k) => (float) ($bpjs[$k]['employee'] ?? 0));
        $bpjs['grand_total'] = $bpjs['company_total'] + $bpjs['employee_total'];

        return $bpjs;
    }
}
