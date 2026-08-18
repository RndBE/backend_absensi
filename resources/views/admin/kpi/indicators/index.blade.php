@extends('admin.layouts.app')
@section('title', 'Indikator KPI')

@php
    $categoryNames = \App\Models\KpiLevel::CATEGORIES;
@endphp

@section('content')
<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($levels as $level)
    <a href="{{ route('admin.kpi-indicators.index', ['level' => $level->code]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $selectedLevel?->id === $level->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $level->code }} · {{ $level->name }}
    </a>
    @endforeach
</div>

{{-- ── Bawaan level vs indikator per orang ── --}}
<div class="mb-4 bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
            <h3 class="text-[13px] font-bold text-gray-900">
                @if($selectedEmployee)
                Indikator milik {{ $selectedEmployee->full_name }}
                @else
                Indikator bawaan level
                @endif
            </h3>
            <p class="text-[11px] text-gray-400 mt-0.5">
                @if($selectedEmployee)
                Menggantikan indikator <strong>General Excellence</strong> bawaan levelnya. Kategori
                lain tetap memakai bawaan. Bobotnya harus berjumlah 100 sendiri.
                @else
                Dipakai semua orang di level ini yang tidak punya indikator sendiri.
                @endif
            </p>
        </div>
        <form method="GET" action="{{ route('admin.kpi-indicators.index') }}" class="flex items-end gap-2 flex-wrap">
            <input type="hidden" name="level" value="{{ $selectedLevel?->code }}">
            <div>
                <label for="employeePicker" class="block text-[11px] font-semibold text-gray-600 mb-1">Lihat indikator milik</label>
                <select name="employee" id="employeePicker" onchange="this.form.submit()"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 min-w-[240px]">
                    <option value="">— bawaan level —</option>
                    @foreach($candidates as $candidate)
                    <option value="{{ $candidate->id }}" @selected($selectedEmployee?->id === $candidate->id)>
                        {{ $candidate->full_name }}{{ $candidate->position ? ' — '.$candidate->position : '' }}
                    </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($withOwnIndicators->isNotEmpty())
    <div class="mt-3 pt-3 border-t border-gray-100">
        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Sudah punya indikator sendiri</span>
        <div class="mt-1.5 flex flex-wrap gap-1.5">
            @foreach($withOwnIndicators as $person)
            <a href="{{ route('admin.kpi-indicators.index', ['employee' => $person->id]) }}"
                class="inline-flex items-baseline gap-1 px-2 py-0.5 border rounded text-[11px] transition-colors {{ $selectedEmployee?->id === $person->id ? 'bg-indigo-50 border-indigo-200' : 'bg-white border-gray-200 hover:bg-gray-50' }}">
                <span class="font-semibold text-gray-800">{{ $person->full_name }}</span>
                <span class="text-gray-400 tabular-nums">{{ $person->kpi_indicators_count }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>

@if(! $selectedLevel)
<div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400">
    <p class="text-sm">Belum ada level KPI. Buat lewat menu Level &amp; Bobot.</p>
</div>
@else

@foreach($categoryNames as $code => $categoryName)
@php
    $rows = $indicators[$code] ?? collect();
    $total = $weightTotals[$code] ?? 0;
    $invalid = $rows->isNotEmpty() && abs($total - 100) >= 0.01;
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900">{{ $categoryName }} <span class="text-gray-400 font-semibold">({{ $code }})</span></h3>
            <p class="text-[12px] {{ $invalid ? 'text-red-600 font-semibold' : 'text-gray-500' }} mt-0.5">
                Jumlah bobot aktif: {{ rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',') }}%
                @if($invalid) — harus 100% @endif
            </p>
        </div>
        <button type="button" onclick="openIndicatorModal('{{ $code }}')"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-[15px]">add</span> Tambah
        </button>
    </div>

    @if($rows->isEmpty())
    <div class="py-10 text-center text-gray-400 text-[13px]">Belum ada indikator pada kategori ini.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-2.5 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Kode</th>
                    <th class="py-2.5 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Indikator</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Bobot</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Inti</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Sumber</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $indicator)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40 transition-all {{ $indicator->is_active ? '' : 'opacity-50' }}">
                    <td class="py-3 px-5 text-[12px] font-mono font-semibold text-gray-700">{{ $indicator->code }}</td>
                    <td class="py-3 px-5">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $indicator->name }}</div>
                        @if($indicator->description)
                        <div class="text-[11px] text-gray-500 mt-0.5">{{ $indicator->description }}</div>
                        @endif
                    </td>
                    <td class="py-3 px-5 text-center text-[13px] font-semibold text-gray-700">
                        {{ rtrim(rtrim(number_format((float) $indicator->weight, 2, ',', '.'), '0'), ',') }}%
                    </td>
                    <td class="py-3 px-5 text-center">
                        @if($indicator->is_core)
                        <span class="px-2 py-0.5 text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded">INTI</span>
                        @else
                        <span class="text-[11px] text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="py-3 px-5 text-center text-[11px]">
                        @if($indicator->is_auto_filled)
                        <span class="px-2 py-0.5 font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded">
                            {{ $indicator->auto_source === \App\Models\KpiIndicator::SOURCE_ATTENDANCE ? 'Absensi' : 'Penilaian silang' }}
                        </span>
                        @else
                        <span class="text-gray-400">Penilai</span>
                        @endif
                    </td>
                    <td class="py-3 px-5">
                        <div class="flex items-center justify-center gap-1.5">
                            <a href="{{ route('admin.kpi-indicators.rubrics', $indicator->id) }}"
                                class="px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-all">
                                Rubrik ({{ $indicator->rubrics_count }})
                            </a>
                            <button type="button"
                                onclick='openIndicatorModal(@json($indicator->category), @json($indicator))'
                                class="px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                                Edit
                            </button>
                            <form action="{{ route('admin.kpi-indicators.destroy', $indicator->id) }}" method="POST"
                                data-confirm="Hapus indikator {{ $indicator->code }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-2 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer">
                                    <span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endforeach

{{-- MODAL TAMBAH / EDIT INDIKATOR --}}
<div id="indModal" class="fixed inset-0 z-[100] items-center justify-center p-4 hidden" style="display:none">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeIndicatorModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="indModalTitle" class="text-[15px] font-bold text-gray-900">Tambah Indikator</h3>
            <button onclick="closeIndicatorModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form id="indForm" method="POST" action="{{ route('admin.kpi-indicators.store') }}">
            @csrf
            <input type="hidden" name="_method" id="indMethod" value="POST">
            <input type="hidden" name="kpi_level_id" value="{{ $selectedLevel->id }}">
            {{-- Kosong = indikator bawaan level; terisi = milik orang yang sedang dipilih. --}}
            <input type="hidden" name="employee_id" value="{{ $selectedEmployee?->id }}">
            <input type="hidden" name="category" id="indCategory">

            <div class="px-6 py-5 space-y-3.5 max-h-[65vh] overflow-y-auto">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Kode</label>
                        <input type="text" name="code" id="indCode" required maxlength="20"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] font-mono outline-none focus:border-indigo-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Bobot dalam kategori (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="weight" id="indWeight" required
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Nama indikator</label>
                    <input type="text" name="name" id="indName" required maxlength="255"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Yang dilihat</label>
                    <textarea name="description" id="indDescription" rows="2" maxlength="500"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500"></textarea>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Sumber skor</label>
                    <select name="auto_source" id="indAutoSource"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500">
                        <option value="">Diisi penilai</option>
                        <option value="cross_assessment">Otomatis — penilaian silang antar divisi</option>
                        <option value="attendance">Otomatis — data absensi</option>
                    </select>
                </div>
                <div class="flex items-center gap-5 pt-1">
                    <label class="inline-flex items-center gap-2 text-[13px] text-gray-700">
                        <input type="checkbox" name="is_core" id="indIsCore" value="1" class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                        Indikator inti (wajib diisi dengan pertimbangan)
                    </label>
                    <label class="inline-flex items-center gap-2 text-[13px] text-gray-700" id="indActiveWrap">
                        <input type="checkbox" name="is_active" id="indIsActive" value="1" checked class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                        Aktif
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl">
                <button type="button" onclick="closeIndicatorModal()" class="px-4 py-2.5 text-[13px] font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl cursor-pointer">Batal</button>
                <button type="submit" class="px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
const indStoreUrl = @json(route('admin.kpi-indicators.store'));
const indUpdateBase = @json(url('/admin/kpi/indicators'));

function openIndicatorModal(category, indicator = null) {
    document.getElementById('indCategory').value = category;
    const form = document.getElementById('indForm');

    if (indicator) {
        document.getElementById('indModalTitle').textContent = 'Edit Indikator ' + indicator.code;
        document.getElementById('indMethod').value = 'PUT';
        form.action = indUpdateBase + '/' + indicator.id;
        document.getElementById('indCode').value = indicator.code;
        document.getElementById('indWeight').value = indicator.weight;
        document.getElementById('indName').value = indicator.name;
        document.getElementById('indDescription').value = indicator.description || '';
        document.getElementById('indAutoSource').value = indicator.auto_source || '';
        document.getElementById('indIsCore').checked = !!indicator.is_core;
        document.getElementById('indIsActive').checked = !!indicator.is_active;
        document.getElementById('indActiveWrap').style.display = '';
    } else {
        document.getElementById('indModalTitle').textContent = 'Tambah Indikator';
        document.getElementById('indMethod').value = 'POST';
        form.action = indStoreUrl;
        form.reset();
        document.getElementById('indCategory').value = category;
        // Indikator baru selalu aktif; checkbox disembunyikan agar tidak membingungkan.
        document.getElementById('indIsActive').checked = true;
        document.getElementById('indActiveWrap').style.display = 'none';
    }

    const modal = document.getElementById('indModal');
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
}

function closeIndicatorModal() {
    const modal = document.getElementById('indModal');
    modal.style.display = 'none';
    modal.classList.add('hidden');
}
</script>
@endpush
