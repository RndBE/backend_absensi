@extends('employee.layouts.app')
@section('title', 'Riwayat Presensi')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Dashboard
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Riwayat Presensi</h1>
        <p class="text-[13px] text-gray-500 mt-1">Catatan clock in/out Anda per bulan.</p>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="text-[15px] font-bold text-gray-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">history</span>
                {{ $period->locale('id')->translatedFormat('F Y') }}
            </h2>
            <form method="GET" action="{{ route('employee.attendance.history') }}" class="flex items-center gap-2">
                <label class="text-[12px] font-semibold text-gray-500">Bulan</label>
                <input type="month" name="history_period"
                       value="{{ $period->format('Y-m') }}"
                       max="{{ now()->format('Y-m') }}"
                       onchange="this.form.submit()"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-[13px] font-semibold text-gray-800 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 [color-scheme:light]">
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Masuk</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Pulang</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $attendance)
                        @php
                            $attendanceDateKey = $attendance->date?->format('Y-m-d');
                            $manualPermissionLabel = \App\Support\AttendanceLateExcuse::manualPermissionStatusLabel($attendance->status);
                            $hasLateExcuse = $manualPermissionLabel === null && $attendance->is_late && $attendanceDateKey && $lateExcuseDates->has($attendanceDateKey);
                            $hasEarlyDeparture = $manualPermissionLabel === null && $attendanceDateKey && $earlyDepartureDates->has($attendanceDateKey);
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ $attendance->date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-emerald-600 border-b border-gray-100">{{ $attendance->clock_in ? substr($attendance->clock_in, 0, 5) : '-' }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-blue-600 border-b border-gray-100">{{ $attendance->clock_out ? substr($attendance->clock_out, 0, 5) : '-' }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100">
                                @if($attendance->review_status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">Review</span>
                                @elseif($attendance->review_status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-100 text-red-800">Ditolak</span>
                                @elseif($attendance->status === \App\Support\AttendanceLateExcuse::LATE_EXCUSE_STATUS)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Izin Terlambat</span>
                                @elseif($attendance->status === \App\Support\AttendanceLateExcuse::EARLY_DEPARTURE_STATUS)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800">Izin Pulang Cepat</span>
                                @elseif($hasLateExcuse)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Izin Terlambat</span>
                                @elseif($attendance->is_late)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">Terlambat</span>
                                @elseif($hasEarlyDeparture)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800">Izin Pulang Cepat</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Hadir</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-10 text-[13px] text-gray-400">Belum ada riwayat presensi pada {{ $period->translatedFormat('F Y') }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
