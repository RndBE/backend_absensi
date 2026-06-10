<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Potong {{ $certificate->certificate_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #d1d5db; padding: 10px; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
    </style>
</head>
<body>
@php
    $details = $certificate->monthly_details ?? [];
    $employeeInfo = $details['employee'] ?? [];
    $taxInfo = $details['tax'] ?? [];
    $months = $details['months'] ?? collect($details)
        ->filter(fn($value, $key) => is_string($key) && preg_match('/^\d{4}-\d{2}$/', $key))
        ->all();
@endphp

<div class="title">Bukti Potong PPh 21 1721-A1</div>
<div class="muted">Nomor: {{ $certificate->certificate_number }}</div>

<div class="box">
    <div class="bold">Pemotong</div>
    <div>{{ $company->name ?? '-' }}</div>
    <div>NPWP: {{ $company->npwp ?? '-' }}</div>
    <div>{{ $company->address ?? '-' }}</div>
</div>

<div class="box">
    <div class="bold">Penerima Penghasilan</div>
    <div>Nama: {{ $certificate->employee->full_name }}</div>
    <div>NIK: {{ $employeeInfo['nik'] ?? '-' }}</div>
    <div>NPWP: {{ $employeeInfo['npwp'] ?? '-' }}</div>
    <div>PTKP: {{ $employeeInfo['ptkp'] ?? '-' }}</div>
    <div>Jabatan: {{ $employeeInfo['position'] ?? '-' }}</div>
    <div>Kode Objek Pajak: {{ $taxInfo['object_code'] ?? '21-100-01' }}</div>
    <div>Tahun Pajak: {{ $certificate->tax_year }}</div>
</div>

<table>
    <thead>
        <tr>
            <th>Periode</th>
            <th class="right">Bruto</th>
            <th class="right">BPJS Karyawan</th>
            <th class="right">PPh 21</th>
            <th class="right">Netto</th>
        </tr>
    </thead>
    <tbody>
        @foreach($months as $period => $month)
        <tr>
            <td>{{ $period }}</td>
            <td class="right">Rp {{ number_format($month['gross'] ?? 0, 0, ',', '.') }}</td>
            <td class="right">Rp {{ number_format($month['bpjs'] ?? 0, 0, ',', '.') }}</td>
            <td class="right">Rp {{ number_format($month['tax'] ?? 0, 0, ',', '.') }}</td>
            <td class="right">Rp {{ number_format($month['net'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td class="bold">Total</td>
            <td class="right bold">Rp {{ number_format($certificate->gross_annual, 0, ',', '.') }}</td>
            <td class="right bold">Rp {{ number_format($certificate->bpjs_annual, 0, ',', '.') }}</td>
            <td class="right bold">Rp {{ number_format($certificate->tax_annual, 0, ',', '.') }}</td>
            <td class="right bold">Rp {{ number_format($certificate->nett_annual, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<p class="muted">Dokumen ini digenerate dari data payroll final/published/locked di sistem.</p>
</body>
</html>
