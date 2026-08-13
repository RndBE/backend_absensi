@extends('admin.layouts.app')
@section('title', 'Pemrosesan HRD')

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">rule_settings</span>
    <p class="text-sm font-medium">Belum ada periode penilaian</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-processing.index', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 flex items-start justify-between flex-wrap gap-3">
        <div class="max-w-2xl">
            <h3 class="text-[15px] font-bold text-gray-900">Pemrosesan HRD — {{ $period->name }}</h3>
            <p class="text-[12px] text-gray-500 mt-1">
                Koreksi anti-penyalahgunaan (Bab 7.8) dan penyaringan komentar (Bab 7.7). Dikerjakan
                sebelum sesi kalibrasi. Pemrosesan boleh dijalankan berulang: hitungannya selalu diulang
                dari skor mentah, dan penyaringan komentar yang sudah Anda lakukan tidak ikut terhapus.
            </p>
        </div>
        <form action="{{ route('admin.kpi-processing.run', $period->id) }}" method="POST"
            data-confirm="Jalankan pemeriksaan anti-penyalahgunaan untuk periode ini? Hasil pemeriksaan sebelumnya dihitung ulang.">
            @csrf
            <button type="submit" class="px-4 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer shrink-0">
                <span class="material-symbols-outlined text-[16px] align-text-bottom">play_arrow</span> Jalankan Pemrosesan
            </button>
        </form>
    </div>
</div>

@php
$cards = [
    ['label' => 'Kuesioner terkirim', 'value' => $summary['submitted'], 'icon' => 'assignment_turned_in', 'tone' => 'text-gray-900'],
    ['label' => 'Dibuang — pengisian asal', 'value' => $summary['straight_lining'], 'icon' => 'block', 'tone' => 'text-red-600'],
    ['label' => 'Skor dipangkas — persekongkolan', 'value' => $summary['collusion'], 'icon' => 'content_cut', 'tone' => 'text-amber-600'],
    ['label' => 'Penilai dikoreksi — kemurahan hati', 'value' => $summary['leniency'], 'icon' => 'balance', 'tone' => 'text-indigo-600'],
    ['label' => 'Komentar disembunyikan', 'value' => $summary['hidden'], 'icon' => 'visibility_off', 'tone' => 'text-gray-500'],
];
@endphp

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    @foreach($cards as $card)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3.5">
        <span class="material-symbols-outlined text-[18px] text-gray-400">{{ $card['icon'] }}</span>
        <p class="text-[22px] font-bold {{ $card['tone'] }} leading-none mt-1">{{ $card['value'] }}</p>
        <p class="text-[10px] text-gray-500 mt-1.5 leading-tight">{{ $card['label'] }}</p>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[14px] font-bold text-gray-900">Koreksi Kemurahan Hati Penilai ({{ $leniency->count() }})</h3>
            <p class="text-[12px] text-gray-500 mt-0.5">
                Diterapkan hanya bila selisih rata-rata penilai terhadap rata-rata perusahaan melebihi 0,5 poin.
            </p>
        </div>

        @if($leniency->isEmpty())
        <div class="py-12 text-center text-gray-400">
            <span class="material-symbols-outlined text-[34px] block mb-1.5">balance</span>
            <p class="text-[13px] font-medium">Tidak ada penilai yang menyimpang jauh</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Penilai</th>
                        <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Rata-rata</th>
                        <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Perusahaan</th>
                        <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Koreksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leniency as $row)
                    <tr class="border-b border-gray-50">
                        <td class="py-3 px-5">
                            <div class="text-[13px] font-semibold text-gray-800">{{ $row->assessor?->full_name ?? '—' }}</div>
                            <div class="text-[11px] text-gray-400">{{ $row->assessor?->department?->name ?? '—' }}</div>
                        </td>
                        <td class="py-3 px-5 text-center text-[12px] text-gray-600">{{ number_format((float) $row->assessor_mean, 2, ',', '.') }}</td>
                        <td class="py-3 px-5 text-center text-[12px] text-gray-500">{{ number_format((float) $row->company_mean, 2, ',', '.') }}</td>
                        <td class="py-3 px-5 text-center">
                            <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg {{ (float) $row->correction_value > 0 ? 'text-amber-700 bg-amber-50 border border-amber-200' : 'text-indigo-700 bg-indigo-50 border border-indigo-200' }}">
                                {{ (float) $row->correction_value > 0 ? '−' : '+' }}{{ number_format(abs((float) $row->correction_value), 2, ',', '.') }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[14px] font-bold text-gray-900">Pasangan Divisi Ditandai Pembalasan ({{ count($retaliation) }})</h3>
            <p class="text-[12px] text-gray-500 mt-0.5">
                Skornya sengaja tidak diubah otomatis. Panggil kedua kepala divisi — di balik angka ini
                biasanya ada konflik nyata yang perlu diselesaikan.
            </p>
        </div>

        @if($retaliation === [])
        <div class="py-12 text-center text-gray-400">
            <span class="material-symbols-outlined text-[34px] block mb-1.5">handshake</span>
            <p class="text-[13px] font-medium">Tidak ada pasangan divisi yang saling memberi skor rendah</p>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($retaliation as $pair)
            <div class="px-5 py-3.5 flex items-center justify-between gap-3 flex-wrap">
                <div class="text-[13px] font-semibold text-gray-800">
                    {{ $pair['department_name'] }}
                    <span class="material-symbols-outlined text-[15px] text-gray-300 align-middle">sync_alt</span>
                    {{ $pair['partner_name'] }}
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 text-[11px] font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg">
                        beri {{ number_format($pair['score_to_partner'], 2, ',', '.') }}
                    </span>
                    <span class="px-2.5 py-1 text-[11px] font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg">
                        terima {{ number_format($pair['score_from_partner'], 2, ',', '.') }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
    <p class="text-[13px] font-bold text-amber-800 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">warning</span> Batas penyaringan komentar
    </p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2.5 text-[12px] text-amber-800">
        <div>
            <p class="font-semibold">Boleh dibuang</p>
            <ul class="mt-1 ml-4 list-disc space-y-0.5">
                <li>Menyerang pribadi</li>
                <li>Menyinggung SARA atau fisik</li>
                <li>Menyebut nama orang lain di luar konteks kerja</li>
                <li>Tidak berhubungan dengan pekerjaan</li>
            </ul>
        </div>
        <div>
            <p class="font-semibold">Tidak boleh dibuang</p>
            <ul class="mt-1 ml-4 list-disc space-y-0.5">
                <li>Kritik keras yang faktual</li>
                <li>Keluhan berulang tentang proses</li>
                <li>Kritik terhadap atasan</li>
                <li>Perbedaan pendapat yang tajam tapi relevan</li>
            </ul>
        </div>
    </div>
    <p class="text-[12px] text-amber-700 mt-2.5">
        Jika HRD mulai menghaluskan kritik yang benar, sistem ini mati dalam satu periode. Setiap
        penyembunyian bisa ditarik kembali — gunakan itu saat ragu.
    </p>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mt-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Komentar Lapis A — Divisi ({{ $commentsA->count() }})</h3>
        <p class="text-[12px] text-gray-500 mt-0.5">Identitas penilai tidak ditampilkan di halaman ini.</p>
    </div>

    @if($commentsA->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">comments_disabled</span>
        <p class="text-sm font-medium">Belum ada komentar Lapis A</p>
    </div>
    @else
    <div class="divide-y divide-gray-50">
        @foreach($commentsA as $row)
        <div class="px-5 py-4 {{ $row->comment_hidden ? 'bg-gray-50/60' : '' }}">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="text-[13px] font-semibold text-gray-800">
                    Untuk divisi {{ $row->targetDepartment?->name ?? '—' }}
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-gray-400">{{ $row->submitted_at?->format('d/m/Y') }}</span>
                    @if($row->comment_hidden)
                    <span class="px-2 py-0.5 text-[10px] font-bold text-gray-600 bg-gray-100 border border-gray-200 rounded">DISEMBUNYIKAN</span>
                    @endif
                </div>
            </div>

            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                @if(trim((string) $row->comment_positive) !== '')
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2">
                    <p class="text-[11px] font-bold text-emerald-700">Yang sudah baik</p>
                    <p class="text-[12px] text-gray-700 mt-1 whitespace-pre-line">{{ $row->comment_positive }}</p>
                </div>
                @endif
                @if(trim((string) $row->comment_improvement) !== '')
                <div class="rounded-lg border border-amber-200 bg-amber-50/60 px-3 py-2">
                    <p class="text-[11px] font-bold text-amber-700">Yang perlu diperbaiki</p>
                    <p class="text-[12px] text-gray-700 mt-1 whitespace-pre-line">{{ $row->comment_improvement }}</p>
                </div>
                @endif
            </div>

            <form action="{{ route('admin.kpi-processing.hide-comment', ['layer' => 'a', 'id' => $row->id]) }}" method="POST"
                class="mt-3 flex flex-col sm:flex-row sm:items-center gap-2">
                @csrf
                @if($row->comment_hidden)
                <input type="hidden" name="action" value="restore">
                <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[15px] align-text-bottom">visibility</span> Tampilkan kembali
                </button>
                <span class="text-[11px] text-gray-500">Komentar ini tidak sampai ke pihak yang dinilai selama disembunyikan.</span>
                @else
                <input type="hidden" name="action" value="hide">
                <input type="text" name="hidden_reason" required minlength="5" maxlength="100"
                    placeholder="Alasan penyembunyian — mis. menyerang pribadi, menyinggung SARA"
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-[12px] outline-none focus:border-indigo-500">
                <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer shrink-0">
                    <span class="material-symbols-outlined text-[15px] align-text-bottom">visibility_off</span> Sembunyikan
                </button>
                @endif
            </form>
        </div>
        @endforeach
    </div>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mt-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Komentar Lapis B — Individu ({{ $commentsB->count() }})</h3>
        <p class="text-[12px] text-gray-500 mt-0.5">Identitas penilai tidak ditampilkan di halaman ini.</p>
    </div>

    @if($commentsB->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">comments_disabled</span>
        <p class="text-sm font-medium">Belum ada komentar Lapis B</p>
    </div>
    @else
    <div class="divide-y divide-gray-50">
        @foreach($commentsB as $row)
        <div class="px-5 py-4 {{ $row->comment_hidden ? 'bg-gray-50/60' : '' }}">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="text-[13px] font-semibold text-gray-800">
                    Untuk {{ $row->targetEmployee?->full_name ?? '—' }}
                    <span class="text-[11px] font-normal text-gray-400">{{ $row->targetEmployee?->employee_code }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-gray-400">{{ $row->submitted_at?->format('d/m/Y') }}</span>
                    @if($row->comment_hidden)
                    <span class="px-2 py-0.5 text-[10px] font-bold text-gray-600 bg-gray-100 border border-gray-200 rounded">DISEMBUNYIKAN</span>
                    @endif
                </div>
            </div>

            <p class="text-[12px] text-gray-700 mt-2 whitespace-pre-line">{{ $row->comment }}</p>

            <form action="{{ route('admin.kpi-processing.hide-comment', ['layer' => 'b', 'id' => $row->id]) }}" method="POST"
                class="mt-3 flex flex-col sm:flex-row sm:items-center gap-2">
                @csrf
                @if($row->comment_hidden)
                <input type="hidden" name="action" value="restore">
                <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[15px] align-text-bottom">visibility</span> Tampilkan kembali
                </button>
                <span class="text-[11px] text-gray-500">Komentar ini tidak sampai ke pihak yang dinilai selama disembunyikan.</span>
                @else
                <input type="hidden" name="action" value="hide">
                <input type="text" name="hidden_reason" required minlength="5" maxlength="100"
                    placeholder="Alasan penyembunyian — mis. menyerang pribadi, menyinggung SARA"
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-xl text-[12px] outline-none focus:border-indigo-500">
                <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer shrink-0">
                    <span class="material-symbols-outlined text-[15px] align-text-bottom">visibility_off</span> Sembunyikan
                </button>
                @endif
            </form>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endif
@endsection
