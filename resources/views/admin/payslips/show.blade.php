@extends('admin.layouts.app')
@section('title', 'Detail Payslip — ' . $detail->employee->full_name)

@section('content')
<div class="mb-4 flex items-center justify-between">
    <a href="{{ route('admin.payslips.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
    <a href="{{ route('admin.payslips.download', $detail->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
        <span class="material-symbols-outlined text-[16px]">download</span> Download PDF
    </a>
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

{{-- Header --}}
<div class="bg-gradient-to-br from-[#1a1a2e] to-[#16213e] rounded-2xl p-6 mb-5 text-white">
    <div class="text-center mb-2">
        <div class="text-[10px] font-bold uppercase tracking-[3px] text-white/40">Slip Gaji</div>
        <div class="text-[18px] font-bold mt-1">PT ARTA TEKNOLOGI COMUNINDO</div>
    </div>
    <div class="flex justify-between items-end mt-5">
        <div>
            <div class="text-[16px] font-bold">{{ $emp->full_name }}</div>
            <div class="text-[12px] text-white/60">{{ $emp->employee_code }} · {{ $emp->department->name ?? '-' }} · {{ $emp->position ?? '-' }}</div>
        </div>
        <div class="text-right">
            <span class="inline-flex items-center px-3 py-1 rounded-lg text-[12px] font-bold bg-white/15 backdrop-blur">{{ $periodLabel }}</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Total Pendapatan</div>
        <div class="text-[20px] font-bold text-emerald-700">Rp {{ number_format($detail->total_earning, 0, ',', '.') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-red-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-red-600 uppercase tracking-wider mb-1">Total Potongan</div>
        <div class="text-[20px] font-bold text-red-600">Rp {{ number_format($detail->total_deduction, 0, ',', '.') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-indigo-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider mb-1">Gaji Bersih</div>
        <div class="text-[20px] font-bold text-indigo-700">Rp {{ number_format($detail->net_salary, 0, ',', '.') }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- Earning --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[14px] font-bold text-emerald-700"><span class="material-symbols-outlined text-[16px] align-text-bottom">trending_up</span> Pendapatan</h3>
        </div>
        <div class="p-5 space-y-2">
            <div class="flex justify-between items-center p-3 rounded-lg bg-emerald-50/50 border border-emerald-100">
                <span class="text-[13px] font-semibold text-gray-800">Gaji Pokok</span>
                <span class="text-[13px] font-bold text-emerald-700">Rp {{ number_format($detail->basic_salary, 0, ',', '.') }}</span>
            </div>
            @foreach($earnings as $e)
            <div class="flex justify-between items-center p-3 rounded-lg bg-emerald-50/50 border border-emerald-100">
                <span class="text-[13px] text-gray-700">{{ $e['name'] }}</span>
                <span class="text-[13px] font-semibold text-emerald-700">Rp {{ number_format($e['amount'], 0, ',', '.') }}</span>
            </div>
            @endforeach
            @if(count($earnings) === 0)
            <div class="text-center py-4 text-gray-400 text-[12px]">Tidak ada tunjangan lain</div>
            @endif
        </div>
    </div>

    {{-- Deduction --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[14px] font-bold text-red-600"><span class="material-symbols-outlined text-[16px] align-text-bottom">trending_down</span> Potongan</h3>
        </div>
        <div class="p-5 space-y-2">
            @foreach($deductions as $d)
            <div class="flex justify-between items-center p-3 rounded-lg bg-red-50/50 border border-red-100">
                <span class="text-[13px] text-gray-700">{{ $d['name'] }}</span>
                <span class="text-[13px] font-semibold text-red-600">Rp {{ number_format($d['amount'], 0, ',', '.') }}</span>
            </div>
            @endforeach
            @if(count($deductions) === 0)
            <div class="text-center py-4 text-gray-400 text-[12px]">Tidak ada potongan</div>
            @endif
        </div>
    </div>
</div>
@endsection
