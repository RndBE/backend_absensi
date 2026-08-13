@extends('admin.layouts.app')
@section('title', 'Nilai Divisi ' . $department->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.kpi-cross.index', ['period' => $kpiPeriod->id]) }}"
        class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke daftar penilaian silang
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 px-5 py-4">
    <h3 class="text-[16px] font-bold text-gray-900">Lapis A — {{ $department->name }}</h3>
    <p class="text-[12px] text-gray-500 mt-0.5">
        Periode {{ $kpiPeriod->name }} · nilai berdasarkan pengalaman kerja
        {{ $kpiPeriod->start_date?->format('d/m/Y') }} – {{ $kpiPeriod->end_date?->format('d/m/Y') }}
    </p>
    <p class="text-[12px] text-gray-500 mt-2">
        Skala: <strong>5</strong> jauh melebihi harapan · <strong>3</strong> sesuai harapan · <strong>1</strong> bermasalah serius.
    </p>
</div>

<form action="{{ route('admin.kpi-cross.division.store', [$kpiPeriod->id, $department->id]) }}" method="POST">
    @csrf

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
        <div class="divide-y divide-gray-100">
            @foreach($items as $item)
            @php $current = old('scores.'.$item->code, $scores->get($item->code)?->score); @endphp
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex-1 min-w-[260px]">
                        <p class="text-[13px] font-semibold text-gray-800">
                            <span class="font-mono text-indigo-600">{{ $item->code }}</span> {{ $item->name }}
                            <span class="ml-1 text-[11px] text-gray-400">bobot {{ rtrim(rtrim(number_format((float) $item->weight, 2, ',', '.'), '0'), ',') }}%</span>
                        </p>
                        <p class="text-[12px] text-gray-600 mt-1">{{ $item->question }}</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @foreach([1, 2, 3, 4, 5] as $option)
                        <label class="cursor-pointer">
                            <input type="radio" name="scores[{{ $item->code }}]" value="{{ $option }}"
                                class="peer sr-only" @checked((int) $current === $option) required>
                            <span class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-[13px] font-bold text-gray-500 hover:border-indigo-400 transition-all peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600">
                                {{ $option }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Kolom kedua biasanya jauh lebih berguna daripada seluruh angkanya — di situ
         masalah alur kerja yang sesungguhnya terungkap (Bab 7.5). --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 rounded-t-xl">
            <h3 class="text-[14px] font-bold text-gray-900">Isian Bebas <span class="text-red-500">— wajib keduanya</span></h3>
        </div>
        <div class="px-5 py-4 space-y-4">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
                    Satu hal yang paling <strong>membantu</strong> dari divisi ini
                </label>
                <textarea name="comment_positive" rows="3" required maxlength="2000"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">{{ old('comment_positive', $submission?->comment_positive) }}</textarea>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
                    Satu hal yang paling <strong>perlu diperbaiki</strong> dari divisi ini
                </label>
                <textarea name="comment_improvement" rows="3" required maxlength="2000"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">{{ old('comment_improvement', $submission?->comment_improvement) }}</textarea>
                <p class="text-[11px] text-gray-500 mt-1">
                    Tulis soal proses kerja, bukan soal orangnya. Kritik keras yang faktual tidak akan disaring HRD.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex items-center justify-end gap-3">
        <a href="{{ route('admin.kpi-cross.index', ['period' => $kpiPeriod->id]) }}"
            class="px-4 py-2.5 text-[13px] font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-all">Batal</a>
        <button type="submit" class="px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-[16px] align-text-bottom">save</span> Simpan Penilaian
        </button>
    </div>
</form>
@endsection
