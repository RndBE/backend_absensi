<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LPJ - {{ $lpj->employee->full_name ?? '' }} - {{ $lpj->budgetRequest?->title ?? '' }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('images/title.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        .material-symbols-outlined { font-size: 20px; }
        .no-print { position: fixed; top: 14px; z-index: 50; border: 0; border-radius: 8px; padding: 10px 16px; font: 700 12px Arial, sans-serif; text-decoration: none; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
        .back-btn { left: 14px; background: #374151; color: #fff; }
        .print-btn { right: 14px; background: #0f766e; color: #fff; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .print-shell { max-width: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <a href="{{ $backUrl ?? route('employee.approvals.index') }}" class="back-btn no-print">← Kembali</a>
    <button type="button" onclick="window.print()" class="print-btn no-print">🖨️ Cetak</button>

    <div class="print-shell max-w-5xl mx-auto px-4 py-16">
        <div class="text-center mb-6">
            <h1 class="text-[22px] font-black text-gray-900">Laporan Pertanggungjawaban (LPJ)</h1>
            <p class="text-[13px] text-gray-500 mt-1">{{ $lpj->nomor_lpj ?: '—' }}</p>
        </div>

        @php
            $statusBg = match($lpj->status) {
                'approved'  => 'bg-emerald-100 text-emerald-700',
                'in_review' => 'bg-blue-100 text-blue-700',
                'rejected'  => 'bg-red-100 text-red-700',
                'draft'     => 'bg-gray-100 text-gray-600',
                default     => 'bg-amber-100 text-amber-700',
            };
            $statusLabel = match($lpj->status) {
                'approved'  => 'Disetujui',
                'in_review' => 'Diproses',
                'rejected'  => 'Ditolak',
                'draft'     => 'Draft',
                default     => 'Pending',
            };
            $sisa = (float) $lpj->sisa;
        @endphp

        {{-- Header Info --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-3">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Status</div>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[12px] font-bold {{ $statusBg }}">{{ $statusLabel }}</span>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Nomor LPJ</div>
                        <div class="text-[14px] font-semibold text-gray-800">{{ $lpj->nomor_lpj ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Karyawan</div>
                        <div class="text-[13px] font-semibold text-gray-800 mt-1">{{ $lpj->employee->full_name ?? '-' }}</div>
                        <div class="text-[11px] text-gray-400">{{ $lpj->employee->position ?? '-' }} · {{ $lpj->employee->department?->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Kegiatan / Anggaran</div>
                        <div class="text-[13px] font-semibold text-gray-800">{{ $lpj->budgetRequest?->title ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Nomor Surat Tugas</div>
                        <div class="text-[13px] text-gray-700">{{ $lpj->budgetRequest?->surat_tugas_no ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Tujuan</div>
                        <div class="text-[13px] text-gray-700">{{ $lpj->travelReport?->destination_city ?? '-' }}</div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="bg-indigo-50 rounded-lg p-4 text-center">
                        <div class="text-[11px] font-semibold text-indigo-400 uppercase tracking-wider mb-1">Total Anggaran</div>
                        <div class="text-[20px] font-black text-indigo-700">Rp {{ number_format($lpj->total_anggaran, 0, ',', '.') }}</div>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-4 text-center">
                        <div class="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider mb-1">Total Realisasi</div>
                        <div class="text-[20px] font-black text-emerald-700">Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}</div>
                    </div>
                    <div class="{{ $sisa < 0 ? 'bg-red-50' : 'bg-gray-50' }} rounded-lg p-4 text-center">
                        <div class="text-[11px] font-semibold {{ $sisa < 0 ? 'text-red-400' : 'text-gray-400' }} uppercase tracking-wider mb-1">
                            {{ $sisa < 0 ? 'Kelebihan' : 'Sisa' }}
                        </div>
                        <div class="text-[20px] font-black {{ $sisa < 0 ? 'text-red-700' : 'text-gray-700' }}">Rp {{ number_format(abs($sisa), 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
            @if($lpj->catatan)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Catatan</div>
                <div class="text-[13px] text-gray-700">{{ $lpj->catatan }}</div>
            </div>
            @endif
            @if($lpj->rejection_reason && $lpj->status === 'rejected')
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="text-[11px] font-semibold text-red-400 uppercase tracking-wider mb-1">Alasan Penolakan</div>
                <div class="text-[13px] text-red-700">{{ $lpj->rejection_reason }}</div>
            </div>
            @endif
        </div>

        {{-- Rincian PEMASUKAN vs PENGELUARAN + ringkasan per kategori --}}
        @include('partials.lpj-rincian', ['lpj' => $lpj])

        {{-- Approval Log --}}
        @if($lpj->approvalLogs->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h4 class="text-[14px] font-bold text-gray-900 mb-4">Riwayat Persetujuan</h4>
            <div class="space-y-3">
                @foreach($lpj->approvalLogs as $log)
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br {{ $log->action === 'approved' ? 'from-emerald-400 to-emerald-500' : 'from-red-400 to-red-500' }} flex items-center justify-center text-white text-[11px] font-bold shrink-0">
                        {{ substr($log->approver->full_name ?? '', 0, 1) }}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[13px] font-semibold text-gray-800">{{ $log->approver->full_name ?? '-' }}</span>
                            @if($log->via_label)<span class="text-[11px] text-gray-500">(via {{ $log->via_label }})</span>@endif
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $log->action === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $log->action === 'approved' ? 'Disetujui' : 'Ditolak' }}
                            </span>
                            <span class="text-[11px] text-gray-400">Step {{ $log->step_order }}</span>
                        </div>
                        @if($log->notes)<div class="text-[12px] text-gray-500 mt-0.5">{{ $log->notes }}</div>@endif
                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $log->created_at->format('d M Y, H:i') }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</body>
</html>
