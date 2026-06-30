
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

    $benefitData = \App\Support\PayslipBenefits::from($bpjsData ?? [], $comps);
    $earningRows = count($earnings) + 1;
    $deductionRows = max(count($deductions), 1);
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

<table class="split-panels">
    <tr>
        <td class="panel-left">
            <table class="pay-panel">
                <thead>
                    <tr>
                        <th>Pemasukan</th>
                        <th class="num">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="num">{{ number_format($detail->basic_salary, 0, ',', '.') }}</td>
                    </tr>
                    @foreach($earnings as $earning)
                        <tr>
                            <td>{{ $earning['name'] }}</td>
                            <td class="num">{{ number_format($earning['amount'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    @for($i = 0; $i < $maxRows - $earningRows; $i++)
                        <tr class="blank-row">
                            <td>&nbsp;</td>
                            <td class="num">&nbsp;</td>
                        </tr>
                    @endfor
                    <tr class="panel-total">
                        <td>Total pemasukan</td>
                        <td class="num">{{ number_format($detail->total_earning, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
        <td class="panel-gap"></td>
        <td class="panel-right">
            <table class="pay-panel">
                <thead>
                    <tr>
                        <th>Pengeluaran</th>
                        <th class="num">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deductions as $deduction)
                        @php
                            $dLoanLines = \App\Support\PayslipLoanSummary::detailLinesForComponent($deduction);
                        @endphp
                        <tr>
                            <td>
                                {{ $deduction['name'] }}
                                @foreach($dLoanLines as $line)
                                    <div class="loan-detail">{{ $line }}</div>
                                @endforeach
                            </td>
                            <td class="num">{{ number_format($deduction['amount'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="muted">Tidak ada pengeluaran</td>
                        </tr>
                    @endforelse
                    @for($i = 0; $i < $maxRows - $deductionRows; $i++)
                        <tr class="blank-row">
                            <td>&nbsp;</td>
                            <td class="num">&nbsp;</td>
                        </tr>
                    @endfor
                    <tr class="panel-total">
                        <td>Total pengeluaran</td>
                        <td class="num">{{ number_format($detail->total_deduction, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

<table class="w100 thp-tbl">
    <tr>
        <td style="width:50%;">Take Home Pay</td>
        <td class="thp-right" style="width:50%;">Rp{{ number_format($detail->net_salary, 0, ',', '.') }}</td>
    </tr>
</table>

@if(empty($hideBenefits) && !empty($benefitData['items']))
<div class="benefits-section">
    <div class="benefits-title">Benefits* <span style="font-weight:normal; color:#9ca3af; font-size:8.5px;">(ditanggung perusahaan)</span></div>
    <table class="ben-tbl">
        @foreach($benefitData['items'] as $b)
        <tr class="{{ $b['is_basis'] ? 'ben-muted' : '' }}">
            <td class="ben-lbl {{ $b['is_basis'] ? 'ben-muted' : '' }}">{{ $b['label'] }}</td>
            <td class="ben-amt" style="font-weight:{{ $b['is_basis'] ? 'normal' : '600' }};">{{ number_format($b['amount'], 0, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr class="ben-total">
            <td class="ben-lbl">Total benefits</td>
            <td class="ben-amt">{{ number_format($benefitData['total'], 0, ',', '.') }}</td>
        </tr>
    </table>
    @if(!empty($benefitData['notes']))
    <div class="benefit-notes">
        @foreach($benefitData['notes'] as $note)
            <div>{{ $note['label'] }}: {{ $note['detail'] }}</div>
        @endforeach
    </div>
    @endif
</div>
@endif

<div class="footer">
    Dokumen ini bersifat rahasia, hanya untuk penerima yang bersangkutan<br>
    {{ $company->name ?? '' }}
    @if($company->phone ?? null) &bull; {{ $company->phone }} @endif
    @if($company->email ?? null) &bull; {{ $company->email }} @endif
</div>

