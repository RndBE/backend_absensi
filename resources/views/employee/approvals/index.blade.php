@extends('employee.layouts.app')
@section('title', 'Persetujuan Tim')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Dashboard
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Persetujuan Tim</h1>
        <p class="text-[13px] text-gray-500 mt-1">Daftar pengajuan yang menunggu persetujuan Anda.</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <section class="space-y-3">
        @forelse($items as $row)
            @php
                $request = $row['model'];
                $type = $row['type'];
                $step = (int) ($request->current_step ?? 1);
            @endphp
            <article class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700">
                                <span class="material-symbols-outlined text-[15px]">
                                    {{ $type === 'leave' ? 'event_available' : ($type === 'overtime' ? 'more_time' : 'edit_calendar') }}
                                </span>
                                Pengajuan {{ $row['type_label'] }}
                            </span>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-bold text-gray-700">Step {{ $step }}</span>
                            @include('employee.partials.status-badge', ['status' => $request->status])
                        </div>
                        <h2 class="mt-3 text-[16px] font-black text-gray-900">{{ $request->employee?->full_name ?? '-' }}</h2>
                        <p class="text-[12px] text-gray-500 mt-1">{{ $request->employee?->position ?? 'Karyawan' }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[12px] text-gray-600 lg:min-w-[420px]">
                        @if($type === 'leave')
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Jenis</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->leaveType?->name ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Tanggal</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->start_date?->format('d/m/Y') }} - {{ $request->end_date?->format('d/m/Y') }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Durasi</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ number_format((float) $request->total_days, 1) }} hari</div>
                            </div>
                        @elseif($type === 'overtime')
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Tanggal</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->date?->format('d/m/Y') }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Tipe</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->overtime_type === 'holiday' ? 'Hari Libur' : 'Hari Kerja' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Durasi</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->total_duration_formatted }}</div>
                            </div>
                        @else
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Tanggal</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->date?->format('d/m/Y') }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Clock In</div>
                                <div class="font-semibold text-emerald-600 mt-1">{{ $request->clock_in ? substr($request->clock_in, 0, 5) : '-' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Clock Out</div>
                                <div class="font-semibold text-blue-600 mt-1">{{ $request->clock_out ? substr($request->clock_out, 0, 5) : '-' }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-[11px] font-bold uppercase text-gray-400">Alasan</div>
                        <div class="text-[13px] text-gray-700 mt-1">{{ $request->reason ?: '-' }}</div>
                    </div>

                    <div class="space-y-3">
                        @php
                            $approveFormId = "approve-{$type}-{$request->id}";
                            $rejectFormId = "reject-{$type}-{$request->id}";
                        @endphp

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <details class="group rounded-lg border border-gray-200 bg-white px-3 py-2">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-[12px] font-bold text-gray-600">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-emerald-600">edit_note</span>
                                        Tambah catatan setuju
                                    </span>
                                    <span class="material-symbols-outlined text-[16px] text-gray-400 transition-transform group-open:rotate-180">expand_more</span>
                                </summary>
                                <textarea form="{{ $approveFormId }}" name="notes" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[13px] outline-none focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100" placeholder="Catatan persetujuan"></textarea>
                            </details>

                            <details class="group rounded-lg border border-gray-200 bg-white px-3 py-2">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-[12px] font-bold text-gray-600">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-red-600">edit_note</span>
                                        Tambah catatan tolak
                                    </span>
                                    <span class="material-symbols-outlined text-[16px] text-gray-400 transition-transform group-open:rotate-180">expand_more</span>
                                </summary>
                                <textarea form="{{ $rejectFormId }}" name="notes" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[13px] outline-none focus:border-red-300 focus:ring-2 focus:ring-red-100" placeholder="Catatan penolakan"></textarea>
                            </details>
                        </div>

                        <div class="approval-action-bar grid grid-cols-2 gap-2">
                            <form id="{{ $rejectFormId }}" method="POST" action="{{ route('employee.approvals.reject', [$type, $request->id]) }}">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-red-700 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                    <span class="material-symbols-outlined text-[17px]">close</span>
                                    Tolak
                                </button>
                            </form>

                            <form id="{{ $approveFormId }}" method="POST" action="{{ route('employee.approvals.approve', [$type, $request->id]) }}">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors">
                                    <span class="material-symbols-outlined text-[17px]">check</span>
                                    Setujui
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[24px]">task_alt</span>
                </div>
                <h2 class="mt-3 text-[15px] font-black text-gray-900">Belum ada approval</h2>
                <p class="text-[13px] text-gray-500 mt-1">Belum ada approval yang perlu diproses.</p>
            </section>
        @endforelse
    </section>
</div>
@endsection
