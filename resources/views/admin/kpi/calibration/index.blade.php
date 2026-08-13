@extends('admin.layouts.app')
@section('title', 'Kalibrasi KPI')

@php
    $gradeLabels = \App\Models\KpiFinalResult::GRADE_LABELS;
    $maxCalibration = \App\Http\Controllers\Admin\KpiCalibrationController::MAX_CALIBRATION;
    $maxAdjustment = \App\Models\KpiCrossResult::MAX_ADJUSTMENT;
    $threshold = \App\Http\Controllers\Admin\KpiCalibrationController::DEVIATION_THRESHOLD;
@endphp

@section('content')
@if($periods->isEmpty())
<div class="bg-white rounded-xl border border-gray-200 p-14 text-center text-gray-400">
    <span class="material-symbols-outlined text-[40px] block mb-2">tune</span>
    <p class="text-sm font-medium">Belum ada periode penilaian</p>
</div>
@else

<div class="flex items-center gap-2 mb-4 flex-wrap">
    @foreach($periods as $p)
    <a href="{{ route('admin.kpi-calibration.index', ['period' => $p->id]) }}"
        class="px-4 py-2 text-[13px] font-semibold rounded-lg border transition-all {{ $period?->id === $p->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
        {{ $p->name }}
    </a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 px-5 py-4 flex items-start justify-between gap-4 flex-wrap">
    <div class="max-w-3xl">
        <h3 class="text-[15px] font-bold text-gray-900">Kalibrasi Antar Penilai — {{ $period->name }}</h3>
        <p class="text-[12px] text-gray-500 mt-1">
            Wajib dilakukan sebelum skor difinalkan. Tujuannya bukan memaksa distribusi normal, melainkan
            memastikan standar yang sama: divisi atau penilai yang rata-ratanya menyimpang lebih dari
            {{ number_format($threshold, 1, ',', '.') }} poin dari rata-rata perusahaan wajib menjelaskan di forum kalibrasi.
        </p>
    </div>
    <div class="text-right">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Rata-rata perusahaan</p>
        <p class="text-[28px] font-bold text-gray-900 leading-none mt-1">
            {{ $companyAverage === null ? '—' : number_format($companyAverage, 2, ',', '.') }}
        </p>
        <p class="text-[11px] text-gray-500 mt-1">{{ $results->count() }} hasil karyawan</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[14px] font-bold text-gray-900">Distribusi Nilai per Divisi</h3>
    </div>

    @if($departments->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">domain</span>
        <p class="text-sm font-medium">Belum ada hasil untuk dibandingkan</p>
        <p class="text-xs mt-1">Hasil dihitung saat periode masuk tahap Pemrosesan.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Divisi</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Orang</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Rata-rata</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Selisih</th>
                    @foreach($gradeLabels as $grade => $label)
                    <th class="py-3 px-3 text-center text-[11px] font-bold text-gray-500 uppercase">{{ $grade }}</th>
                    @endforeach
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($departments as $row)
                <tr class="border-b border-gray-50 {{ $row['needs_explanation'] ? 'bg-amber-50/40' : '' }}">
                    <td class="py-3.5 px-5 text-[13px] font-semibold text-gray-800">{{ $row['name'] }}</td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">{{ $row['count'] }}</td>
                    <td class="py-3.5 px-5 text-center text-[13px] font-bold text-gray-900">
                        {{ $row['average'] === null ? '—' : number_format($row['average'], 2, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        @if($row['deviation'] === null)
                        <span class="text-[12px] text-gray-400">—</span>
                        @else
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                            {{ $row['needs_explanation'] ? 'text-amber-700 bg-amber-50 border border-amber-200' : 'text-gray-600 bg-gray-100' }}">
                            {{ $row['deviation'] >= 0 ? '+' : '−' }}{{ number_format(abs($row['deviation']), 2, ',', '.') }}
                        </span>
                        @endif
                    </td>
                    @foreach($gradeLabels as $grade => $label)
                    <td class="py-3.5 px-3 text-center text-[12px] {{ ($row['grades'][$grade] ?? 0) > 0 ? 'text-gray-700 font-semibold' : 'text-gray-300' }}">
                        {{ $row['grades'][$grade] ?? 0 }}
                    </td>
                    @endforeach
                    <td class="py-3.5 px-5 text-[11px]">
                        @if($row['needs_explanation'])
                        <span class="text-amber-700 font-semibold">Penilai wajib menjelaskan standar yang dipakai</span>
                        @else
                        <span class="text-gray-400">Sejalan dengan perusahaan</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[14px] font-bold text-gray-900">Distribusi Skor per Penilai</h3>
        <p class="text-[12px] text-gray-500 mt-0.5">
            Dihitung dari skor butir yang diberikan penilai, bukan dari nilai akhir karyawan — yang
            dibandingkan di sini adalah standar penilainya, bukan kinerja yang dinilai.
        </p>
    </div>

    @if($assessors->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">groups</span>
        <p class="text-sm font-medium">Belum ada penilaian terkirim pada periode ini</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Penilai</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Dinilai</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Butir</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Rata-rata</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Selisih</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assessors as $row)
                <tr class="border-b border-gray-50 {{ $row['needs_explanation'] ? 'bg-amber-50/40' : '' }}">
                    <td class="py-3.5 px-5">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $row['assessor']?->full_name ?? '—' }}</div>
                        <div class="text-[11px] text-gray-400">{{ $row['assessor']?->department?->name ?? '—' }}</div>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">{{ $row['assessments'] }}</td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-500">{{ $row['items'] }}</td>
                    <td class="py-3.5 px-5 text-center text-[13px] font-bold text-gray-900">
                        {{ $row['average'] === null ? '—' : number_format($row['average'], 2, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        @if($row['deviation'] === null)
                        <span class="text-[12px] text-gray-400">—</span>
                        @else
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                            {{ $row['needs_explanation'] ? 'text-amber-700 bg-amber-50 border border-amber-200' : 'text-gray-600 bg-gray-100' }}">
                            {{ $row['deviation'] >= 0 ? '+' : '−' }}{{ number_format(abs($row['deviation']), 2, ',', '.') }}
                        </span>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-[11px]">
                        @if($row['needs_explanation'])
                        <span class="text-amber-700 font-semibold">
                            {{ $row['deviation'] > 0 ? 'Cenderung murah nilai' : 'Cenderung pelit nilai' }} — wajib menjelaskan
                        </span>
                        @else
                        <span class="text-gray-400">Sejalan dengan perusahaan</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Kalibrasi Nilai Akhir ({{ $results->count() }})</h3>
        <p class="text-[12px] text-gray-500 mt-0.5">
            Perubahan maksimal ±{{ number_format($maxCalibration, 1, ',', '.') }} poin dari nilai perhitungan dan wajib disertai catatan.
            Predikat dihitung ulang otomatis dari nilai hasil kalibrasi.
        </p>
    </div>

    @if($results->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">calculate</span>
        <p class="text-sm font-medium">Belum ada hasil untuk dikalibrasi</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Karyawan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Level</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Perhitungan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Berlaku</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Predikat</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Kalibrasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $result)
                <tr class="border-b border-gray-50 align-top">
                    <td class="py-3.5 px-5">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $result->employee->full_name }}</div>
                        <div class="text-[11px] text-gray-400">{{ $result->employee->department?->name ?? '—' }}</div>
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        <span class="px-2 py-0.5 text-[11px] font-bold text-gray-600 bg-gray-100 rounded">{{ $result->levelSnapshot?->code ?? '—' }}</span>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">
                        {{ $result->final_score === null ? '—' : number_format((float) $result->final_score, 2, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-5 text-center text-[14px] font-bold text-gray-900">
                        {{ $result->effectiveScore() === null ? '—' : number_format($result->effectiveScore(), 2, ',', '.') }}
                        @if($result->calibrated_score !== null)
                        <div class="text-[10px] font-normal text-indigo-500">sudah dikalibrasi</div>
                        @endif
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg
                            {{ in_array($result->grade, ['A', 'B']) ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : ($result->grade === 'C' ? 'text-indigo-700 bg-indigo-50 border border-indigo-200' : 'text-red-700 bg-red-50 border border-red-200') }}">
                            {{ $result->grade ?? '—' }}
                        </span>
                    </td>
                    <td class="py-3.5 px-5">
                        @if($result->calibration_note)
                        <p class="text-[11px] text-gray-500 mb-2 whitespace-pre-line">Catatan sebelumnya: {{ $result->calibration_note }}</p>
                        @endif
                        <form action="{{ route('admin.kpi-calibration.calibrate', $result->id) }}" method="POST"
                            class="flex flex-col lg:flex-row lg:items-start gap-2">
                            @csrf
                            <input type="number" name="calibrated_score" step="0.01" min="1" max="5" required
                                value="{{ $result->calibrated_score !== null ? number_format((float) $result->calibrated_score, 2, '.', '') : '' }}"
                                placeholder="Nilai"
                                class="w-24 px-3 py-2 border border-gray-300 rounded-xl text-[12px] outline-none focus:border-indigo-500 shrink-0">
                            <textarea name="calibration_note" rows="1" required minlength="10" maxlength="1000"
                                placeholder="Alasan kalibrasi — standar apa yang disamakan"
                                class="flex-1 min-w-[14rem] px-3 py-2 border border-gray-300 rounded-xl text-[12px] outline-none focus:border-indigo-500">{{ $result->calibration_note }}</textarea>
                            <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-xl shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer shrink-0">
                                Simpan
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@if($pendingApproval->isNotEmpty())
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
    <p class="text-[13px] font-bold text-amber-800 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">pending_actions</span>
        {{ $pendingApproval->count() }} penyesuaian menunggu persetujuan atasan di atas
    </p>
    <p class="text-[12px] text-amber-700 mt-1">
        Selama belum disetujui, penyesuaian ini tidak ikut dihitung pada skor kolaborasi. Aturan ini
        mencegah seorang atasan menaikkan seluruh timnya demi melindungi divisinya.
    </p>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Penyesuaian Atasan atas Skor Kolaborasi ({{ $crossResults->count() }})</h3>
        <p class="text-[12px] text-gray-500 mt-0.5">
            Maksimal ±{{ number_format($maxAdjustment, 1, ',', '.') }} poin, wajib beralasan tertulis beserta contoh kejadian.
            Menyesuaikan lebih dari {{ \App\Models\KpiCrossResult::APPROVAL_TRIGGER_COUNT }} bawahan ke arah yang sama membuat penyesuaian tertahan sampai disetujui.
        </p>
    </div>

    @if($crossResults->isEmpty())
    <div class="py-14 text-center text-gray-400">
        <span class="material-symbols-outlined text-[40px] block mb-2">diversity_3</span>
        <p class="text-sm font-medium">Belum ada skor kolaborasi individu pada periode ini</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Karyawan</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Kolaborasi</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Berlaku</th>
                    <th class="py-3 px-5 text-center text-[11px] font-bold text-gray-500 uppercase">Kuorum</th>
                    <th class="py-3 px-5 text-left text-[11px] font-bold text-gray-500 uppercase">Penyesuaian Atasan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($crossResults as $row)
                <tr class="border-b border-gray-50 align-top {{ $row->adjustmentPending() ? 'bg-amber-50/40' : '' }}">
                    <td class="py-3.5 px-5">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $row->employee->full_name }}</div>
                        <div class="text-[11px] text-gray-400">{{ $row->employee->department?->name ?? '—' }}</div>
                    </td>
                    <td class="py-3.5 px-5 text-center text-[12px] text-gray-600">
                        {{ $row->score_collaboration === null ? '—' : number_format((float) $row->score_collaboration, 2, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-5 text-center text-[14px] font-bold text-gray-900">
                        {{ $row->effectiveScore() === null ? '—' : number_format($row->effectiveScore(), 2, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-5 text-center">
                        @if($row->quorum_met)
                        <span class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded">TERPENUHI</span>
                        @else
                        <span class="px-2 py-0.5 text-[10px] font-bold text-gray-500 bg-gray-100 border border-gray-200 rounded">BELUM</span>
                        @endif
                        <div class="text-[10px] text-gray-400 mt-1">{{ $row->assessor_count }} penilai / {{ $row->division_count }} divisi</div>
                    </td>
                    <td class="py-3.5 px-5">
                        @if($row->superior_adjustment !== null)
                        <div class="mb-2 flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg {{ (float) $row->superior_adjustment >= 0 ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-red-700 bg-red-50 border border-red-200' }}">
                                {{ (float) $row->superior_adjustment >= 0 ? '+' : '−' }}{{ number_format(abs((float) $row->superior_adjustment), 2, ',', '.') }}
                            </span>
                            <span class="text-[11px] text-gray-500">
                                oleh {{ $row->adjuster?->full_name ?? 'tidak tercatat' }}
                                {{ $row->adjusted_at ? '· '.$row->adjusted_at->format('d/m/Y') : '' }}
                            </span>
                            @if($row->adjustmentPending())
                            <span class="px-2 py-0.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded">MENUNGGU PERSETUJUAN</span>
                            @elseif($row->adjustment_approved_by !== null)
                            <span class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded">DISETUJUI</span>
                            @endif
                        </div>
                        @if($row->adjustment_reason)
                        <p class="text-[11px] text-gray-500 mb-2 whitespace-pre-line">{{ $row->adjustment_reason }}</p>
                        @endif
                        @endif

                        @if($row->adjustmentPending())
                        <form action="{{ route('admin.kpi-calibration.approve-adjustment', $row->id) }}" method="POST" class="mb-2"
                            data-confirm="Setujui penyesuaian ini? Setelah disetujui, penyesuaian langsung berlaku pada skor kolaborasi.">
                            @csrf
                            <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[15px] align-text-bottom">verified</span> Setujui Penyesuaian
                            </button>
                        </form>
                        @endif

                        <form action="{{ route('admin.kpi-calibration.adjust-cross', $row->id) }}" method="POST"
                            class="flex flex-col lg:flex-row lg:items-start gap-2">
                            @csrf
                            <input type="number" name="superior_adjustment" step="0.05"
                                min="-{{ $maxAdjustment }}" max="{{ $maxAdjustment }}" required
                                value="{{ $row->superior_adjustment !== null ? number_format((float) $row->superior_adjustment, 2, '.', '') : '' }}"
                                placeholder="±0,00"
                                class="w-24 px-3 py-2 border border-gray-300 rounded-xl text-[12px] outline-none focus:border-indigo-500 shrink-0">
                            <textarea name="adjustment_reason" rows="1" required minlength="10" maxlength="1000"
                                placeholder="Alasan dan contoh kejadian"
                                class="flex-1 min-w-[14rem] px-3 py-2 border border-gray-300 rounded-xl text-[12px] outline-none focus:border-indigo-500">{{ $row->adjustment_reason }}</textarea>
                            <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer shrink-0">
                                Simpan
                            </button>
                        </form>
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
