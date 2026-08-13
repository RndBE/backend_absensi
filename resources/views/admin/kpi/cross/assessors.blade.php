@extends('admin.layouts.app')
@section('title', 'Penilai Silang')

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">groups</span>
    <p class="text-sm font-medium">Belum ada periode aktif</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-cross-matrix.assessors', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

<div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4">
    <p class="text-[12px] text-indigo-800">
        Penilai dipilih berdasarkan <strong>intensitas interaksi kerja</strong>, bukan jabatan semata —
        yang setiap hari berurusan dengan Gudang bisa jadi staf admin, bukan manajernya.
        Target {{ \App\Models\KpiCrossAssessor::MIN_PER_DIVISION }}–{{ \App\Models\KpiCrossAssessor::MAX_PER_DIVISION }} penilai per divisi.
        Centang <em>Lapis B</em> hanya untuk yang berhak menilai individu (umumnya L2 dan L3).
    </p>
</div>

@foreach($divisions as $division)
@php $rows = $assessors[$division->id] ?? collect(); @endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[14px] font-bold text-gray-900">
            {{ $division->name }}
            <span class="ml-1 text-[12px] font-semibold {{ $rows->count() < \App\Models\KpiCrossAssessor::MIN_PER_DIVISION ? 'text-amber-600' : 'text-gray-400' }}">
                {{ $rows->count() }} penilai
            </span>
        </h3>
    </div>

    @if($rows->isNotEmpty())
    <div class="divide-y divide-gray-50">
        @foreach($rows as $row)
        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[13px] font-semibold text-gray-800">{{ $row->employee?->full_name ?? '—' }}</p>
                <p class="text-[11px] text-gray-400">
                    {{ $row->employee?->position ?: '—' }}
                    @if($row->employee?->kpiLevel)<span class="ml-1 px-1.5 py-0.5 bg-gray-100 rounded font-bold">{{ $row->employee->kpiLevel->code }}</span>@endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 text-[10px] font-bold rounded {{ $row->can_assess_individual ? 'text-indigo-700 bg-indigo-50 border border-indigo-200' : 'text-gray-500 bg-gray-100' }}">
                    {{ $row->can_assess_individual ? 'Lapis A + B' : 'Lapis A saja' }}
                </span>
                <form action="{{ route('admin.kpi-cross-matrix.assessors.destroy', $row->id) }}" method="POST"
                    data-confirm="Hapus {{ $row->employee?->full_name }} dari daftar penilai?">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-2 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg cursor-pointer">
                        <span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span>
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if($period && ! $period->isFinal())
    <form action="{{ route('admin.kpi-cross-matrix.assessors.store', $period->id) }}" method="POST" class="px-5 py-3 bg-gray-50/50 rounded-b-xl flex items-end gap-3 flex-wrap">
        @csrf
        <input type="hidden" name="department_id" value="{{ $division->id }}">
        <div class="flex-1 min-w-[240px]">
            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Tambah penilai</label>
            <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500">
                <option value="">— pilih karyawan —</option>
                @foreach($candidates as $candidate)
                <option value="{{ $candidate->id }}">
                    {{ $candidate->full_name }}{{ $candidate->kpiLevel ? ' ('.$candidate->kpiLevel->code.')' : '' }}
                </option>
                @endforeach
            </select>
        </div>
        <label class="inline-flex items-center gap-2 text-[12px] text-gray-700 pb-2">
            <input type="checkbox" name="can_assess_individual" value="1" class="w-4 h-4 rounded border-gray-300 text-indigo-600">
            Boleh Lapis B
        </label>
        <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm cursor-pointer">
            Tambah
        </button>
    </form>
    @endif
</div>
@endforeach
@endif
@endsection
