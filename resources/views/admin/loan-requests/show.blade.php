@extends('admin.layouts.app')
@section('title', 'Detail Pinjaman')

@section('content')
@php
    $statusMeta = [
        'active' => ['label' => 'Aktif', 'class' => 'bg-indigo-50 text-indigo-700'],
        'paid' => ['label' => 'Lunas', 'class' => 'bg-emerald-50 text-emerald-700'],
        'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-red-50 text-red-700'],
    ];
    $meta = $statusMeta[$loanRequest->status] ?? ['label' => ucfirst($loanRequest->status), 'class' => 'bg-gray-100 text-gray-700'];
    $canManageLoans = app(\App\Support\AdminPermission::class)->can($currentAdmin, 'payroll.loans.manage');
@endphp

<div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <a href="{{ route('admin.loan-requests.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
    @if($canManageLoans)
        <div class="flex gap-2">
            <a href="{{ route('admin.loan-requests.edit', $loanRequest->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                <span class="material-symbols-outlined text-[16px]">edit</span>
                Edit
            </a>
            <form action="{{ route('admin.loan-requests.destroy', $loanRequest->id) }}" method="POST" onsubmit="return confirm('Hapus data pinjaman ini?')">
                @csrf
                @method('DELETE')
                <button class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                    Hapus
                </button>
            </form>
        </div>
    @endif
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-[20px] font-bold text-gray-900">Detail Pinjaman</h1>
            <p class="text-[12px] text-gray-400 mt-1">Dicatat {{ $loanRequest->created_at?->format('d M Y H:i') }}</p>
        </div>
        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
    </div>

    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Karyawan</div>
            <div class="text-[14px] font-bold text-gray-900 mt-1">{{ $loanRequest->employee->full_name ?? '-' }}</div>
            <div class="text-[12px] text-gray-500">{{ $loanRequest->employee->employee_code ?? '-' }} - {{ $loanRequest->employee->department->name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Mulai Potong</div>
            <div class="text-[14px] font-bold text-gray-900 mt-1">{{ $loanRequest->start_period ?: '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Nominal Pinjaman</div>
            <div class="text-[22px] font-black text-gray-900 mt-1">Rp {{ number_format($loanRequest->amount, 0, ',', '.') }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Tenor & Cicilan</div>
            <div class="text-[14px] font-bold text-gray-900 mt-1">{{ $loanRequest->installment_count }}x - Rp {{ number_format($loanRequest->monthly_installment, 0, ',', '.') }}/bulan</div>
            <div class="text-[12px] text-gray-500">Sisa pinjaman Rp {{ number_format($loanRequest->remaining_amount, 0, ',', '.') }}</div>
        </div>
        <div class="md:col-span-2">
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Catatan</div>
            <div class="text-[13px] text-gray-700 mt-1 whitespace-pre-line">{{ $loanRequest->purpose ?: '-' }}</div>
        </div>
    </div>
</div>
@endsection
