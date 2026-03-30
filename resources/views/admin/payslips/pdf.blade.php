<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; padding: 30px; }

        .header { text-align: center; margin-bottom: 25px; border-bottom: 3px double #1a1a2e; padding-bottom: 15px; }
        .company-name { font-size: 18px; font-weight: bold; color: #1a1a2e; text-transform: uppercase; letter-spacing: 1px; }
        .company-sub { font-size: 10px; color: #666; margin-top: 2px; }
        .doc-title { font-size: 14px; font-weight: bold; color: #1a1a2e; margin-top: 10px; letter-spacing: 2px; text-transform: uppercase; }

        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 3px 0; vertical-align: top; }
        .info-label { color: #888; font-size: 11px; width: 130px; }
        .info-value { font-weight: 600; font-size: 12px; }

        .period-badge { display: inline-block; background: #1a1a2e; color: white; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; }

        .section-title { font-size: 12px; font-weight: bold; color: #1a1a2e; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e0e0e0; }

        .comp-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .comp-table th { background: #f5f5f5; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; border-bottom: 2px solid #ddd; }
        .comp-table td { padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 11px; }
        .comp-table .amount { text-align: right; font-weight: 600; }
        .comp-table .earning { color: #0d9488; }
        .comp-table .deduction { color: #dc2626; }

        .summary-box { background: #1a1a2e; color: white; padding: 15px 20px; border-radius: 6px; margin-top: 20px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .summary-table { width: 100%; }
        .summary-table td { padding: 4px 0; }
        .summary-table .label { color: #aaa; font-size: 11px; }
        .summary-table .value { text-align: right; font-size: 12px; font-weight: 600; }
        .summary-table .net { font-size: 16px; font-weight: bold; color: #4ade80; }
        .summary-table .net-label { font-size: 13px; font-weight: bold; color: white; }
        .summary-table hr { border: none; border-top: 1px solid #444; margin: 5px 0; }

        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #999; }
        .confidential { font-size: 9px; color: #ccc; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">PT ARTA TEKNOLOGI COMUNINDO</div>
        <div class="company-sub">Jl. Indonesia</div>
        <div class="doc-title">Slip Gaji Karyawan</div>
    </div>

    @php
        $emp = $detail->employee;
        $run = $detail->payrollRun;
        $periodLabel = \Carbon\Carbon::parse($run->period . '-01')->translatedFormat('F Y');
        $earnings = [];
        $deductions = [];
        if ($detail->components) {
            foreach ($detail->components as $c) {
                if ($c['type'] === 'earning') $earnings[] = $c;
                else $deductions[] = $c;
            }
        }
    @endphp

    <table class="info-table">
        <tr>
            <td class="info-label">Nama Karyawan</td>
            <td class="info-value">: {{ $emp->full_name }}</td>
            <td class="info-label">Periode</td>
            <td class="info-value">: <span class="period-badge">{{ $periodLabel }}</span></td>
        </tr>
        <tr>
            <td class="info-label">ID Karyawan</td>
            <td class="info-value">: {{ $emp->employee_code }}</td>
            <td class="info-label">Departemen</td>
            <td class="info-value">: {{ $emp->department->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Jabatan</td>
            <td class="info-value">: {{ $emp->position ?? '-' }}</td>
            <td class="info-label"></td>
            <td class="info-value"></td>
        </tr>
    </table>

    {{-- Basic Salary --}}
    <div class="section-title">Gaji Pokok</div>
    <table class="comp-table">
        <tr>
            <td>Gaji Pokok</td>
            <td class="amount earning">Rp {{ number_format($detail->basic_salary, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Earnings --}}
    @if(count($earnings) > 0)
    <div class="section-title">Tunjangan / Pendapatan Lain</div>
    <table class="comp-table">
        <thead>
            <tr>
                <th>Komponen</th>
                <th style="text-align: right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($earnings as $e)
            <tr>
                <td>{{ $e['name'] }}</td>
                <td class="amount earning">Rp {{ number_format($e['amount'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Deductions --}}
    @if(count($deductions) > 0)
    <div class="section-title">Potongan</div>
    <table class="comp-table">
        <thead>
            <tr>
                <th>Komponen</th>
                <th style="text-align: right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deductions as $d)
            <tr>
                <td>{{ $d['name'] }}</td>
                <td class="amount deduction">Rp {{ number_format($d['amount'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Summary --}}
    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td class="label">Total Pendapatan</td>
                <td class="value">Rp {{ number_format($detail->total_earning, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Total Potongan</td>
                <td class="value" style="color: #f87171;">Rp {{ number_format($detail->total_deduction, 0, ',', '.') }}</td>
            </tr>
            <tr><td colspan="2"><hr></td></tr>
            <tr>
                <td class="net-label">Gaji Bersih (Take Home Pay)</td>
                <td class="net">Rp {{ number_format($detail->net_salary, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Diterbitkan oleh sistem payroll PT Arta Teknologi Comunindo</p>
        <p class="confidential">Dokumen ini bersifat rahasia — hanya untuk penerima yang bersangkutan</p>
    </div>
</body>
</html>
