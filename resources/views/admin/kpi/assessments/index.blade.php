@extends('admin.layouts.app')
@section('title', 'Isi Penilaian KPI')

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">rate_review</span>
    <p class="text-sm font-medium">Belum ada periode penilaian yang dibuka</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-assessments.index', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">rate_review</span>
            Penilaian yang Harus Anda Isi
        </h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Skor 4–5 dan 1–2 wajib disertai contoh kejadian konkret. Skor 3 berarti sesuai standar
            dan tidak perlu dijelaskan.
        </p>
    </div>

    @if($assessments->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">check_circle</span>
        <p class="text-sm font-medium">Tidak ada penilaian untuk Anda pada periode ini</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Karyawan</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Departemen</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Level</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Peran Anda</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Terisi</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Status</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assessments as $assessment)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all">
                    <td class="py-3.5 px-5">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $assessment->employee->full_name }}</div>
                        <div class="text-[11px] text-gray-400">{{ $assessment->employee->position ?: $assessment->employee->employee_code }}</div>
                    </td>
                    <td class="py-3.5 px-5 text-[12px] text-gray-600">{{ $assessment->employee->department?->name ?? '—' }}</td>
                    <td class="py-3.5 px-5 text-center">
                        <span class="px-2 py-0.5 text-[11px] font-bold text-gray-600 bg-gray-100 rounded">
                            {{ $assessment->employee->kpiLevel?->code ?? '—' }}
                        </span>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">
                        {{ $assessment->roleLabel() }}
                        <div class="text-[11px] text-gray-400">bobot {{ rtrim(rtrim(number_format((float) $assessment->weight, 2, ',', '.'), '0'), ',') }}%</div>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[13px] text-gray-600">{{ $assessment->filled_count }}</td>
                    <td class="py-3.5 px-5 text-center">
                        @if($assessment->isSubmitted())
                        <span class="px-2.5 py-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">Terkirim</span>
                        @else
                        <span class="px-2.5 py-1 text-[11px] font-bold text-gray-600 bg-gray-100 border border-gray-200 rounded-lg">Draft</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        <a href="{{ route('admin.kpi-assessments.edit', $assessment->id) }}"
                            class="px-3 py-1.5 text-[11px] font-semibold {{ $assessment->isSubmitted() ? 'text-gray-600 bg-gray-50 border-gray-200' : 'text-indigo-600 bg-indigo-50 border-indigo-200' }} border rounded-lg hover:opacity-80 transition-all">
                            {{ $assessment->isSubmitted() ? 'Lihat' : 'Isi' }}
                        </a>
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
