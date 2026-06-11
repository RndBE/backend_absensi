@extends('admin.layouts.app')
@section('title', 'Data Pinjaman')

@section('content')
@php
    $statusMeta = [
        'active' => ['label' => 'Aktif', 'class' => 'bg-indigo-50 text-indigo-700'],
        'paid' => ['label' => 'Lunas', 'class' => 'bg-emerald-50 text-emerald-700'],
        'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-red-50 text-red-700'],
    ];
    $canManageLoans = app(\App\Support\AdminPermission::class)->can($currentAdmin, 'payroll.loans.manage');
@endphp

<div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
        <h1 class="text-[22px] font-bold text-gray-900">Data Pinjaman</h1>
        <p class="text-[12px] text-gray-400 mt-0.5">Kelola pinjaman karyawan yang dicatat manual oleh admin atau finance.</p>
    </div>
    @if($canManageLoans)
        <a href="{{ route('admin.loan-requests.create') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
            <span class="material-symbols-outlined text-[16px]">add</span>
            Tambah Pinjaman
        </a>
    @endif
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
    @foreach($statusMeta as $key => $meta)
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-[11px] font-bold uppercase tracking-wide {{ $meta['class'] }} inline-flex px-2 py-0.5 rounded-full">{{ $meta['label'] }}</div>
            <div class="text-[24px] font-black text-gray-900 mt-2">{{ $summary[$key] ?? 0 }}</div>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama / NIK karyawan..." class="w-full md:max-w-xs px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <select name="status" class="px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @foreach(['all' => 'Semua Status', 'active' => 'Aktif', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status', 'all') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                Filter
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Nominal</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Tenor</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Cicilan/Bulan</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Sisa Pinjaman</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loanRequests as $loan)
                    @php $meta = $statusMeta[$loan->status] ?? ['label' => ucfirst($loan->status), 'class' => 'bg-gray-100 text-gray-700']; @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 border-b border-gray-100">
                            <div class="text-[13px] font-semibold text-gray-900">{{ $loan->employee->full_name ?? '-' }}</div>
                            <div class="text-[11px] text-gray-400">{{ $loan->employee->employee_code ?? '-' }} - {{ $loan->employee->department->name ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3 border-b border-gray-100 text-right text-[13px] font-bold text-gray-900">Rp {{ number_format($loan->amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 border-b border-gray-100 text-center text-[13px] text-gray-600">{{ $loan->installment_count }}x</td>
                        <td class="px-4 py-3 border-b border-gray-100 text-right text-[13px] text-gray-600">Rp {{ number_format($loan->monthly_installment, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 border-b border-gray-100 text-right text-[13px] text-gray-600">Rp {{ number_format($loan->remaining_amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 border-b border-gray-100 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                        </td>
                        <td class="px-4 py-3 border-b border-gray-100">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.loan-requests.show', $loan->id) }}" class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">Detail</a>
                                @if($canManageLoans)
                                    <a href="{{ route('admin.loan-requests.edit', $loan->id) }}" class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Edit</a>
                                    <form action="{{ route('admin.loan-requests.destroy', $loan->id) }}" method="POST" onsubmit="return confirm('Hapus data pinjaman ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors cursor-pointer">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Belum ada data pinjaman</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4">
        {{ $loanRequests->links() }}
    </div>
</div>
@endsection
