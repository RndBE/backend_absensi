@extends('employee.layouts.app')
@section('title', 'Pengajuan Cuti')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Pengajuan Cuti</h1>
            <p class="text-[13px] text-gray-500 mt-1">Saldo dan riwayat cuti/izin.</p>
        </div>
        <a href="{{ route('employee.leaves.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[17px]">add</span>
            Ajukan
        </a>
    </div>

    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse($balances as $balance)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="text-[12px] font-bold text-gray-500">{{ $balance->leaveType->name ?? 'Cuti' }}</div>
                <div class="mt-2 text-[26px] font-black text-gray-900">{{ $balance->remaining_days }}</div>
                <div class="text-[12px] text-gray-500">sisa dari {{ $balance->total_days }} hari</div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-[13px] text-gray-500">Saldo cuti belum tersedia.</div>
        @endforelse
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Riwayat Cuti</div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Jenis</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Hari</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $leave)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100">{{ $leave->leaveType->name ?? '-' }}</td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100">{{ $leave->start_date?->format('d/m/Y') }} - {{ $leave->end_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100">{{ $leave->total_days_label }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100">@include('employee.partials.status-badge', ['status' => $leave->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-10 text-[13px] text-gray-400">Belum ada pengajuan cuti.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
