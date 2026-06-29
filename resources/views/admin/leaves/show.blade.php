@extends('admin.layouts.app')
@section('title', 'Detail Cuti')

@section('content')
<div class="space-y-5">
    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <h3 class="text-[15px] font-bold text-gray-900 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[18px]">description</span>
                Detail Pengajuan Cuti
            </h3>
            <a href="{{ route('admin.leaves.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Kembali
            </a>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[13px]">
                <div>
                    <span class="text-gray-400 text-[11px] font-semibold uppercase">Karyawan</span>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ $leave->employee->full_name }}</p>
                    <p class="text-[11px] text-gray-400">{{ $leave->employee->department->name ?? '' }} - Level {{ $leave->employee->job_level }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-[11px] font-semibold uppercase">Jenis Cuti</span>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ $leave->leaveType->name ?? '-' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-[11px] font-semibold uppercase">Tanggal</span>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ $leave->start_date->format('d M Y') }} - {{ $leave->end_date->format('d M Y') }}</p>
                    <p class="text-[11px] text-gray-400">{{ $leave->total_days_label }} hari kerja</p>
                </div>
                <div>
                    <span class="text-gray-400 text-[11px] font-semibold uppercase">Status</span>
                    @php
                        $colors = ['pending' => 'bg-amber-50 text-amber-700 border-amber-200', 'in_review' => 'bg-blue-50 text-blue-700 border-blue-200', 'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'rejected' => 'bg-red-50 text-red-700 border-red-200'];
                        $labels = ['pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
                    @endphp
                    <p class="mt-0.5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $colors[$leave->status] ?? '' }}">{{ $labels[$leave->status] ?? $leave->status }}</span>
                    </p>
                </div>
                <div>
                    <span class="text-gray-400 text-[11px] font-semibold uppercase">Alasan</span>
                    <p class="font-medium text-gray-700 mt-0.5">{{ $leave->reason }}</p>
                </div>
                @if($leave->attachments->isNotEmpty())
                <div>
                    <span class="text-gray-400 text-[11px] font-semibold uppercase">Lampiran</span>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach($leave->attachments as $att)
                        <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                            <span class="material-symbols-outlined text-[16px]">attach_file</span>
                            {{ $att->file_name ?: 'Lihat lampiran' }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
                @if($leave->delegate)
                <div>
                    <span class="text-gray-400 text-[11px] font-semibold uppercase">Delegasi</span>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ $leave->delegate->full_name }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Approval Chain --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="text-[14px] font-bold text-gray-900 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[17px] text-indigo-500">account_tree</span>
                Approval Chain
            </h4>
        </div>
        <div class="p-5">
            <div id="approvalChainList" class="space-y-3">
                {{-- Requester --}}
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-white shrink-0">
                        <span class="material-symbols-outlined text-[16px]">person</span>
                    </div>
                    <div class="min-w-0 flex-1 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5">
                        <div class="text-[12px] font-bold text-gray-700 truncate">{{ $leave->employee->full_name }}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">Pemohon</div>
                    </div>
                </div>

                @foreach($chain as $step)
                    @php
                        $log = $leave->approvalLogs->where('step_order', $step['step'])->first();
                        $isCurrent = !$log && $leave->current_step == $step['step'] && in_array($leave->status, ['pending', 'in_review']);
                        $isApproved = $log && $log->action === 'approved';
                        $isRejected = $log && $log->action === 'rejected';
                        $cardClass = $isApproved ? 'bg-emerald-50 border-emerald-200' : ($isRejected ? 'bg-red-50 border-red-200' : ($isCurrent ? 'bg-amber-50 border-amber-300 ring-2 ring-amber-200' : 'bg-gray-50 border-gray-200'));
                        $circleClass = $isApproved ? 'bg-emerald-500' : ($isRejected ? 'bg-red-500' : ($isCurrent ? 'bg-amber-500' : 'bg-gray-300'));
                        $textClass = $isApproved ? 'text-emerald-700' : ($isRejected ? 'text-red-700' : ($isCurrent ? 'text-amber-700' : 'text-gray-600'));
                        $mutedClass = $isApproved ? 'text-emerald-500' : ($isRejected ? 'text-red-500' : ($isCurrent ? 'text-amber-500' : 'text-gray-400'));
                        $statusClass = $isApproved ? 'bg-emerald-100 text-emerald-700' : ($isRejected ? 'bg-red-100 text-red-700' : ($isCurrent ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'));
                        $statusLabel = $isApproved ? 'Disetujui' : ($isRejected ? 'Ditolak' : ($isCurrent ? 'Menunggu' : 'Step '.$step['step']));
                    @endphp

                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold text-white shrink-0 {{ $circleClass }}">
                            @if($isApproved)
                                &#10003;
                            @elseif($isRejected)
                                &#10005;
                            @else
                                {{ $step['step'] }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 rounded-lg border px-3 py-2.5 {{ $cardClass }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[12px] font-bold truncate {{ $textClass }}">
                                        {{ $step['employee']->full_name }}
                                    </div>
                                    <div class="text-[10px] mt-0.5 {{ $mutedClass }}">
                                        {{ $step['employee']->position ?? '-' }} - Lv{{ $step['employee']->job_level ?? '-' }}
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold whitespace-nowrap {{ $statusClass }}">
                                    @if($isApproved)
                                        <span aria-hidden="true">&#10003;</span>
                                    @elseif($isRejected)
                                        <span aria-hidden="true">&#10005;</span>
                                    @elseif($isCurrent)
                                        <span class="material-symbols-outlined text-[13px]">hourglass_top</span>
                                    @endif
                                    {{ $statusLabel }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if($leave->status === 'approved')
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-[13px] font-bold text-white shrink-0" aria-label="Selesai">&#10003;</div>
                    <div class="min-w-0 flex-1 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2.5">
                        <div class="text-[12px] font-bold text-emerald-700">Approved</div>
                        <div class="text-[10px] text-emerald-500 mt-0.5">Selesai</div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Approval Logs --}}
    @if($leave->approvalLogs->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="text-[14px] font-bold text-gray-900 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[17px] text-amber-500">history</span>
                Log Approval
            </h4>
        </div>
        <div class="p-5">
            <div class="space-y-3">
                @foreach($leave->approvalLogs as $log)
                <div class="flex items-start gap-3 p-3 rounded-lg {{ $log->action === 'approved' ? 'bg-emerald-50' : 'bg-red-50' }}">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white shrink-0 {{ $log->action === 'approved' ? 'bg-emerald-500' : 'bg-red-500' }}">
                        @if($log->action === 'approved')
                            &#10003;
                        @else
                            &#10005;
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold {{ $log->action === 'approved' ? 'text-emerald-800' : 'text-red-800' }}">
                            {{ $log->approver->full_name ?? 'Unknown' }}
                            <span class="font-normal">{{ $log->action === 'approved' ? 'menyetujui' : 'menolak' }}</span>
                            di Step {{ $log->step_order }}
                            @if($log->via_label)
                            <span class="font-normal text-[11px] text-gray-500">(via {{ $log->via_label }})</span>
                            @endif
                        </div>
                        @if($log->notes)
                        <p class="text-[12px] text-gray-600 mt-0.5">"{{ $log->notes }}"</p>
                        @endif
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $log->created_at->format('d M Y H:i') }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
