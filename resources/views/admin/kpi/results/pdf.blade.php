<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Lembar Hasil KPI {{ $kpiResult->employee->full_name }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9.5px;
        color: #1a1a1a;
        background: #fff;
        padding: 26px 30px;
    }

    .w100 { width: 100%; }
    .bold { font-weight: bold; }
    .center { text-align: center; }
    .right { text-align: right; }
    .muted { color: #6b7280; }

    .company-name { font-size: 15px; font-weight: bold; color: #111; }
    .company-addr { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .doc-title { font-size: 13px; font-weight: bold; letter-spacing: 2px; color: #111; }
    .doc-sub { font-size: 8px; color: #6b7280; margin-top: 2px; }

    .divider-top { border-top: 2px solid #111; padding-top: 10px; margin-bottom: 12px; }

    .info-tbl td { padding: 2px 0; font-size: 9.5px; vertical-align: top; }
    .info-key { color: #6b7280; width: 92px; }
    .info-sep { width: 8px; color: #6b7280; }
    .info-val { font-weight: 600; }
    .info-gap { width: 26px; }

    .trial {
        margin-top: 10px;
        border: 1px solid #f59e0b;
        background: #fffbeb;
        color: #92400e;
        padding: 6px 9px;
        font-size: 8.5px;
        font-weight: bold;
    }

    .summary {
        margin-top: 14px;
        border: 1px solid #d1d5db;
        border-collapse: collapse;
        width: 100%;
    }
    .summary td { padding: 9px 12px; vertical-align: middle; }
    .summary .score-big { font-size: 26px; font-weight: bold; color: #111; line-height: 1; }
    .summary .score-cap { font-size: 8px; color: #6b7280; letter-spacing: 1px; }
    .summary .grade-box {
        border: 1.5px solid #111;
        padding: 5px 10px;
        font-size: 16px;
        font-weight: bold;
        text-align: center;
    }

    .section-title {
        margin-top: 14px;
        margin-bottom: 5px;
        font-size: 10px;
        font-weight: bold;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: #111;
    }

    .grid-tbl { width: 100%; border-collapse: collapse; }
    .grid-tbl th {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        padding: 5px 8px;
        font-size: 8.5px;
        font-weight: bold;
        text-align: left;
    }
    .grid-tbl td {
        border: 1px solid #e5e7eb;
        padding: 4.5px 8px;
        font-size: 9px;
        vertical-align: top;
    }
    .grid-tbl .num { text-align: right; white-space: nowrap; }
    .grid-tbl .code { font-family: "DejaVu Sans Mono", Courier, monospace; font-size: 8.5px; color: #4338ca; }
    .grid-tbl .total-row td {
        background: #f9fafb;
        font-weight: bold;
        border-top: 1.5px solid #d1d5db;
    }
    .grid-tbl .cat-row td { background: #eef2ff; font-weight: bold; }

    .evidence {
        margin-top: 3px;
        font-size: 8px;
        color: #4b5563;
        line-height: 1.35;
    }
    .evidence-src { font-weight: bold; color: #374151; }

    .sign-wrap { margin-top: 22px; page-break-inside: avoid; }
    .sign-tbl { width: 100%; border-collapse: collapse; }
    .sign-tbl td { width: 33.33%; vertical-align: top; padding: 0 8px; font-size: 9px; }
    .sign-role { font-weight: bold; }
    .sign-note { color: #6b7280; font-size: 8px; margin-top: 1px; }
    .sign-line {
        margin-top: 52px;
        border-top: 1px solid #111;
        padding-top: 3px;
        font-size: 8.5px;
        color: #374151;
    }

    .footer {
        margin-top: 18px;
        border-top: 1px solid #e5e7eb;
        padding-top: 6px;
        font-size: 7.5px;
        color: #9ca3af;
        text-align: center;
        line-height: 1.4;
    }
</style>
</head>
<body>

@php
    $weight = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $score = fn ($value) => $value === null ? '—' : number_format((float) $value, 2, ',', '.');
    $levelWeights = $kpiResult->levelSnapshot?->categoryWeights() ?? [];
    $categoryFields = ['EX' => 'score_excellence', 'CO' => 'score_contribution', 'LD' => 'score_leadership'];
@endphp

<table class="w100">
    <tr>
        <td style="vertical-align:top;">
            @if($logoBase64)
            <img src="{{ $logoBase64 }}" style="height:34px; margin-bottom:4px;">
            @endif
            <div class="company-name">{{ $company->name ?? '' }}</div>
            @if($company->address ?? null)
            <div class="company-addr">{{ $company->address }}</div>
            @endif
        </td>
        <td style="vertical-align:top; text-align:right;">
            <div class="doc-title">LEMBAR HASIL PENILAIAN KINERJA</div>
            <div class="doc-sub">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<div class="divider-top"></div>

<table class="w100">
    <tr>
        <td style="width:50%; vertical-align:top;">
            <table class="info-tbl">
                <tr>
                    <td class="info-key">Nama</td><td class="info-sep">:</td>
                    <td class="info-val">{{ $kpiResult->employee->full_name }}</td>
                </tr>
                <tr>
                    <td class="info-key">Kode karyawan</td><td class="info-sep">:</td>
                    <td class="info-val">{{ $kpiResult->employee->employee_code ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="info-key">Jabatan</td><td class="info-sep">:</td>
                    <td class="info-val">{{ $kpiResult->employee->position ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="info-key">Departemen</td><td class="info-sep">:</td>
                    <td class="info-val">{{ $kpiResult->employee->department?->name ?? '—' }}</td>
                </tr>
            </table>
        </td>
        <td class="info-gap"></td>
        <td style="width:48%; vertical-align:top;">
            <table class="info-tbl">
                <tr>
                    <td class="info-key">Periode</td><td class="info-sep">:</td>
                    <td class="info-val">{{ $kpiResult->period->name }}</td>
                </tr>
                <tr>
                    <td class="info-key">Rentang</td><td class="info-sep">:</td>
                    <td class="info-val">
                        {{ $kpiResult->period->start_date?->format('d/m/Y') ?? '—' }} –
                        {{ $kpiResult->period->end_date?->format('d/m/Y') ?? '—' }}
                    </td>
                </tr>
                <tr>
                    <td class="info-key">Level</td><td class="info-sep">:</td>
                    <td class="info-val">
                        {{ $kpiResult->levelSnapshot?->code ?? '—' }}
                        @if($kpiResult->levelSnapshot?->name) — {{ $kpiResult->levelSnapshot->name }} @endif
                    </td>
                </tr>
                <tr>
                    <td class="info-key">Status hasil</td><td class="info-sep">:</td>
                    <td class="info-val">
                        {{ $kpiResult->calibrated_score !== null ? 'Setelah kalibrasi' : 'Hasil perhitungan' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($kpiResult->period->is_trial)
<div class="trial">
    PERIODE UJI COBA — hasil ini tidak boleh dipakai sebagai dasar bonus, promosi, maupun sanksi.
</div>
@endif

<table class="summary">
    <tr>
        <td style="width:34%;">
            <div class="score-cap">NILAI AKHIR</div>
            <div class="score-big">{{ $score($kpiResult->effectiveScore()) }}</div>
            <div class="muted" style="font-size:8px; margin-top:3px;">skala 1,00 – 5,00</div>
        </td>
        <td style="width:44%;">
            <div class="score-cap">PREDIKAT</div>
            <div class="bold" style="font-size:11px; margin-top:3px;">{{ $kpiResult->gradeLabel() }}</div>
            @if($kpiResult->calibrated_score !== null)
            <div class="muted" style="font-size:8px; margin-top:3px;">
                Nilai perhitungan {{ $score($kpiResult->final_score) }}, disesuaikan pada tahap kalibrasi.
            </div>
            @endif
        </td>
        <td style="width:22%;">
            <div class="grade-box">{{ $kpiResult->grade ?? '—' }}</div>
        </td>
    </tr>
</table>

<div class="section-title">Skor per Kategori</div>
<table class="grid-tbl">
    <thead>
        <tr>
            <th>Kategori</th>
            <th style="width:70px;" class="num">Bobot</th>
            <th style="width:70px;" class="num">Skor</th>
            <th style="width:90px;" class="num">Kontribusi</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories as $code => $categoryName)
        @php
            $categoryScore = (float) $kpiResult->{$categoryFields[$code]};
            $categoryWeight = (float) ($levelWeights[$code] ?? 0);
        @endphp
        <tr>
            <td><span class="code">{{ $code }}</span> {{ $categoryName }}</td>
            <td class="num">{{ $weight($categoryWeight) }}%</td>
            <td class="num">{{ $score($categoryScore) }}</td>
            <td class="num">{{ $score($categoryScore * $categoryWeight / 100) }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td>Nilai akhir</td>
            <td class="num">{{ $weight(array_sum($levelWeights)) }}%</td>
            <td class="num">—</td>
            <td class="num">{{ $score($kpiResult->effectiveScore()) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Skor per Indikator</div>
<table class="grid-tbl">
    <thead>
        <tr>
            <th style="width:62px;">Kode</th>
            <th>Indikator</th>
            <th style="width:60px;" class="num">Bobot</th>
            <th style="width:52px;" class="num">Skor</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories as $code => $categoryName)
        @php $catRows = $rows[$code] ?? collect(); @endphp
        @if($catRows->isNotEmpty())
        <tr class="cat-row">
            <td colspan="4">
                {{ $categoryName }} — bobot kategori {{ $weight($levelWeights[$code] ?? 0) }}%
            </td>
        </tr>
        @foreach($catRows as $row)
        <tr>
            <td class="code">{{ $row['snapshot']->code }}</td>
            <td>
                {{ $row['snapshot']->name }}
                @foreach($row['evidence'] as $evidence)
                @if(trim((string) $evidence['text']) !== '')
                <div class="evidence">
                    <span class="evidence-src">{{ $evidence['assessor'] ?? $evidence['role'] }} (skor {{ $evidence['score'] }}):</span>
                    {{ $evidence['text'] }}
                </div>
                @endif
                @endforeach
            </td>
            <td class="num">{{ $weight($row['snapshot']->weight) }}%</td>
            <td class="num bold">{{ $score($row['score']) }}</td>
        </tr>
        @endforeach
        @endif
        @endforeach
        @if($rows->isEmpty())
        <tr>
            <td colspan="4" class="muted center">Rincian per indikator tidak tersedia.</td>
        </tr>
        @endif
    </tbody>
</table>

<div class="sign-wrap">
    <table class="sign-tbl">
        <tr>
            <td>
                <div class="sign-role">Karyawan yang dinilai</div>
                <div class="sign-note">Tanda tangan menyatakan hasil sudah dibaca</div>
                <div class="sign-line">{{ $kpiResult->employee->full_name }}</div>
            </td>
            <td>
                <div class="sign-role">Atasan langsung</div>
                <div class="sign-note">Penilai utama</div>
                <div class="sign-line">{{ $primaryAssessor?->full_name ?? '.....................................' }}</div>
            </td>
            <td>
                <div class="sign-role">HRD</div>
                <div class="sign-note">Mengetahui</div>
                <div class="sign-line">.....................................</div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    Dokumen ini bersifat rahasia dan hanya diperuntukkan bagi karyawan yang bersangkutan beserta atasannya.<br>
    Identitas penilai silang tidak dicantumkan. Hasil hanya berlaku untuk periode yang dinilai.
</div>

</body>
</html>
