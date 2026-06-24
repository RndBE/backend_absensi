@extends('employee.layouts.app')
@section('title', 'Detail LPJ')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.lpj.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Kembali
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Detail LPJ</h1>
    </div>

    @if(session('success'))
    <div class="px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-[13px]">{{ session('success') }}</div>
    @endif

    {{-- Status Card --}}
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

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start justify-between mb-4">
            <div>
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold {{ $statusBg }}">{{ $statusLabel }}</span>
                @if($lpj->nomor_lpj)
                <div class="text-[12px] text-gray-400 mt-1">{{ $lpj->nomor_lpj }}</div>
                @endif
            </div>
            <div class="text-right">
                <div class="text-[10px] text-gray-400 uppercase font-semibold">Diajukan</div>
                <div class="text-[12px] text-gray-600">{{ $lpj->created_at->format('d M Y') }}</div>
            </div>
        </div>

        <div class="space-y-2.5 text-[13px]">
            <div class="flex gap-2">
                <span class="text-gray-400 w-28 shrink-0">Kegiatan</span>
                <span class="font-semibold text-gray-800">{{ $lpj->budgetRequest?->title ?? '-' }}</span>
            </div>
            <div class="flex gap-2">
                <span class="text-gray-400 w-28 shrink-0">Surat Tugas</span>
                <span class="text-gray-700">{{ $lpj->budgetRequest?->surat_tugas_no ?? '-' }}</span>
            </div>
            <div class="flex gap-2">
                <span class="text-gray-400 w-28 shrink-0">Tujuan</span>
                <span class="text-gray-700">{{ $lpj->travelReport?->destination_city ?? '-' }}</span>
            </div>
            @if($lpj->catatan)
            <div class="flex gap-2">
                <span class="text-gray-400 w-28 shrink-0">Catatan</span>
                <span class="text-gray-700">{{ $lpj->catatan }}</span>
            </div>
            @endif
            @if($lpj->rejection_reason && $lpj->status === 'rejected')
            <div class="flex gap-2">
                <span class="text-red-400 w-28 shrink-0">Ditolak Karena</span>
                <span class="text-red-700 font-medium">{{ $lpj->rejection_reason }}</span>
            </div>
            @endif
        </div>

        {{-- Summary keuangan --}}
        <div class="grid grid-cols-3 gap-3 mt-4 pt-4 border-t border-gray-100">
            <div class="bg-indigo-50 rounded-lg p-3 text-center">
                <div class="text-[9px] text-indigo-400 uppercase font-bold mb-0.5">Anggaran</div>
                <div class="text-[13px] font-black text-indigo-700">Rp {{ number_format($lpj->total_anggaran, 0, ',', '.') }}</div>
            </div>
            <div class="bg-emerald-50 rounded-lg p-3 text-center">
                <div class="text-[9px] text-emerald-400 uppercase font-bold mb-0.5">Realisasi</div>
                <div class="text-[13px] font-black text-emerald-700">Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}</div>
            </div>
            <div class="{{ $sisa < 0 ? 'bg-red-50' : 'bg-gray-50' }} rounded-lg p-3 text-center">
                <div class="text-[9px] {{ $sisa < 0 ? 'text-red-400' : 'text-gray-400' }} uppercase font-bold mb-0.5">{{ $sisa < 0 ? 'Lebih' : 'Sisa' }}</div>
                <div class="text-[13px] font-black {{ $sisa < 0 ? 'text-red-700' : 'text-gray-700' }}">Rp {{ number_format(abs($sisa), 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    {{-- Rincian PEMASUKAN vs PENGELUARAN + ringkasan per kategori --}}
    @include('partials.lpj-rincian', ['lpj' => $lpj])

    {{-- Approval Log --}}
    @if($lpj->approvalLogs->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-[14px] font-bold text-gray-900 mb-4">Riwayat Persetujuan</h3>
        <div class="space-y-3">
            @foreach($lpj->approvalLogs as $log)
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br {{ $log->action === 'approved' ? 'from-emerald-400 to-emerald-500' : 'from-red-400 to-red-500' }} flex items-center justify-center text-white text-[11px] font-bold shrink-0">
                    {{ substr($log->approver->full_name ?? '', 0, 1) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[13px] font-semibold text-gray-800">{{ $log->approver->full_name ?? '-' }}</span>
                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $log->action === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            {{ $log->action === 'approved' ? 'Disetujui' : 'Ditolak' }}
                        </span>
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
@endsection
