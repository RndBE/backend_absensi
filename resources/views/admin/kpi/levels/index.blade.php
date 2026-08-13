@extends('admin.layouts.app')
@section('title', 'Level & Bobot KPI')

@section('content')
@if($problems)
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
    <p class="text-[13px] font-bold text-amber-800 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">warning</span>
        Bobot belum siap — periode baru tidak bisa dibuka sebelum ini beres
    </p>
    <ul class="mt-2 ml-6 list-disc text-[12px] text-amber-700 space-y-0.5">
        @foreach($problems as $problem)
        <li>{{ $problem }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Form diletakkan di luar tabel dan dirujuk lewat atribut form="..." pada tiap input.
     Membungkus <td> dengan <form> membuat browser mengeluarkan form dari tabel. --}}
@foreach($levels as $level)
<form action="{{ route('admin.kpi-levels.update', $level->id) }}" method="POST" id="lvl-{{ $level->id }}">
    @csrf @method('PUT')
</form>
@endforeach

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">stairs</span> Level & Bobot Kategori
        </h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Bobot tiga kategori harus berjumlah 100% untuk tiap level yang dinilai. Perubahan di sini
            <strong>tidak mempengaruhi periode yang sudah dibuka</strong> — periode memakai salinannya sendiri.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Level</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Dinilai</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Excellence</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Contribution</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Leadership</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Indikator</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($levels as $level)
                @php
                    $total = $level->totalCategoryWeight();
                    $invalid = $level->is_assessed && abs($total - 100) >= 0.01;
                @endphp
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all">
                    <td class="py-3 px-5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[12px] font-bold shrink-0">
                                {{ $level->code }}
                            </div>
                            <input type="text" name="name" form="lvl-{{ $level->id }}" value="{{ $level->name }}"
                                class="w-44 px-2.5 py-1.5 border border-gray-300 rounded-lg text-[13px] font-semibold outline-none focus:border-indigo-500">
                        </div>
                    </td>
                    <td class="py-3 px-5 text-center">
                        <input type="checkbox" name="is_assessed" form="lvl-{{ $level->id }}" value="1" @checked($level->is_assessed)
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                    </td>
                    @foreach(['weight_excellence', 'weight_contribution', 'weight_leadership'] as $field)
                    <td class="py-3 px-5 text-center">
                        <div class="inline-flex items-center gap-1">
                            <input type="number" step="0.01" min="0" max="100" name="{{ $field }}" form="lvl-{{ $level->id }}"
                                value="{{ rtrim(rtrim(number_format((float) $level->$field, 2, '.', ''), '0'), '.') }}"
                                class="w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-[13px] text-right outline-none focus:border-indigo-500">
                            <span class="text-[12px] text-gray-400">%</span>
                        </div>
                    </td>
                    @endforeach
                    <td class="py-3 px-5 text-center text-[12px] {{ $invalid ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                        {{ rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',') }}%
                        @if($invalid)<br><span class="text-[10px]">harus 100%</span>@endif
                    </td>
                    <td class="py-3 px-5 text-center text-[13px]">
                        <a href="{{ route('admin.kpi-indicators.index', ['level' => $level->code]) }}" class="text-indigo-600 font-semibold hover:underline">
                            {{ $level->indicators_count }} butir
                        </a>
                        <div class="text-[11px] text-gray-400">{{ $level->employees_count }} karyawan</div>
                    </td>
                    <td class="py-3 px-5 text-center">
                        <button type="submit" form="lvl-{{ $level->id }}"
                            class="px-3 py-1.5 text-[11px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                            Simpan
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
