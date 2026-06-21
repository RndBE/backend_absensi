@extends('employee.layouts.app')
@section('title', 'LPJ')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">LPJ</h1>
            <p class="text-[13px] text-gray-500 mt-1">Laporan pertanggungjawaban anggaran.</p>
        </div>
        <a href="{{ route('employee.lpj.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[17px]">add</span>
            Buat LPJ
        </a>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Riwayat LPJ</div>
        @forelse($lpjs as $lpj)
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
        <div class="px-5 py-4 border-b border-gray-100 last:border-0">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusBg }}">{{ $statusLabel }}</span>
                        @if($lpj->nomor_lpj)
                        <span class="text-[11px] text-gray-400">{{ $lpj->nomor_lpj }}</span>
                        @endif
                    </div>
                    <div class="text-[14px] font-bold text-gray-900 truncate">{{ $lpj->budgetRequest?->title ?? '-' }}</div>
                    <div class="text-[12px] text-gray-500 mt-0.5">{{ $lpj->travelReport?->destination_city ?? '' }}</div>
                    <div class="flex gap-4 mt-2">
                        <div>
                            <div class="text-[10px] text-gray-400 uppercase font-semibold">Anggaran</div>
                            <div class="text-[12px] font-bold text-gray-700">Rp {{ number_format($lpj->total_anggaran, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-400 uppercase font-semibold">Realisasi</div>
                            <div class="text-[12px] font-bold text-gray-700">Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] {{ $sisa < 0 ? 'text-red-400' : 'text-emerald-400' }} uppercase font-semibold">{{ $sisa < 0 ? 'Lebih' : 'Sisa' }}</div>
                            <div class="text-[12px] font-bold {{ $sisa < 0 ? 'text-red-600' : 'text-emerald-600' }}">Rp {{ number_format(abs($sisa), 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('employee.lpj.show', $lpj->id) }}" class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100">
                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                    Detail
                </a>
            </div>
        </div>
        @empty
        <div class="text-center py-10 text-gray-400">
            <span class="material-symbols-outlined text-[36px] block mb-2">receipt_long</span>
            <p class="text-sm font-medium">Belum ada LPJ</p>
            <a href="{{ route('employee.lpj.create') }}" class="inline-flex items-center gap-1 mt-3 text-[12px] font-semibold text-indigo-600 hover:underline">
                <span class="material-symbols-outlined text-[16px]">add_circle</span> Buat LPJ Pertama
            </a>
        </div>
        @endforelse
    </section>
</div>
@endsection
