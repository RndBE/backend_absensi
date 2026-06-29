@extends('employee.layouts.app')
@section('title', 'Detail Pengajuan Cuti')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.leaves.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Pengajuan Cuti
        </a>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h1 class="text-[22px] font-black text-gray-900">{{ $leave->leaveType->name ?? 'Cuti' }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">{{ $leave->start_date?->format('d/m/Y') }} - {{ $leave->end_date?->format('d/m/Y') }} · {{ $leave->total_days_label }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($leave->status === 'pending')
                    <a href="{{ route('employee.leaves.edit', $leave->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-3 py-2 text-[12px] font-bold text-amber-700 hover:bg-amber-100">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                        Edit
                    </a>
                @endif
                @include('employee.partials.status-badge', ['status' => $leave->status])
            </div>
        </div>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Jenis Cuti</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $leave->leaveType->name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Jumlah Hari</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $leave->total_days_label }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Tanggal Mulai</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $leave->start_date?->format('d/m/Y') ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Tanggal Selesai</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $leave->end_date?->format('d/m/Y') ?? '-' }}</div>
        </div>
        @if($leave->delegate)
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Delegasi Tugas</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $leave->delegate->full_name }}</div>
        </div>
        @endif
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-[15px] font-black text-gray-900">Alasan</h2>
        <p class="mt-2 text-[13px] text-gray-700 whitespace-pre-line">{{ $leave->reason ?: '-' }}</p>
    </section>

    @if($leave->attachments->isNotEmpty())
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-[15px] font-black text-gray-900">Lampiran</h2>
            <div class="mt-3 space-y-2">
                @foreach($leave->attachments as $att)
                    <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                        <span class="material-symbols-outlined text-[16px]">attach_file</span>
                        {{ $att->file_name ?? 'Lampiran' }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($leave->approvalLogs->isNotEmpty())
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-[15px] font-black text-gray-900">Riwayat Approval</h2>
            <div class="mt-3 space-y-2">
                @foreach($leave->approvalLogs as $log)
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-[13px] text-gray-700">
                        <span class="font-bold">{{ $log->approver?->full_name ?? 'Approver' }}</span>
                        {{ $log->action === 'approved' ? 'menyetujui' : ($log->action === 'rejected' ? 'menolak' : $log->action) }} di step {{ $log->step_order }}
                        @if($log->via_label)<span class="text-gray-500"> (via {{ $log->via_label }})</span>@endif
                        @if($log->notes)<span class="text-gray-500"> · {{ $log->notes }}</span>@endif
                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $log->created_at->format('d M Y H:i') }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
