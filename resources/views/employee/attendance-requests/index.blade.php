@extends('employee.layouts.app')
@section('title', 'Pengajuan Absensi')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Pengajuan Absensi</h1>
            <p class="text-[13px] text-gray-500 mt-1">Riwayat koreksi clock in/out.</p>
        </div>
        <a href="{{ route('employee.attendance-requests.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[17px]">add</span>
            Ajukan
        </a>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Riwayat Pengajuan Absensi</div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock In</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock Out</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Alasan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $attendanceRequest)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100 whitespace-nowrap">{{ $attendanceRequest->date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-emerald-600 border-b border-gray-100 whitespace-nowrap">{{ $attendanceRequest->clock_in ? substr($attendanceRequest->clock_in, 0, 5) : '-' }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-blue-600 border-b border-gray-100 whitespace-nowrap">{{ $attendanceRequest->clock_out ? substr($attendanceRequest->clock_out, 0, 5) : '-' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-gray-600 border-b border-gray-100 min-w-[220px]">{{ $attendanceRequest->reason }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100 whitespace-nowrap">@include('employee.partials.status-badge', ['status' => $attendanceRequest->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-10 text-[13px] text-gray-400">Belum ada pengajuan absensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $requests->links() }}</div>
        @endif
    </section>
</div>
@endsection
