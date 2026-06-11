<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Payslip</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9.5px;
        color: #1a1a1a;
        background: #fff;
        padding: 28px 30px;
    }

    .w100 { width: 100%; }
    .bold { font-weight: bold; }
    .right { text-align: right; }
    .muted { color: #6b7280; }
    .red { color: #dc2626; font-weight: bold; font-size: 9px; letter-spacing: 0.5px; }

    .company-name { font-size: 16px; font-weight: bold; color: #111; }
    .company-addr { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .doc-title { font-size: 15px; font-weight: bold; letter-spacing: 3px; color: #111; }

    .divider-top { border-top: 2px solid #111; padding-top: 10px; margin-bottom: 12px; }

    .info-tbl td { padding: 2px 0; font-size: 9.5px; vertical-align: top; }
    .info-key { color: #6b7280; width: 130px; }
    .info-sep { width: 10px; color: #6b7280; }
    .info-val { font-weight: 600; }
    .info-gap { width: 30px; }

    .main-tbl { border-collapse: collapse; margin-top: 14px; }
    .main-tbl th {
        background: #f3f4f6;
        padding: 7px 10px;
        text-align: left;
        font-size: 9.5px;
        font-weight: bold;
        border: 1px solid #d1d5db;
    }
    .main-tbl td {
        padding: 4.5px 10px;
        font-size: 9.5px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: top;
    }
    .main-tbl td.divider { border-left: 1px solid #d1d5db; }
    .main-tbl .num { text-align: right; white-space: nowrap; }
    .loan-detail { margin-top: 2px; font-size: 8px; line-height: 1.25; color: #6b7280; }
    .main-tbl .total-row td {
        border-top: 1.5px solid #d1d5db;
        border-bottom: none;
        background: #f9fafb;
        font-weight: bold;
        font-size: 9.5px;
    }

    .thp-tbl { border-collapse: collapse; margin-top: 0; }
    .thp-tbl td {
        padding: 9px 10px;
        border: 1px solid #d1d5db;
        border-top: none;
        font-size: 13px;
        font-weight: bold;
    }
    .thp-tbl td.thp-right { text-align: right; }

    .benefits-section { margin-top: 18px; }
    .benefits-title { font-size: 10px; font-weight: bold; margin-bottom: 7px; }
    .ben-tbl { border-collapse: collapse; }
    .ben-tbl td { padding: 2.5px 0; font-size: 9.5px; }
    .ben-tbl .ben-lbl { width: 220px; }
    .ben-tbl .ben-amt { text-align: right; width: 90px; white-space: nowrap; }
    .ben-tbl .ben-muted { color: #6b7280; }
    .ben-total td {
        border-top: 1px solid #111;
        padding-top: 5px;
        font-weight: bold;
    }

    .footer { margin-top: 24px; text-align: center; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>

@php
    $emp = $detail->employee;
    $run = $detail->payrollRun;
    $payroll = $emp->activePayroll;

    $periodDate = \Carbon\Carbon::parse($run->period . '-01');
    $periodStart = $periodDate->copy()->startOfMonth()->format('d');
    $periodEnd = $periodDate->copy()->endOfMonth()->format('d M Y');

    $earnings = [];
    $deductions = [];

    $comps = is_array($detail->components)
        ? $detail->components
        : (json_decode($detail->components, true) ?? []);

    foreach ($comps as $c) {
        if (($c['type'] ?? '') === 'earning') {
            $earnings[] = $c;
        } elseif (($c['type'] ?? '') === 'deduction') {
            $deductions[] = $c;
        }
    }

    $earningRows = count($earnings) + 1;
    $deductionRows = count($deductions);
    $maxRows = max($earningRows, $deductionRows);
@endphp

<table class="w100" style="margin-bottom:14px;">
    <tr>
        <td style="width:50%; vertical-align:bottom;">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo" style="height:40px; width:auto;">
            @else
                <span style="font-size:20px; font-weight:900; letter-spacing:-1px;">{{ strtoupper(substr($company->name ?? 'CO', 0, 3)) }}</span>
            @endif
        </td>
        <td style="text-align:right; vertical-align:top;">
            <span class="red">*CONFIDENTIAL</span>
        </td>
    </tr>
</table>

<div class="divider-top">
    <table class="w100">
        <tr>
            <td>
                <div class="company-name">{{ $company->name ?? 'PT. Perusahaan' }}</div>
                <div class="company-addr">{{ $company->address ?? '' }}</div>
            </td>
            <td style="text-align:right; vertical-align:top;">
                <span class="doc-title">PAYSLIP</span>
            </td>
        </tr>
    </table>
</div>

<table class="w100 info-tbl" style="margin-top:10px; margin-bottom:14px;">
    <tr>
        <td class="info-key">Payroll cut off</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ $periodStart }} - {{ $periodEnd }}</td>
        <td class="info-gap"></td>
        <td class="info-key">PTKP</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ $payroll->ptkp_status ?? 'TK/0' }}</td>
    </tr>
    <tr>
        <td class="info-key">ID / Name</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ $emp->employee_code }} / {{ strtoupper($emp->full_name) }}</td>
        <td class="info-gap"></td>
        <td class="info-key">NPWP</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ $payroll->npwp ?? '-' }}</td>
    </tr>
    <tr>
        <td class="info-key">Job position</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ strtoupper($emp->position ?? '-') }}</td>
        <td class="info-gap"></td>
        <td class="info-key">BPJS Kesehatan</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ $payroll->bpjs_kesehatan ?? '-' }}</td>
    </tr>
    <tr>
        <td class="info-key">Organization</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ strtoupper($emp->department->name ?? '-') }}</td>
        <td class="info-gap"></td>
        <td class="info-key">BPJS Ketenagakerjaan</td>
        <td class="info-sep">:</td>
        <td class="info-val">{{ $payroll->bpjs_ketenagakerjaan ?? '-' }}</td>
    </tr>
</table>

<table class="w100 main-tbl" style="border:1px solid #d1d5db;">
    <thead>
        <tr>
            <th style="width:40%; border-right:none;">Earnings</th>
            <th style="width:10%; text-align:right; border-left:none; border-right:none;"></th>
            <th style="width:40%; border-left:1px solid #d1d5db; border-right:none;">Deductions</th>
            <th style="width:10%; text-align:right; border-left:none;"></th>
        </tr>
    </thead>
    <tbody>
        @for($i = 0; $i < $maxRows; $i++)
        @php
            if ($i === 0) {
                $eName = 'Basic Salary';
                $eAmt = $detail->basic_salary;
                $hasE = true;
            } elseif (isset($earnings[$i - 1])) {
                $eName = $earnings[$i - 1]['name'];
                $eAmt = $earnings[$i - 1]['amount'];
                $hasE = true;
            } else {
                $eName = '';
                $eAmt = null;
                $hasE = false;
            }

            if (isset($deductions[$i])) {
                $dName = $deductions[$i]['name'];
                $dAmt = $deductions[$i]['amount'];
                $hasD = true;
                $dLoanLines = \App\Support\PayslipLoanSummary::detailLinesForComponent($deductions[$i]);
            } else {
                $dName = '';
                $dAmt = null;
                $hasD = false;
                $dLoanLines = [];
            }
        @endphp
        <tr>
            <td>{{ $eName }}</td>
            <td class="num">{{ $hasE && $eAmt !== null ? number_format($eAmt, 0, ',', '.') : '' }}</td>
            <td class="divider">
                {{ $dName }}
                @foreach($dLoanLines as $line)
                    <div class="loan-detail">{{ $line }}</div>
                @endforeach
            </td>
            <td class="num">{{ $hasD && $dAmt !== null ? number_format($dAmt, 0, ',', '.') : '' }}</td>
        </tr>
        @endfor
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td>Total earnings</td>
            <td class="num">{{ number_format($detail->total_earning, 0, ',', '.') }}</td>
            <td class="divider">Total deductions</td>
            <td class="num">{{ number_format($detail->total_deduction, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<table class="w100 thp-tbl">
    <tr>
        <td style="width:50%;">Take Home Pay</td>
        <td class="thp-right" style="width:50%;">Rp{{ number_format($detail->net_salary, 0, ',', '.') }}</td>
    </tr>
</table>

@if(!empty($bpjsData['items']))
<div class="benefits-section">
    <div class="benefits-title">Benefits* <span style="font-weight:normal; color:#9ca3af; font-size:8.5px;">(ditanggung perusahaan)</span></div>
    <table class="ben-tbl">
        @foreach($bpjsData['items'] as $b)
        <tr class="{{ $b['is_basis'] ? 'ben-muted' : '' }}">
            <td class="ben-lbl {{ $b['is_basis'] ? 'ben-muted' : '' }}">{{ $b['label'] }}</td>
            <td class="ben-amt" style="font-weight:{{ $b['is_basis'] ? 'normal' : '600' }};">{{ number_format($b['amount'], 0, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr class="ben-total">
            <td class="ben-lbl">Total benefits</td>
            <td class="ben-amt">{{ number_format($bpjsData['total'], 0, ',', '.') }}</td>
        </tr>
    </table>
</div>
@endif

<div class="footer">
    Dokumen ini bersifat rahasia, hanya untuk penerima yang bersangkutan<br>
    {{ $company->name ?? '' }}
    @if($company->phone ?? null) &bull; {{ $company->phone }} @endif
    @if($company->email ?? null) &bull; {{ $company->email }} @endif
</div>

</body>
</html>
