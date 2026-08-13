@extends('admin.layouts.app')
@section('title', 'Rubrik ' . $kpiIndicator->code)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.kpi-indicators.index', ['level' => $kpiIndicator->level?->code]) }}"
        class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke daftar indikator
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="font-mono text-indigo-600">{{ $kpiIndicator->code }}</span> — {{ $kpiIndicator->name }}
        </h3>
        <p class="text-[12px] text-gray-500 mt-1">
            {{ $kpiIndicator->level?->code }} · {{ $kpiIndicator->categoryName() }} · bobot
            {{ rtrim(rtrim(number_format((float) $kpiIndicator->weight, 2, ',', '.'), '0'), ',') }}%
        </p>
        <p class="text-[12px] text-gray-500 mt-2">
            Rubrik yang konkret adalah pembeda antara angka 1–5 yang berarti dan angka yang jadi selera
            masing-masing penilai. Teks ini <strong>dibekukan saat periode dibuka</strong> — mengubahnya
            tidak mempengaruhi periode yang sedang berjalan.
        </p>
    </div>

    <form action="{{ route('admin.kpi-indicators.rubrics.update', $kpiIndicator->id) }}" method="POST">
        @csrf @method('PUT')

        <div class="divide-y divide-gray-100">
            @foreach([5, 4, 3, 2, 1] as $score)
            @php $rubric = $rubrics->get($score); @endphp
            <div class="px-5 py-4 flex gap-4">
                <div class="shrink-0 w-32">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-[16px] font-bold text-white
                        {{ $score >= 4 ? 'bg-emerald-500' : ($score == 3 ? 'bg-gray-400' : 'bg-red-500') }}">
                        {{ $score }}
                    </div>
                    <div class="text-[11px] font-semibold text-gray-600 mt-1.5">
                        {{ \App\Models\KpiIndicatorRubric::PREDICATES[$score] }}
                    </div>
                </div>
                <div class="flex-1">
                    <textarea name="rubrics[{{ $score }}]" rows="2" required maxlength="500"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10"
                        placeholder="Patokan konkret untuk skor {{ $score }}">{{ old('rubrics.'.$score, $rubric?->description) }}</textarea>
                </div>
            </div>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
            <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">save</span> Simpan Rubrik
            </button>
        </div>
    </form>
</div>
@endsection
