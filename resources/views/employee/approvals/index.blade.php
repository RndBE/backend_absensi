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
        <p class="text-[13px] text-gray-500 mt-1">
            {{ $tab === 'history'
                ? 'Keputusan yang pernah Anda ambil, beserta catatannya.'
                : 'Daftar pengajuan yang menunggu persetujuan Anda.' }}
        </p>
    </div>

    {{-- Tab: yang perlu diproses vs riwayat keputusan sendiri --}}
    <div class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1">
        @foreach([
            'pending' => ['Perlu Diproses', 'pending_actions', $pendingCount],
            'history' => ['Riwayat', 'history', $historyCount],
        ] as $key => [$label, $icon, $count])
            <a href="{{ route('employee.approvals.index', $key === 'pending' ? [] : ['tab' => $key]) }}"
               class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-[12.5px] font-bold transition-colors
                      {{ $tab === $key ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-50' }}">
                <span class="material-symbols-outlined text-[17px]">{{ $icon }}</span>
                {{ $label }}
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === $key ? 'bg-white/20' : 'bg-gray-100 text-gray-600' }}">{{ $count }}</span>
            </a>
        @endforeach
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

    @if($tab === 'history')
    {{-- Riwayat: padat, satu baris per keputusan. Kartu terlalu boros untuk data historis. --}}
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        @forelse($history as $log)
            @php
                $item = $log->approvable;
                $typeKey = array_search($log->approvable_type, [
                    'leave' => \App\Models\LeaveRequest::class,
                    'overtime' => \App\Models\OvertimeRequest::class,
                    'attendance' => \App\Models\AttendanceRequest::class,
                    'budget' => \App\Models\BudgetRequest::class,
                    'travel_report' => \App\Models\TravelReport::class,
                    'lpj' => \App\Models\Lpj::class,
                ], true);
                $disetujui = $log->action === 'approved';
            @endphp
            <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-3.5 last:border-b-0 sm:flex-row sm:items-start sm:gap-4">
                <div class="flex shrink-0 items-center gap-2 sm:w-40">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold
                        {{ $disetujui ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                        <span class="material-symbols-outlined text-[14px]">{{ $disetujui ? 'check' : 'close' }}</span>
                        {{ $disetujui ? 'Disetujui' : 'Ditolak' }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10.5px] font-bold text-gray-500">Step {{ $log->step_order }}</span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="text-[13px] font-bold text-gray-900">{{ $item?->employee?->full_name ?? 'Karyawan dihapus' }}</span>
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10.5px] font-bold text-indigo-700">{{ $typeLabels[$typeKey] ?? $typeKey }}</span>
                        @if($item?->status)
                            <span class="text-[11px] text-gray-400">· status akhir: <span class="font-semibold text-gray-600">{{ ucfirst($item->status) }}</span></span>
                        @endif
                    </div>
                    <div class="mt-0.5 text-[11.5px] text-gray-400">{{ $item?->employee?->department?->name }}</div>

                    @if($log->notes)
                        <div class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-[12.5px] text-gray-700">
                            <span class="font-bold text-gray-400">Catatan Anda:</span> {{ $log->notes }}
                        </div>
                    @endif

                    @if($log->via_label)
                        <div class="mt-1 text-[11px] text-amber-600">Ditindak oleh {{ $log->via_label }} atas nama Anda</div>
                    @endif
                </div>

                <div class="shrink-0 text-left text-[11.5px] text-gray-400 sm:text-right">
                    {{ $log->created_at->format('d/m/Y') }}<br>
                    <span class="text-[11px]">{{ $log->created_at->format('H:i') }}</span>
                </div>
            </div>
        @empty
            <div class="p-10 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <span class="material-symbols-outlined text-[24px]">history</span>
                </div>
                <h2 class="mt-3 text-[15px] font-black text-gray-900">Belum ada riwayat</h2>
                <p class="mt-1 text-[13px] text-gray-500">Keputusan yang Anda ambil akan muncul di sini.</p>
            </div>
        @endforelse
    </section>

    @if($history->hasPages())
        <div class="pt-1">{{ $history->links() }}</div>
    @endif

    @else
    <section class="space-y-3">
        @forelse($items as $row)
            @php
                $request = $row['model'];
                $type = $row['type'];
                $step = $row['step'];
                $totalSteps = $row['total_steps'];
                $nextApprover = $row['next_approver'];
                $balance = $row['balance'];

                // Umur pengajuan: yang menumpuk harus terlihat, bukan tenggelam di antara yang baru.
                $umurJam = $request->created_at->diffInHours(now());
                $umurWarna = $umurJam >= 72 ? 'bg-red-50 text-red-700' : ($umurJam >= 24 ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600');

                // Kuota kurang → menyetujui akan menekan saldo ke 0 (atau ditolak sistem untuk cuti tahunan).
                $kuotaKurang = $balance && (float) $balance->remaining_days < (float) $request->total_days;
            @endphp
            <article class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700">
                                <span class="material-symbols-outlined text-[15px]">
                                    {{ match($type) {
                                        'leave' => 'event_available',
                                        'overtime' => 'more_time',
                                        'attendance' => 'edit_calendar',
                                        'budget' => 'request_quote',
                                        'travel_report' => 'flight_takeoff',
                                        'lpj' => 'receipt_long',
                                        default => 'fact_check',
                                    } }}
                                </span>
                                Pengajuan {{ $row['type_label'] }}
                            </span>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-bold text-gray-700">
                                Step {{ $step }}@if($totalSteps > 1) dari {{ $totalSteps }}@endif
                            </span>
                            @include('employee.partials.status-badge', ['status' => $request->status])

                            {{-- Umur pengajuan --}}
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $umurWarna }}">
                                <span class="material-symbols-outlined text-[14px]">schedule</span>
                                Menunggu {{ $request->created_at->diffForHumans(null, true) }}
                            </span>
                        </div>
                        <h2 class="mt-3 text-[16px] font-black text-gray-900">{{ $request->employee?->full_name ?? '-' }}</h2>
                        <p class="text-[12px] text-gray-500 mt-1">{{ $request->employee?->position ?? 'Karyawan' }}</p>

                        {{-- Posisi keputusan ini dalam rantai approval --}}
                        <p class="mt-2 text-[11.5px]">
                            @if($nextApprover)
                                <span class="text-gray-400">Setelah Anda:</span>
                                <span class="font-semibold text-gray-600">{{ $nextApprover }}</span>
                            @else
                                <span class="inline-flex items-center gap-1 font-bold text-indigo-600">
                                    <span class="material-symbols-outlined text-[14px]">verified</span>
                                    Keputusan Anda final
                                </span>
                            @endif
                        </p>
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
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->total_days_label }} hari</div>
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
                        @elseif($type === 'attendance')
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
                        @elseif($type === 'budget')
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Judul</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->title }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Tipe</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->type === 'budget' ? 'Budget' : 'Reimbursement' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Nominal</div>
                                <div class="font-semibold text-gray-900 mt-1">Rp {{ number_format((float) $request->total_amount, 0, ',', '.') }}</div>
                            </div>
                        @elseif($type === 'lpj')
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Nomor</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->nomor_lpj ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Budget</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->budgetRequest?->title ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Realisasi</div>
                                <div class="font-semibold text-gray-900 mt-1">Rp {{ number_format((float) $request->total_realisasi, 0, ',', '.') }}</div>
                            </div>
                        @else
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Tujuan</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->destination_city }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Tanggal</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->departure_date?->format('d/m/Y') }} - {{ $request->return_date?->format('d/m/Y') }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-400 uppercase text-[10px]">Budget</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $request->budgetRequest?->title ?? '-' }}</div>
                            </div>
                        @endif
                    </div>

                    @if($type === 'budget')
                    <a href="{{ route('employee.approvals.budget.print', $request->id) }}" target="_blank" class="approval-print-link inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-teal-600 px-3 text-[12px] font-bold text-white transition-colors hover:bg-teal-700">
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        Cetak
                    </a>
                    @elseif($type === 'travel_report')
                    <a href="{{ route('employee.approvals.travel_report.print', $request->id) }}" target="_blank" class="approval-print-link inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-teal-600 px-3 text-[12px] font-bold text-white transition-colors hover:bg-teal-700">
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        Lihat/Cetak
                    </a>
                    @elseif($type === 'lpj')
                    <a href="{{ route('employee.approvals.lpj.print', $request->id) }}" target="_blank" class="approval-print-link inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-teal-600 px-3 text-[12px] font-bold text-white transition-colors hover:bg-teal-700">
                        <span class="material-symbols-outlined text-[16px]">print</span>
                        Lihat/Cetak
                    </a>
                    @endif
                </div>

                <div class="p-5 space-y-4">
                    {{-- Sisa kuota cuti — konteks yang menentukan keputusan, harus dilihat SEBELUM menyetujui. --}}
                    @if($balance)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-3
                        {{ $kuotaKurang ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' }}">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] {{ $kuotaKurang ? 'text-red-600' : 'text-emerald-600' }}">
                                {{ $kuotaKurang ? 'warning' : 'account_balance_wallet' }}
                            </span>
                            <span class="text-[12.5px] {{ $kuotaKurang ? 'text-red-800' : 'text-emerald-800' }}">
                                Sisa kuota <span class="font-bold">{{ $request->leaveType?->name }}</span>:
                                <span class="font-black">{{ rtrim(rtrim(number_format((float) $balance->remaining_days, 1, ',', '.'), '0'), ',') }}</span>
                                dari {{ rtrim(rtrim(number_format((float) $balance->total_days + (float) $balance->carry_over, 1, ',', '.'), '0'), ',') }} hari
                            </span>
                        </div>
                        @if($kuotaKurang)
                            <span class="text-[11.5px] font-bold text-red-700">
                                Pengajuan {{ rtrim(rtrim(number_format((float) $request->total_days, 1, ',', '.'), '0'), ',') }} hari — melebihi sisa kuota
                            </span>
                        @endif
                    </div>
                    @endif

                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-[11px] font-bold uppercase text-gray-400">
                            {{ in_array($type, ['budget', 'travel_report', 'lpj'], true) ? 'Keterangan' : 'Alasan' }}
                        </div>
                        <div class="text-[13px] text-gray-700 mt-1">
                            @if($type === 'budget')
                                {{ $request->description ?: '-' }}
                            @elseif($type === 'travel_report')
                                {{ $request->purpose ?: '-' }}
                            @elseif($type === 'lpj')
                                {{ $request->catatan ?: '-' }}
                            @else
                                {{ $request->reason ?: '-' }}
                            @endif
                        </div>
                    </div>

                    @if(in_array($type, ['leave', 'overtime', 'budget', 'travel_report'], true) && $request->attachments->isNotEmpty())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-[11px] font-bold uppercase text-gray-400">Lampiran</div>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            @foreach($request->attachments as $att)
                            <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                                <span class="material-symbols-outlined text-[16px]">attach_file</span>
                                {{ $att->file_name ?: 'Lihat lampiran' }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Satu form, satu catatan. Tombol yang ditekan menentukan tujuannya lewat
                         formaction, jadi catatan yang sama ikut terkirim baik saat setuju
                         maupun tolak — tidak ada lagi dua kotak yang bisa terisi berbeda. --}}
                    <form method="POST" action="{{ route('employee.approvals.approve', [$type, $request->id]) }}" class="space-y-3">
                        @csrf

                        <details class="group rounded-lg border border-gray-200 bg-white px-3 py-2">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-[12px] font-bold text-gray-600">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-indigo-600">edit_note</span>
                                    Tambah catatan <span class="font-normal text-gray-400">(opsional)</span>
                                </span>
                                <span class="material-symbols-outlined text-[16px] text-gray-400 transition-transform group-open:rotate-180">expand_more</span>
                            </summary>
                            <textarea name="notes" rows="2" maxlength="1000"
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                                placeholder="Catatan untuk keputusan Anda — tersimpan baik saat menyetujui maupun menolak"></textarea>
                        </details>

                        <div class="approval-action-bar grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button type="submit" formaction="{{ route('employee.approvals.reject', [$type, $request->id]) }}"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-red-700 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                <span class="material-symbols-outlined text-[17px]">close</span>
                                Tolak
                            </button>

                            <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors">
                                <span class="material-symbols-outlined text-[17px]">check</span>
                                Setujui
                            </button>
                        </div>
                    </form>
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
    @endif
</div>
@endsection
