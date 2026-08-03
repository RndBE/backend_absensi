<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Persetujuan {{ $typeLabel }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111827; font: 13px Arial, sans-serif; }
        .page { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 20mm 18mm; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .no-print { position: fixed; top: 14px; z-index: 20; border: 0; border-radius: 8px; padding: 10px 15px; color: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
        .back { left: 14px; background: #4b5563; }
        .print { right: 14px; background: #0f766e; }
        .header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 16px; border-bottom: 2px solid #111827; }
        .eyebrow { margin-bottom: 6px; color: #4f46e5; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 22px; }
        .doc-no { color: #6b7280; font-size: 11px; text-align: right; }
        .status { display: inline-block; margin-top: 8px; padding: 5px 9px; border-radius: 999px; background: #d1fae5; color: #047857; font-size: 11px; font-weight: 700; }
        .section { margin-top: 22px; }
        .section h2 { margin: 0 0 10px; color: #374151; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .field { min-height: 62px; padding: 11px 13px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }
        .field:nth-child(even) { border-right: 0; }
        .field.full { grid-column: 1 / -1; border-right: 0; }
        .label { margin-bottom: 5px; color: #9ca3af; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .value { font-size: 13px; font-weight: 600; line-height: 1.45; white-space: pre-line; }
        .decision { border: 1px solid #a7f3d0; background: #ecfdf5; }
        .decision .field { border-color: #a7f3d0; }
        .footer { margin-top: 28px; color: #6b7280; font-size: 10px; line-height: 1.5; text-align: center; }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page { width: auto; min-height: auto; margin: 0; box-shadow: none; }
        }
        @media screen and (max-width: 760px) {
            .page { width: calc(100% - 20px); min-height: auto; margin: 70px 10px 10px; padding: 24px 18px; }
            .grid { grid-template-columns: 1fr; }
            .field, .field:nth-child(even) { border-right: 0; }
            .field.full { grid-column: auto; }
        }
    </style>
</head>
<body>
    @php
        $formatMinutes = static function ($minutes): string {
            if (is_null($minutes)) return '-';
            $minutes = (int) $minutes;
            return intdiv($minutes, 60).' jam '.($minutes % 60).' menit';
        };
        $statusLabel = match($item->status) {
            'approved' => 'Disetujui',
            'in_review' => 'Dalam proses',
            'rejected' => 'Ditolak',
            default => ucfirst((string) $item->status),
        };
    @endphp

    <a href="{{ $backUrl }}" class="no-print back">Kembali</a>
    <button type="button" onclick="window.print()" class="no-print print">Cetak</button>

    <main class="page">
        <header class="header">
            <div>
                <div class="eyebrow">Persetujuan Tim</div>
                <h1>Bukti Persetujuan {{ $typeLabel }}</h1>
                <span class="status">Disetujui pada step {{ $approvalLog->step_order }}</span>
            </div>
            <div class="doc-no">
                Nomor dokumen<br>
                <strong>{{ strtoupper($type) }}/{{ str_pad((string) $item->id, 6, '0', STR_PAD_LEFT) }}</strong><br><br>
                Status akhir pengajuan<br>
                <strong>{{ $statusLabel }}</strong>
            </div>
        </header>

        <section class="section">
            <h2>Data Pemohon</h2>
            <div class="grid">
                <div class="field"><div class="label">Nama</div><div class="value">{{ $item->employee?->full_name ?? '-' }}</div></div>
                <div class="field"><div class="label">NIK Karyawan</div><div class="value">{{ $item->employee?->employee_code ?? '-' }}</div></div>
                <div class="field"><div class="label">Jabatan</div><div class="value">{{ $item->employee?->position ?? '-' }}</div></div>
                <div class="field"><div class="label">Departemen</div><div class="value">{{ $item->employee?->department?->name ?? '-' }}</div></div>
                <div class="field full"><div class="label">Tanggal Pengajuan</div><div class="value">{{ $item->created_at?->format('d/m/Y H:i') ?? '-' }}</div></div>
            </div>
        </section>

        <section class="section">
            <h2>Rincian Pengajuan</h2>
            <div class="grid">
                @if($type === 'leave')
                    <div class="field"><div class="label">Jenis Cuti/Izin</div><div class="value">{{ $item->leaveType?->name ?? '-' }}</div></div>
                    <div class="field"><div class="label">Durasi</div><div class="value">{{ $item->total_days_label }} hari</div></div>
                    <div class="field"><div class="label">Tanggal Mulai</div><div class="value">{{ $item->start_date?->format('d/m/Y') ?? '-' }}</div></div>
                    <div class="field"><div class="label">Tanggal Selesai</div><div class="value">{{ $item->end_date?->format('d/m/Y') ?? '-' }}</div></div>
                    <div class="field"><div class="label">Delegasi</div><div class="value">{{ $item->delegate?->full_name ?? '-' }}</div></div>
                    <div class="field full"><div class="label">Alasan</div><div class="value">{{ $item->reason ?: '-' }}</div></div>
                @elseif($type === 'overtime')
                    <div class="field"><div class="label">Tanggal Lembur</div><div class="value">{{ $item->date?->format('d/m/Y') ?? '-' }}</div></div>
                    <div class="field"><div class="label">Tipe Hari</div><div class="value">{{ $item->overtime_type === 'holiday' ? 'Hari Libur' : 'Hari Kerja' }}</div></div>
                    <div class="field"><div class="label">Rencana Waktu</div><div class="value">{{ $item->planned_start ? substr($item->planned_start, 0, 5) : '-' }} - {{ $item->planned_end ? substr($item->planned_end, 0, 5) : '-' }}</div></div>
                    <div class="field"><div class="label">Durasi Diajukan</div><div class="value">{{ $formatMinutes($item->total_duration) }}</div></div>
                    <div class="field"><div class="label">Durasi Disetujui</div><div class="value">{{ $formatMinutes($item->approved_duration ?? $item->total_duration) }}</div></div>
                    <div class="field"><div class="label">Istirahat Disetujui</div><div class="value">{{ $formatMinutes($item->approved_break ?? $item->break_duration) }}</div></div>
                    <div class="field full"><div class="label">Alasan</div><div class="value">{{ $item->reason ?: '-' }}</div></div>
                @else
                    <div class="field"><div class="label">Tanggal Presensi</div><div class="value">{{ $item->date?->format('d/m/Y') ?? '-' }}</div></div>
                    <div class="field"><div class="label">Jam Masuk</div><div class="value">{{ $item->clock_in ? substr($item->clock_in, 0, 5) : '-' }}</div></div>
                    <div class="field"><div class="label">Jam Pulang</div><div class="value">{{ $item->clock_out ? substr($item->clock_out, 0, 5) : '-' }}</div></div>
                    <div class="field full"><div class="label">Alasan Koreksi</div><div class="value">{{ $item->reason ?: '-' }}</div></div>
                @endif
            </div>
        </section>

        <section class="section">
            <h2>Keputusan Persetujuan</h2>
            <div class="grid decision">
                <div class="field"><div class="label">Disetujui Oleh</div><div class="value">{{ $approvalLog->approver?->full_name ?? '-' }}</div></div>
                <div class="field"><div class="label">Jabatan Approver</div><div class="value">{{ $approvalLog->approver?->position ?? '-' }}</div></div>
                <div class="field"><div class="label">Tanggal Keputusan</div><div class="value">{{ $approvalLog->created_at?->format('d/m/Y H:i') ?? '-' }}</div></div>
                <div class="field"><div class="label">Tahap</div><div class="value">Step {{ $approvalLog->step_order }}</div></div>
                <div class="field full"><div class="label">Catatan Approver</div><div class="value">{{ $approvalLog->notes ?: '-' }}</div></div>
            </div>
        </section>

        <footer class="footer">
            Dokumen ini dihasilkan secara elektronik dari sistem absensi dan mencatat keputusan persetujuan yang tersimpan di sistem.
        </footer>
    </main>
</body>
</html>
