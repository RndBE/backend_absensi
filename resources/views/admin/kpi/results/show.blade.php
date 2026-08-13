@extends('admin.layouts.app')
@section('title', 'Hasil KPI ' . $kpiResult->employee->full_name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.kpi-results.index', ['period' => $kpiResult->kpi_period_id]) }}"
        class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke hasil KPI
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 px-5 py-5">
    <div class="flex items-start justify-between flex-wrap gap-4">
        <div>
            <h3 class="text-[18px] font-bold text-gray-900">{{ $kpiResult->employee->full_name }}</h3>
            <p class="text-[12px] text-gray-500 mt-0.5">
                {{ $kpiResult->employee->position ?: '—' }} ·
                {{ $kpiResult->employee->department?->name ?? '—' }} ·
                Level {{ $kpiResult->levelSnapshot?->code }}
            </p>
            <p class="text-[12px] text-gray-500 mt-0.5">
                Periode {{ $kpiResult->period->name }}
                @if($kpiResult->period->is_trial)
                <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded">UJI COBA</span>
                @endif
            </p>
        </div>
        <div class="text-right">
            <p class="text-[32px] font-bold text-gray-900 leading-none">
                {{ number_format((float) $kpiResult->effectiveScore(), 2, ',', '.') }}
            </p>
            <p class="text-[12px] text-gray-500 mt-1">
                Predikat <strong>{{ $kpiResult->grade ?? '—' }}</strong> — {{ $kpiResult->gradeLabel() }}
            </p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        @foreach($categories as $code => $name)
        @php
            $field = ['EX' => 'score_excellence', 'CO' => 'score_contribution', 'LD' => 'score_leadership'][$code];
            $weight = $kpiResult->levelSnapshot?->categoryWeights()[$code] ?? 0;
        @endphp
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="text-[11px] font-bold text-gray-500 uppercase">{{ $name }}</p>
            <p class="text-[20px] font-bold text-gray-900 mt-0.5">{{ number_format((float) $kpiResult->$field, 2, ',', '.') }}</p>
            <p class="text-[11px] text-gray-500">bobot {{ rtrim(rtrim(number_format((float) $weight, 2, ',', '.'), '0'), ',') }}%</p>
        </div>
        @endforeach
    </div>

    <div class="mt-4 text-[12px] text-gray-500">
        Dinilai oleh:
        @foreach($assessments as $assessment)
        <span class="inline-block px-2 py-0.5 bg-gray-100 rounded mr-1.5">
            {{ $assessment->assessor?->full_name ?? '—' }}
            ({{ strtolower($assessment->roleLabel()) }}, {{ rtrim(rtrim(number_format((float) $assessment->weight, 2, ',', '.'), '0'), ',') }}%)
        </span>
        @endforeach
    </div>
</div>

@foreach($categories as $code => $categoryName)
@php $catRows = $rows[$code] ?? collect(); @endphp
@if($catRows->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 rounded-t-xl">
        <h3 class="text-[14px] font-bold text-gray-900">{{ $categoryName }}</h3>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($catRows as $row)
        <div class="px-5 py-3.5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <p class="text-[13px] font-semibold text-gray-800">
                        <span class="font-mono text-indigo-600">{{ $row['snapshot']->code }}</span>
                        {{ $row['snapshot']->name }}
                        <span class="ml-1 text-[11px] text-gray-400">bobot {{ rtrim(rtrim(number_format((float) $row['snapshot']->weight, 2, ',', '.'), '0'), ',') }}%</span>
                    </p>
                </div>
                <span class="shrink-0 w-11 h-9 flex items-center justify-center rounded-lg text-[14px] font-bold text-white
                    {{ $row['score'] >= 4 ? 'bg-emerald-500' : ($row['score'] >= 3 ? 'bg-indigo-500' : 'bg-red-500') }}">
                    {{ number_format((float) $row['score'], 1, ',', '.') }}
                </span>
            </div>

            @foreach($row['evidence'] as $evidence)
            @if(trim((string) $evidence['text']) !== '')
            <div class="mt-2 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2">
                <p class="text-[11px] font-semibold text-gray-600">{{ $evidence['assessor'] }} — skor {{ $evidence['score'] }}</p>
                <p class="text-[12px] text-gray-700 mt-0.5">{{ $evidence['text'] }}</p>
            </div>
            @endif
            @endforeach
        </div>
        @endforeach
    </div>
</div>
@endif
@endforeach
@endsection
