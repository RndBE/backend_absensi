@extends('employee.layouts.app')
@section('title', 'Detail Pengajuan Lembur')

@section('content')
@php
    $formatMinutes = function ($minutes): string {
        $minutes = max(0, (int) ($minutes ?? 0));
        return intdiv($minutes, 60).'j '.($minutes % 60).'m';
    };
    $isHoliday = $overtime->overtime_type === 'holiday';
@endphp

<div class="space-y-4">
    <div>
        <a href="{{ route('employee.overtimes.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Pengajuan Lembur
        </a>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h1 class="text-[22px] font-black text-gray-900">Detail Pengajuan Lembur</h1>
                <p class="text-[13px] text-gray-500 mt-1">
                    {{ $overtime->date?->format('d/m/Y') ?? '-' }} &middot; {{ $isHoliday ? 'Hari Libur' : 'Hari Kerja' }} &middot; {{ $overtime->total_duration_formatted }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($overtime->status === 'pending')
                    <a href="{{ route('employee.overtimes.edit', $overtime->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-3 py-2 text-[12px] font-bold text-amber-700 hover:bg-amber-100">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                        Edit
                    </a>
                @endif
                @include('employee.partials.status-badge', ['status' => $overtime->status])
            </div>
        </div>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Tanggal</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $overtime->date?->format('d/m/Y') ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Tipe Lembur</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $isHoliday ? 'Hari Libur' : 'Hari Kerja' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Total Durasi</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $overtime->total_duration_formatted }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Total Istirahat</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $formatMinutes($overtime->break_duration) }}</div>
        </div>

        @if($isHoliday)
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Jam Mulai</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ $overtime->planned_start ? substr($overtime->planned_start, 0, 5) : '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Jam Selesai</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ $overtime->planned_end ? substr($overtime->planned_end, 0, 5) : '-' }}</div>
            </div>
        @else
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Lembur Pre-Shift</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ $formatMinutes($overtime->pre_shift_duration) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Istirahat Pre-Shift</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ $formatMinutes($overtime->pre_shift_break) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Lembur Post-Shift</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ $formatMinutes($overtime->post_shift_duration) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Istirahat Post-Shift</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ $formatMinutes($overtime->post_shift_break) }}</div>
            </div>
        @endif

        @if(! is_null($overtime->approved_duration) || ! is_null($overtime->actual_duration))
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Durasi Disetujui</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ is_null($overtime->approved_duration) ? '-' : $formatMinutes($overtime->approved_duration) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-400">Durasi Aktual</div>
                <div class="mt-1 text-[13px] text-gray-700">{{ is_null($overtime->actual_duration) ? '-' : $formatMinutes($overtime->actual_duration) }}</div>
            </div>
        @endif
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-[15px] font-black text-gray-900">Alasan</h2>
        <p class="mt-2 text-[13px] text-gray-700 whitespace-pre-line">{{ $overtime->reason ?: '-' }}</p>
    </section>

    @if($overtime->approvalLogs->isNotEmpty())
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-[15px] font-black text-gray-900">Riwayat Approval</h2>
            <div class="mt-3 space-y-2">
                @foreach($overtime->approvalLogs as $log)
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-[13px] text-gray-700">
                        <span class="font-bold">{{ $log->approver?->full_name ?? 'Approver' }}</span>
                        {{ $log->action === 'approved' ? 'menyetujui' : ($log->action === 'rejected' ? 'menolak' : $log->action) }} di step {{ $log->step_order }}
                        @if($log->notes)<span class="text-gray-500"> &middot; {{ $log->notes }}</span>@endif
                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $log->created_at?->format('d M Y H:i') }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
