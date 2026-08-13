@extends('admin.layouts.app')
@section('title', 'Penilaian ' . $kpiAssessment->employee->full_name)

@php
    $categoryNames = \App\Models\KpiLevel::CATEGORIES;
    $readonly = $kpiAssessment->isSubmitted();
@endphp

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.kpi-assessments.index', ['period' => $kpiAssessment->kpi_period_id]) }}"
        class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke daftar penilaian
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 px-5 py-4">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-[16px] font-bold text-gray-900">{{ $kpiAssessment->employee->full_name }}</h3>
            <p class="text-[12px] text-gray-500 mt-0.5">
                {{ $kpiAssessment->employee->position ?: '—' }} ·
                {{ $kpiAssessment->employee->department?->name ?? '—' }} ·
                Level {{ $levelSnapshot->code }} ({{ $levelSnapshot->name }})
            </p>
            <p class="text-[12px] text-gray-500 mt-0.5">
                Periode {{ $kpiAssessment->period->name }} ·
                Anda sebagai <strong>{{ strtolower($kpiAssessment->roleLabel()) }}</strong>
                (bobot {{ rtrim(rtrim(number_format((float) $kpiAssessment->weight, 2, ',', '.'), '0'), ',') }}%)
            </p>
        </div>
        @if($readonly)
        <span class="px-3 py-1.5 text-[12px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">
            Terkirim {{ $kpiAssessment->submitted_at?->format('d/m/Y H:i') }}
        </span>
        @endif
    </div>

    <div class="mt-3 grid grid-cols-3 gap-3">
        @foreach($categoryNames as $code => $name)
        <div class="rounded-lg bg-gray-50 border border-gray-100 px-3 py-2">
            <p class="text-[11px] font-bold text-gray-500 uppercase">{{ $name }}</p>
            <p class="text-[13px] font-bold text-gray-800">
                {{ rtrim(rtrim(number_format((float) $levelSnapshot->categoryWeights()[$code], 2, ',', '.'), '0'), ',') }}%
                dari nilai akhir
            </p>
        </div>
        @endforeach
    </div>
</div>

{{-- Aksi bawaan = simpan draft. Tombol "Kirim" mengarahkan ulang lewat formaction.
     Keduanya POST, jadi tidak ada input _method yang bisa nyasar ke route lain. --}}
<form action="{{ route('admin.kpi-assessments.update', $kpiAssessment->id) }}" method="POST" id="kpiForm">
    @csrf

    @foreach($categoryNames as $code => $categoryName)
    @php $rows = $indicators[$code] ?? collect(); @endphp
    @if($rows->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 rounded-t-xl">
            <h3 class="text-[14px] font-bold text-gray-900">{{ $categoryName }}</h3>
        </div>

        <div class="divide-y divide-gray-100">
            @foreach($rows as $indicator)
            @php
                $score = $scores->get($indicator->id);
                $value = old('scores.'.$indicator->id, $score?->score_raw);
                $evidence = old('evidence.'.$indicator->id, $score?->evidence_text);
                $locked = $readonly || $indicator->is_auto_filled;
            @endphp
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex-1 min-w-[240px]">
                        <p class="text-[13px] font-semibold text-gray-800">
                            <span class="font-mono text-indigo-600">{{ $indicator->code }}</span>
                            {{ $indicator->name }}
                            @if($indicator->is_core)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded">INTI</span>
                            @endif
                            <span class="ml-1 text-[11px] text-gray-400">bobot {{ rtrim(rtrim(number_format((float) $indicator->weight, 2, ',', '.'), '0'), ',') }}%</span>
                        </p>
                        @if($indicator->description)
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ $indicator->description }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-1.5">
                        @foreach([1, 2, 3, 4, 5] as $option)
                        <label class="cursor-pointer" title="{{ $indicator->rubricFor($option) }}">
                            <input type="radio" name="scores[{{ $indicator->id }}]" value="{{ $option }}"
                                class="peer sr-only kpi-score" data-indicator="{{ $indicator->id }}"
                                @checked((int) $value === $option) @disabled($locked)>
                            <span class="w-9 h-9 flex items-center justify-center rounded-lg border text-[13px] font-bold transition-all
                                {{ $locked ? 'opacity-60' : 'hover:border-indigo-400' }}
                                border-gray-200 text-gray-500 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600">
                                {{ $option }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>

                @if($indicator->is_auto_filled)
                <div class="mt-2.5 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2">
                    <p class="text-[11px] font-semibold text-emerald-800">
                        Diisi otomatis oleh sistem — tidak bisa diubah penilai.
                    </p>
                    @if($score?->evidence_text)
                    <p class="text-[11px] text-emerald-700 mt-0.5">{{ $score->evidence_text }}</p>
                    @endif
                </div>
                @else
                <div class="mt-2.5" data-evidence-wrap="{{ $indicator->id }}">
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                        Contoh kejadian konkret
                        <span class="text-red-500 hidden" data-evidence-required="{{ $indicator->id }}">— wajib untuk skor 4–5 dan 1–2</span>
                    </label>
                    <textarea name="evidence[{{ $indicator->id }}]" rows="2" maxlength="2000" @disabled($readonly)
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[12px] outline-none focus:border-indigo-500 disabled:bg-gray-50"
                        placeholder="Kejadian nyata yang mendasari skor ini">{{ $evidence }}</textarea>
                </div>

                <details class="mt-2">
                    <summary class="text-[11px] font-semibold text-gray-500 cursor-pointer hover:text-gray-700">Lihat rubrik 1–5</summary>
                    <div class="mt-1.5 space-y-1">
                        @foreach([5, 4, 3, 2, 1] as $option)
                        <p class="text-[11px] text-gray-600">
                            <span class="inline-block w-4 font-bold {{ $option >= 4 ? 'text-emerald-600' : ($option == 3 ? 'text-gray-500' : 'text-red-600') }}">{{ $option }}</span>
                            {{ $indicator->rubricFor($option) ?? '—' }}
                        </p>
                        @endforeach
                    </div>
                </details>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @endforeach

    @unless($readonly)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex items-center justify-end gap-3">
        <button type="submit" class="px-4 py-2.5 text-[13px] font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-all cursor-pointer">
            Simpan Draft
        </button>
        <button type="submit" formaction="{{ route('admin.kpi-assessments.submit', $kpiAssessment->id) }}"
            class="px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-[16px] align-text-bottom">send</span> Kirim Penilaian
        </button>
    </div>
    @endunless
</form>
@endsection

@push('scripts')
<script>
// Penanda "bukti wajib" muncul begitu penilai memilih skor ekstrem, bukan baru saat submit
// ditolak server — server tetap jadi penentu, ini hanya supaya tidak bolak-balik.
function syncEvidenceHints() {
    document.querySelectorAll('.kpi-score:checked').forEach(function (input) {
        const id = input.dataset.indicator;
        const label = document.querySelector('[data-evidence-required="' + id + '"]');
        if (!label) return;
        const score = parseInt(input.value, 10);
        label.classList.toggle('hidden', score === 3);
    });
}

document.addEventListener('change', function (e) {
    if (e.target.classList && e.target.classList.contains('kpi-score')) {
        syncEvidenceHints();
    }
});

syncEvidenceHints();
</script>
@endpush
