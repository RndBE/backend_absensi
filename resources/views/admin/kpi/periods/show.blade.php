@extends('admin.layouts.app')
@section('title', $kpiPeriod->name)

@php
    $flow = \App\Models\KpiPeriod::STATUS_FLOW;
    $labels = \App\Models\KpiPeriod::STATUS_LABELS;
    $currentIndex = array_search($kpiPeriod->status, $flow, true);
    $next = $flow[$currentIndex + 1] ?? null;
    $percent = $progress['total'] > 0 ? round($progress['submitted'] / $progress['total'] * 100) : 0;
@endphp

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.kpi-periods.index') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke daftar periode
    </a>
</div>

@if(session('kpi_skipped'))
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
    <p class="text-[13px] font-bold text-amber-800">Karyawan yang dilewati saat membuat daftar penilaian</p>
    <ul class="mt-2 ml-6 list-disc text-[12px] text-amber-700 space-y-0.5">
        @foreach(session('kpi_skipped') as $row)
        <li>{{ $row }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900">
                {{ $kpiPeriod->name }}
                @if($kpiPeriod->is_trial)
                <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded align-middle">UJI COBA</span>
                @endif
            </h3>
            <p class="text-[12px] text-gray-500 mt-0.5">
                Kinerja {{ $kpiPeriod->start_date?->format('d/m/Y') }} – {{ $kpiPeriod->end_date?->format('d/m/Y') }}
                · {{ $kpiPeriod->indicatorSnapshots()->count() }} indikator dibekukan
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if(! $kpiPeriod->isDraft() && ! $kpiPeriod->isFinal())
            <form action="{{ route('admin.kpi-periods.generate', $kpiPeriod->id) }}" method="POST"
                data-confirm="Siapkan daftar penilaian untuk seluruh karyawan? Penilaian yang sudah dikirim tidak akan disentuh.">
                @csrf
                <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[15px] align-text-bottom">group_add</span> Siapkan Daftar Penilaian
                </button>
            </form>
            @endif
            @if($next)
            <form action="{{ route('admin.kpi-periods.advance', $kpiPeriod->id) }}" method="POST"
                data-confirm="Ubah status periode menjadi &quot;{{ $labels[$next] }}&quot;? Status tidak bisa mundur.">
                @csrf
                <input type="hidden" name="status" value="{{ $next }}">
                <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                    Lanjut ke {{ $labels[$next] }}
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Alur status Bab 9.3 --}}
    <div class="px-5 py-4 flex items-center gap-1.5 flex-wrap">
        @foreach($flow as $i => $status)
        <div class="flex items-center gap-1.5">
            <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                {{ $i < $currentIndex ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : ($i === $currentIndex ? 'text-white bg-indigo-600' : 'text-gray-400 bg-gray-50 border border-gray-200') }}">
                {{ $labels[$status] }}
            </span>
            @if(! $loop->last)
            <span class="material-symbols-outlined text-[14px] text-gray-300">chevron_right</span>
            @endif
        </div>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Progres Pengisian</p>
        <p class="text-[28px] font-bold text-gray-900 mt-1">{{ $percent }}%</p>
        <p class="text-[12px] text-gray-500">{{ $progress['submitted'] }} dari {{ $progress['total'] }} penilaian terkirim</p>
        <div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-indigo-600 to-indigo-400" style="width: {{ $percent }}%"></div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm lg:col-span-2">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[14px] font-bold text-gray-900">Progres per Penilai</h3>
        </div>
        @if($byAssessor->isEmpty())
        <div class="py-10 text-center text-gray-400 text-[13px]">
            Belum ada daftar penilaian. Tekan &ldquo;Siapkan Daftar Penilaian&rdquo;.
        </div>
        @else
        <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
            @foreach($byAssessor as $row)
            @php $p = $row['total'] > 0 ? round($row['submitted'] / $row['total'] * 100) : 0; @endphp
            <div class="px-5 py-2.5 flex items-center gap-3">
                <span class="flex-1 text-[13px] font-semibold text-gray-800">{{ $row['assessor']?->full_name ?? '—' }}</span>
                <span class="text-[12px] text-gray-500 w-20 text-right">{{ $row['submitted'] }}/{{ $row['total'] }}</span>
                <div class="w-28 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $p === 100 ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width: {{ $p }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@if($unassigned)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mt-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[14px] font-bold text-gray-900">Karyawan Belum Bisa Dinilai ({{ count($unassigned) }})</h3>
        <p class="text-[12px] text-gray-500 mt-0.5">Beresekan sebelum periode masuk tahap pengisian, kalau tidak orang-orang ini tidak punya nilai sama sekali.</p>
    </div>
    <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
        @foreach($unassigned as $row)
        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
            <a href="{{ route('admin.employees.edit', $row['employee']->id) }}" class="text-[13px] font-semibold text-indigo-600 hover:underline">
                {{ $row['employee']->full_name }}
            </a>
            <span class="text-[12px] text-amber-700">{{ $row['reason'] }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection
