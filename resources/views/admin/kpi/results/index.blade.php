@extends('admin.layouts.app')
@section('title', 'Hasil KPI')

@php $gradeLabels = \App\Models\KpiFinalResult::GRADE_LABELS; @endphp

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">leaderboard</span>
    <p class="text-sm font-medium">Belum ada periode penilaian</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-results.index', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

@if($period?->is_trial)
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3.5">
    <p class="text-[13px] font-semibold text-amber-800 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">science</span>
        Periode uji coba — hasil ini tidak boleh dipakai untuk bonus, promosi, atau sanksi.
    </p>
</div>
@endif

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    @foreach($gradeLabels as $grade => $label)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center text-[14px] font-bold text-white
                {{ in_array($grade, ['A', 'B']) ? 'bg-emerald-500' : ($grade === 'C' ? 'bg-indigo-500' : 'bg-red-500') }}">
                {{ $grade }}
            </span>
            <div>
                <p class="text-[18px] font-bold text-gray-900 leading-none">{{ $distribution[$grade] ?? 0 }}</p>
                <p class="text-[10px] text-gray-500 mt-0.5">{{ $label }}</p>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Hasil per Karyawan ({{ $results->count() }})</h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Nilai ditampilkan 2 desimal; predikat ditentukan dari perhitungan 4 desimal.
        </p>
    </div>

    @if($results->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">calculate</span>
        <p class="text-sm font-medium">Belum ada hasil</p>
        <p class="text-xs mt-1">Hasil dihitung saat periode masuk tahap Pemrosesan.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Karyawan</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Departemen</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Level</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">EX</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">CO</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">LD</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Nilai Akhir</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Predikat</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $result)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all">
                    <td class="py-3.5 px-5">
                        <a href="{{ route('admin.kpi-results.show', $result->id) }}" class="text-[13px] font-semibold text-indigo-600 hover:underline">
                            {{ $result->employee->full_name }}
                        </a>
                        <div class="text-[11px] text-gray-400">{{ $result->employee->employee_code }}</div>
                    </td>
                    <td class="py-3.5 px-5 text-[12px] text-gray-600">{{ $result->employee->department?->name ?? '—' }}</td>
                    <td class="py-3.5 px-5 text-center">
                        <span class="px-2 py-0.5 text-[11px] font-bold text-gray-600 bg-gray-100 rounded">{{ $result->levelSnapshot?->code ?? '—' }}</span>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">{{ number_format((float) $result->score_excellence, 2, ',', '.') }}</td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">{{ number_format((float) $result->score_contribution, 2, ',', '.') }}</td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">{{ number_format((float) $result->score_leadership, 2, ',', '.') }}</td>
                    <td class="py-3.5 px-5 text-center text-[14px] font-bold text-gray-900">
                        {{ number_format((float) $result->effectiveScore(), 2, ',', '.') }}
                        @if($result->calibrated_score !== null)
                        <div class="text-[10px] font-normal text-gray-400">setelah kalibrasi</div>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                            {{ in_array($result->grade, ['A', 'B']) ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : ($result->grade === 'C' ? 'text-indigo-700 bg-indigo-50 border border-indigo-200' : 'text-red-700 bg-red-50 border border-red-200') }}">
                            {{ $result->grade ?? '—' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif
@endsection
