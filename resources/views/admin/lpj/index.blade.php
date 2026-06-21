@extends('admin.layouts.app')
@section('title', 'Laporan Pertanggungjawaban')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">receipt_long</span> Laporan Pertanggungjawaban (LPJ)</h3>
    </div>

    {{-- Status Tabs --}}
    <div class="px-5 pt-3">
        <div class="flex gap-0 border-b-2 border-gray-200 mb-4">
            @foreach(['all' => 'Semua', 'pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
                <a href="{{ route('admin.lpj.index', ['status' => $key]) }}"
                   class="px-4 py-2 text-[13px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                          {{ $status === $key ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Filter & Search --}}
    <div class="px-5 pb-3">
        <form method="GET" class="flex gap-2 items-center flex-wrap">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / nomor LPJ..."
                   class="flex-1 min-w-[200px] px-3 py-2 text-[13px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none">
            <input type="month" name="month" value="{{ request('month') }}"
                   class="px-3 py-2 text-[13px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none">
            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Cari</button>
        </form>
    </div>

    <div class="p-5 pt-0">
        @if($lpjs->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">receipt_long</span></div>
            <p class="text-sm font-medium mb-1">Belum ada LPJ</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Nomor LPJ</th>
                        <th class="py-3 px-3">Kegiatan</th>
                        <th class="py-3 px-3 text-right">Anggaran</th>
                        <th class="py-3 px-3 text-right">Realisasi</th>
                        <th class="py-3 px-3 text-right">Sisa</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($lpjs as $lpj)
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
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-all">
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-400 to-violet-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($lpj->employee->full_name ?? '', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $lpj->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $lpj->employee->department->name ?? '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3 text-[13px] text-gray-700">{{ $lpj->nomor_lpj ?? '-' }}</td>
                        <td class="py-3 px-3 text-[13px] text-gray-700 max-w-[180px] truncate">{{ $lpj->budgetRequest?->title ?? '-' }}</td>
                        <td class="py-3 px-3 text-[13px] text-right font-medium text-gray-700">Rp {{ number_format($lpj->total_anggaran, 0, ',', '.') }}</td>
                        <td class="py-3 px-3 text-[13px] text-right font-medium text-gray-700">Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}</td>
                        <td class="py-3 px-3 text-[13px] text-right font-bold {{ $sisa < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            Rp {{ number_format(abs($sisa), 0, ',', '.') }}{{ $sisa < 0 ? ' (lebih)' : '' }}
                        </td>
                        <td class="py-3 px-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusBg }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="py-3 px-3 text-center">
                            <a href="{{ route('admin.lpj.show', $lpj->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100">
                                <span class="material-symbols-outlined text-[13px]">visibility</span> Detail
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $lpjs->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
