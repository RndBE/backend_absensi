@extends('admin.layouts.app')
@section('title', 'Matriks Relasi Kerja')

@section('content')
@if($problems)
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
    <p class="text-[13px] font-bold text-amber-800 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">warning</span> Matriks belum siap
    </p>
    <ul class="mt-2 ml-6 list-disc text-[12px] text-amber-700 space-y-0.5">
        @foreach($problems as $problem)<li>{{ $problem }}</li>@endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[18px] align-text-bottom">hub</span> Matriks Relasi Kerja
        </h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Hanya divisi yang punya hubungan kerja langsung yang saling menilai. Minimal
            {{ \App\Models\KpiDivisionRelation::MIN_PARTNERS }}, maksimal {{ \App\Models\KpiDivisionRelation::MAX_PARTNERS }} mitra per divisi —
            lebih dari itu mutu pengisian anjlok. Relasi otomatis dibuat <strong>dua arah</strong>.
        </p>
    </div>

    @if($divisions->isEmpty())
    <div class="py-10 text-center text-gray-400 text-[13px]">
        Belum ada departemen yang ditandai sebagai divisi. Tandai di tabel bawah.
    </div>
    @else
    <div class="divide-y divide-gray-100">
        @foreach($divisions as $division)
        @php $current = ($relations[$division->id] ?? collect())->pluck('partner_department_id')->all(); @endphp
        <form action="{{ route('admin.kpi-cross-matrix.update', $division->id) }}" method="POST" class="px-5 py-4">
            @csrf @method('PUT')
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-[200px]">
                    <p class="text-[13px] font-bold text-gray-800">{{ $division->name }}</p>
                    <p class="text-[11px] text-gray-400">
                        {{ count($current) }} mitra
                        @if($division->is_shared_service)· <span class="text-indigo-600 font-semibold">layanan umum</span>@endif
                    </p>
                </div>
                <div class="flex-1 flex flex-wrap gap-2 min-w-[300px]">
                    @foreach($divisions as $candidate)
                    @continue($candidate->id === $division->id)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="partners[]" value="{{ $candidate->id }}"
                            class="peer sr-only" @checked(in_array($candidate->id, $current))>
                        <span class="px-3 py-1.5 text-[12px] font-semibold rounded-lg border border-gray-200 text-gray-600 hover:border-indigo-400 transition-all peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 inline-block">
                            {{ $candidate->name }}
                        </span>
                    </label>
                    @endforeach
                </div>
                <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm cursor-pointer">
                    Simpan
                </button>
            </div>
        </form>
        @endforeach
    </div>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Butir Penilaian Silang</h3>
        <p class="text-[12px] text-gray-500 mt-1">Dibekukan saat periode dibuka. Perubahan tidak mempengaruhi periode berjalan.</p>
    </div>
    @foreach(\App\Models\KpiCrossItem::LAYERS as $layer => $label)
    @php $rows = $items[$layer] ?? collect(); @endphp
    <div class="px-5 py-3 border-b border-gray-50 last:border-0">
        <p class="text-[13px] font-bold text-gray-700 mb-2">
            {{ $label }}
            <span class="ml-1 text-[11px] font-semibold {{ abs($rows->sum('weight') - 100) >= 0.01 ? 'text-red-600' : 'text-gray-400' }}">
                total bobot {{ rtrim(rtrim(number_format((float) $rows->sum('weight'), 2, ',', '.'), '0'), ',') }}%
            </span>
        </p>
        <div class="space-y-1">
            @foreach($rows as $item)
            <p class="text-[12px] text-gray-600">
                <span class="font-mono text-indigo-600">{{ $item->code }}</span>
                {{ $item->name }}
                <span class="text-gray-400">— {{ rtrim(rtrim(number_format((float) $item->weight, 2, ',', '.'), '0'), ',') }}%</span>
            </p>
            @endforeach
        </div>
    </div>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Penandaan Departemen</h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Tandai simpul mana yang berperan sebagai <strong>divisi</strong> — unit yang saling menilai.
            Departemen induk besar sering membawahi beberapa unit yang menurut kerangka adalah divisi terpisah,
            jadi penandaan bawaan perlu ditinjau sebelum periode pertama dibuka.
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-2.5 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Departemen</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Kode</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Karyawan</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Divisi</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Layanan Umum</th>
                    <th class="py-2.5 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allDepartments as $dept)
                <tr class="border-b border-gray-50">
                    <td class="py-2.5 px-5 text-[13px] {{ $dept->parent_id ? 'pl-10 text-gray-600' : 'font-semibold text-gray-800' }}">
                        {{ $dept->name }}
                    </td>
                    <td class="py-2.5 px-5 text-center">
                        <input type="text" name="kpi_code" form="dept-{{ $dept->id }}" value="{{ $dept->kpi_code }}" maxlength="20"
                            class="w-20 px-2 py-1 border border-gray-300 rounded-lg text-[12px] text-center outline-none focus:border-indigo-500">
                    </td>
                    <td class="py-2.5 px-5 text-center text-[12px] text-gray-500">{{ $dept->employees_count }}</td>
                    <td class="py-2.5 px-5 text-center">
                        <input type="checkbox" name="is_division" form="dept-{{ $dept->id }}" value="1" @checked($dept->is_division)
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                    </td>
                    <td class="py-2.5 px-5 text-center">
                        <input type="checkbox" name="is_shared_service" form="dept-{{ $dept->id }}" value="1" @checked($dept->is_shared_service)
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                    </td>
                    <td class="py-2.5 px-5 text-center">
                        <button type="submit" form="dept-{{ $dept->id }}"
                            class="px-3 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                            Simpan
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Form di luar tabel, dirujuk lewat atribut form="..." — <form> di dalam <tr> tidak sah. --}}
@foreach($allDepartments as $dept)
<form action="{{ route('admin.kpi-cross-matrix.update-flags', $dept->id) }}" method="POST" id="dept-{{ $dept->id }}">
    @csrf @method('PUT')
</form>
@endforeach
@endsection
