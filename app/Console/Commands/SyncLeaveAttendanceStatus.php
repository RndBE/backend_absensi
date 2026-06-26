<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Support\AttendanceLateExcuse;
use App\Support\AttendanceLeaveSync;
use Illuminate\Console\Command;

/**
 * Backfill: perbaiki data lama agar status absensi pada hari izin parsial
 * (datang terlambat / pulang cepat) yang sudah di-ACC tersimpan sebagai
 * 'late_excuse' / 'early_departure' di kolom attendances.status.
 */
class SyncLeaveAttendanceStatus extends Command
{
    protected $signature = 'attendance:sync-leave-status
        {--company= : Batasi hanya untuk karyawan pada company tertentu}
        {--from= : Hanya izin yang beririsan dari tanggal ini (Y-m-d)}
        {--to= : Hanya izin yang beririsan sampai tanggal ini (Y-m-d)}
        {--dry-run : Tampilkan jumlah yang akan diubah tanpa menyimpan}';

    protected $description = 'Sinkronkan status absensi lama dengan izin datang terlambat / pulang cepat yang sudah disetujui';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = LeaveRequest::with(['leaveType', 'employee:id,company_id'])
            ->where('status', 'approved');

        if ($company = $this->option('company')) {
            $query->whereHas('employee', fn ($q) => $q->where('company_id', $company));
        }
        if ($from = $this->option('from')) {
            $query->where('end_date', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->where('start_date', '<=', $to);
        }

        // Hanya izin parsial (datang terlambat / pulang cepat) yang relevan.
        $leaves = $query->get()->filter(
            fn (LeaveRequest $leave) => AttendanceLeaveSync::targetStatusFor($leave) !== null
        );

        if ($leaves->isEmpty()) {
            $this->info('Tidak ada izin datang terlambat / pulang cepat yang disetujui untuk diproses.');
            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Memproses {$leaves->count()} izin parsial yang disetujui...");

        $lateExcuse = 0;
        $earlyDeparture = 0;

        foreach ($leaves as $leave) {
            $affected = $dryRun
                ? AttendanceLeaveSync::previewApplyCount($leave)
                : AttendanceLeaveSync::apply($leave);

            if ($affected === 0) {
                continue;
            }

            if (AttendanceLeaveSync::targetStatusFor($leave) === AttendanceLateExcuse::LATE_EXCUSE_STATUS) {
                $lateExcuse += $affected;
            } else {
                $earlyDeparture += $affected;
            }
        }

        $verb = $dryRun ? 'akan diperbarui' : 'diperbarui';
        $this->info("✅ Selesai. Absensi {$verb}: {$lateExcuse} -> Izin Terlambat, {$earlyDeparture} -> Izin Pulang Cepat.");

        if ($dryRun && ($lateExcuse + $earlyDeparture) > 0) {
            $this->comment('Jalankan ulang tanpa --dry-run untuk menyimpan perubahan.');
        }

        return Command::SUCCESS;
    }
}
