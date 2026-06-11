@extends('admin.layouts.app')
@section('title', 'Payslip - ' . $detail->employee->full_name)

@section('content')
@php
    $emp = $detail->employee;
    $run = $detail->payrollRun;
    $payroll = $emp->activePayroll;

    $periodDate = \Carbon\Carbon::parse($run->period . '-01');
    $periodStart = $periodDate->copy()->startOfMonth();
    $periodEnd = $periodDate->copy()->endOfMonth();

    $earnings = [];
    $deductions = [];
    $infoComps = [];

    $comps = is_array($detail->components) ? $detail->components : json_decode($detail->components, true) ?? [];
    foreach ($comps as $c) {
        if (($c['type'] ?? '') === 'earning') {
            $earnings[] = $c;
        } elseif (($c['type'] ?? '') === 'deduction') {
            $deductions[] = $c;
        } elseif (($c['type'] ?? '') === 'info' && !str_contains($c['name'] ?? '', 'BPJS (Perusahaan)')) {
            $infoComps[] = $c;
        }
    }

    $loanLines = fn (?array $component) => $component
        ? \App\Support\PayslipLoanSummary::detailLinesForComponent($component)
        : [];
@endphp

<div class="mb-5 flex items-center justify-between">
    <a href="{{ route('admin.payslips.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke Daftar
    </a>
    <a href="{{ route('admin.payslips.download', $detail->id) }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
        <span class="material-symbols-outlined text-[15px]">download</span> Download PDF
    </a>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm max-w-4xl mx-auto overflow-hidden">
    <div class="px-8 pt-7 pb-4">
        <div class="flex items-start justify-between">
            <div>
                @if($company && $company->logo)
                    <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" class="h-10 w-auto object-contain mb-1">
                @else
                    <div class="text-[18px] font-black text-gray-800 tracking-tight">{{ strtoupper(substr($company->name ?? 'CO', 0, 3)) }}</div>
                @endif
            </div>
            <div class="text-[11px] font-bold text-red-600 tracking-wide">*CONFIDENTIAL</div>
        </div>
    </div>

    <div class="px-8 pb-4 border-t-2 border-gray-900 pt-4">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-[17px] font-bold text-gray-900">{{ $company->name ?? 'PT. Perusahaan' }}</div>
                <div class="text-[11px] text-gray-500 mt-0.5 max-w-xs">{{ $company->address ?? '' }}</div>
            </div>
            <div class="text-[16px] font-bold tracking-[3px] text-gray-900">PAYSLIP</div>
        </div>
    </div>

    <div class="px-8 py-4 bg-gray-50/50 border-t border-gray-100">
        <div class="grid grid-cols-2 gap-x-8 gap-y-1.5">
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">Payroll cut off</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ $periodStart->format('d') }} - {{ $periodEnd->format('d M Y') }}</span>
            </div>
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">PTKP</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ $payroll->ptkp_status ?? 'TK/0' }}</span>
            </div>
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">ID / Name</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ $emp->employee_code }} / {{ strtoupper($emp->full_name) }}</span>
            </div>
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">NPWP</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ $payroll->npwp ?? '-' }}</span>
            </div>
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">Job position</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ strtoupper($emp->position ?? '-') }}</span>
            </div>
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">BPJS Kesehatan</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ $payroll->bpjs_kesehatan ?? '-' }}</span>
            </div>
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">Organization</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ strtoupper($emp->department->name ?? '-') }}</span>
            </div>
            <div class="flex gap-2 text-[12px]">
                <span class="text-gray-500 w-36 shrink-0">BPJS Ketenagakerjaan</span>
                <span class="text-gray-400">:</span>
                <span class="font-semibold text-gray-800">{{ $payroll->bpjs_ketenagakerjaan ?? '-' }}</span>
            </div>
        </div>
    </div>

    <div class="px-8 pt-4">
        <table class="w-full text-[12px] border border-gray-300 border-collapse">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-4 py-2.5 text-left font-bold text-gray-800 border-b border-gray-300 w-[42%]">Earnings</th>
                    <th class="px-4 py-2.5 text-right font-bold text-gray-800 border-b border-gray-300 w-[12%]"></th>
                    <th class="px-4 py-2.5 text-left font-bold text-gray-800 border-b border-l border-gray-300 w-[34%]">Deductions</th>
                    <th class="px-4 py-2.5 text-right font-bold text-gray-800 border-b border-gray-300 w-[12%]"></th>
                </tr>
            </thead>
            <tbody>
                @php $firstDeduction = $deductions[0] ?? null; @endphp
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">Basic Salary</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format($detail->basic_salary, 0, ',', '.') }}</td>
                    <td class="px-4 py-2 border-l border-gray-200">
                        {{ $firstDeduction['name'] ?? '' }}
                        @foreach($loanLines($firstDeduction) as $line)
                            <div class="mt-1 text-[10.5px] leading-snug text-gray-500">{{ $line }}</div>
                        @endforeach
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ isset($deductions[0]) ? number_format($deductions[0]['amount'], 0, ',', '.') : '' }}</td>
                </tr>
                @foreach($earnings as $i => $e)
                @php $pairedDeduction = $deductions[$i + 1] ?? null; @endphp
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $e['name'] }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format($e['amount'], 0, ',', '.') }}</td>
                    <td class="px-4 py-2 border-l border-gray-200">
                        {{ $pairedDeduction['name'] ?? '' }}
                        @foreach($loanLines($pairedDeduction) as $line)
                            <div class="mt-1 text-[10.5px] leading-snug text-gray-500">{{ $line }}</div>
                        @endforeach
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ isset($deductions[$i + 1]) ? number_format($deductions[$i + 1]['amount'], 0, ',', '.') : '' }}</td>
                </tr>
                @endforeach
                @php $startIdx = count($earnings) + 1; @endphp
                @for($i = $startIdx; $i < count($deductions); $i++)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2"></td>
                    <td class="px-4 py-2"></td>
                    <td class="px-4 py-2 border-l border-gray-200">
                        {{ $deductions[$i]['name'] ?? '' }}
                        @foreach($loanLines($deductions[$i] ?? null) as $line)
                            <div class="mt-1 text-[10.5px] leading-snug text-gray-500">{{ $line }}</div>
                        @endforeach
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format($deductions[$i]['amount'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr class="bg-gray-50 border-t-2 border-gray-300 text-[12px] font-bold">
                    <td class="px-4 py-3">Total earnings</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($detail->total_earning, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-l border-gray-200">Total deductions</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($detail->total_deduction, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="border border-t-0 border-gray-300 px-4 py-4 flex items-center justify-between">
            <span class="text-[16px] font-bold text-gray-900">Take Home Pay</span>
            <span class="text-[16px] font-bold text-gray-900">Rp{{ number_format($detail->net_salary, 0, ',', '.') }}</span>
        </div>
    </div>

    @if(!empty($bpjsData['items']))
    <div class="px-8 py-5">
        <div class="text-[12px] font-bold text-gray-800 mb-3">Benefits* <span class="font-normal text-gray-400 text-[10px]">(ditanggung perusahaan)</span></div>
        <div class="space-y-1.5 max-w-sm">
            @foreach($bpjsData['items'] as $b)
            <div class="flex justify-between text-[12px] {{ $b['is_basis'] ? 'text-gray-500' : 'text-gray-700' }}">
                <span>{{ $b['label'] }}</span>
                <span class="tabular-nums font-{{ $b['is_basis'] ? 'normal' : 'medium' }}">{{ number_format($b['amount'], 0, ',', '.') }}</span>
            </div>
            @endforeach
            <div class="flex justify-between text-[12px] font-bold pt-1.5 border-t border-gray-900">
                <span>Total benefits</span>
                <span class="tabular-nums">{{ number_format($bpjsData['total'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
    @endif

    @if(count($infoComps) > 0)
    <div class="px-8 pb-5">
        <div class="text-[11px] text-gray-400 space-y-1">
            @foreach($infoComps as $ic)
            <div>{{ $ic['name'] }}: {{ $ic['detail'] ?? '' }}</div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="px-8 py-4 border-t border-gray-100 bg-gray-50/50 text-center">
        <p class="text-[10px] text-gray-400">Dokumen ini bersifat rahasia, hanya untuk penerima yang bersangkutan</p>
        <p class="text-[10px] text-gray-400 mt-0.5">{{ $company->name ?? '' }} &bull; {{ $company->phone ?? '' }} &bull; {{ $company->email ?? '' }}</p>
    </div>
</div>

@endsection
