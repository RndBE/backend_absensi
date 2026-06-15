@extends('employee.layouts.app')
@section('title', 'Pengajuan Lembur')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Pengajuan Lembur</h1>
            <p class="text-[13px] text-gray-500 mt-1">Riwayat dan status lembur.</p>
        </div>
        <a href="{{ route('employee.overtimes.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[17px]">add</span>
            Ajukan
        </a>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Riwayat Lembur</div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tipe</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Durasi</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $overtime)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100">{{ $overtime->date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100">{{ $overtime->overtime_type === 'holiday' ? 'Hari Libur' : 'Hari Kerja' }}</td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100">{{ $overtime->total_duration_formatted }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100">@include('employee.partials.status-badge', ['status' => $overtime->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-10 text-[13px] text-gray-400">Belum ada pengajuan lembur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
