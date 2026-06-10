@extends('admin.layouts.app')
@section('title', 'Detail Bukti Potong')

@section('content')
@php
    $details = $certificate->monthly_details ?? [];
    $employeeInfo = $details['employee'] ?? [];
    $taxInfo = $details['tax'] ?? [];
    $months = $details['months'] ?? collect($details)
        ->filter(fn($value, $key) => is_string($key) && preg_match('/^\d{4}-\d{2}$/', $key))
        ->all();
@endphp

<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-[20px] font-bold text-gray-900">Detail Bukti Potong 1721-A1</h2>
        <p class="text-[13px] text-gray-500">{{ $certificate->certificate_number }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.tax.bukti-potong', ['year' => $certificate->tax_year]) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
        </a>
        <a href="{{ route('admin.tax.download-bukti-potong', $certificate->id) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
            <span class="material-symbols-outlined text-[16px]">download</span> Download PDF
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-gray-400 uppercase mb-3">Karyawan</div>
        <div class="text-[15px] font-bold text-gray-900">{{ $certificate->employee->full_name }}</div>
        <div class="text-[12px] text-gray-500">{{ $certificate->employee->employee_code }} - {{ $employeeInfo['position'] ?? '-' }}</div>
        <div class="mt-3 space-y-1 text-[12px] text-gray-600">
            <div>NPWP/NIK: <span class="font-semibold text-gray-800">{{ $employeeInfo['npwp'] ?? $employeeInfo['nik'] ?? '-' }}</span></div>
            <div>PTKP: <span class="font-semibold text-gray-800">{{ $employeeInfo['ptkp'] ?? '-' }}</span></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-gray-400 uppercase mb-3">Pajak</div>
        <div class="space-y-1 text-[12px] text-gray-600">
            <div>Tahun: <span class="font-semibold text-gray-800">{{ $certificate->tax_year }}</span></div>
            <div>Kode Objek: <span class="font-semibold text-gray-800">{{ $taxInfo['object_code'] ?? '21-100-01' }}</span></div>
            <div>Masa: <span class="font-semibold text-gray-800">{{ $taxInfo['period_start'] ?? '-' }} s/d {{ $taxInfo['period_end'] ?? '-' }}</span></div>
            <div>Status: <span class="font-semibold {{ $certificate->status === 'final' ? 'text-emerald-700' : 'text-amber-700' }}">{{ ucfirst($certificate->status) }}</span></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-gray-400 uppercase mb-3">Total</div>
        <div class="space-y-1 text-[12px] text-gray-600">
            <div class="flex justify-between"><span>Bruto</span><span class="font-semibold text-gray-800">Rp {{ number_format($certificate->gross_annual, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>BPJS Karyawan</span><span class="font-semibold text-gray-800">Rp {{ number_format($certificate->bpjs_annual, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>PPh 21</span><span class="font-semibold text-red-600">Rp {{ number_format($certificate->tax_annual, 0, ',', '.') }}</span></div>
            <div class="flex justify-between border-t border-gray-100 pt-2 mt-2"><span>Netto</span><span class="font-bold text-gray-900">Rp {{ number_format($certificate->nett_annual, 0, ',', '.') }}</span></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900">Breakdown Bulanan</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Periode</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Bruto</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase text-gray-500 border-b">BPJS</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase text-gray-500 border-b">PPh 21</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Netto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($months as $period => $month)
                <tr>
                    <td class="px-4 py-3 border-b border-gray-100 text-[12px] font-semibold text-gray-800">{{ $period }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px]">Rp {{ number_format($month['gross'] ?? 0, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px]">Rp {{ number_format($month['bpjs'] ?? 0, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px] text-red-600">Rp {{ number_format($month['tax'] ?? 0, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px] font-semibold">Rp {{ number_format($month['net'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-gray-400 text-sm">Belum ada breakdown bulanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
