@extends('employee.layouts.app')
@section('title', 'Dashboard Employee')

@section('content')
@php
    $hasClockIn = (bool) $todayAttendance?->clock_in;
    $hasClockOut = (bool) $todayAttendance?->clock_out;
    $todayManualPermissionLabel = \App\Support\AttendanceLateExcuse::manualPermissionStatusLabel($todayAttendance?->status);
    $todayLateExcuse = $todayManualPermissionLabel === null && $todayAttendance?->is_late ? ($lateExcuseDates->get($today->toDateString()) ?? null) : null;
    $todayEarlyDeparture = $todayManualPermissionLabel === null && $todayAttendance ? ($earlyDepartureDates->get($today->toDateString()) ?? null) : null;
    $actionType = ! $hasClockIn ? 'clock-in' : (! $hasClockOut ? 'clock-out' : null);
    $actionLabel = ! $hasClockIn ? 'Clock In Sekarang' : (! $hasClockOut ? 'Clock Out Sekarang' : 'Presensi Selesai');
@endphp

<div class="space-y-5">
    <section class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <div class="text-[12px] font-semibold text-gray-400">{{ $today->locale('id')->translatedFormat('l, d F Y') }}</div>
                <h1 class="mt-1 text-[22px] sm:text-[26px] font-black text-gray-900 tracking-tight">Halo, {{ $employee->full_name }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">{{ $employee->position ?? 'Karyawan' }}</p>
            </div>
            @if($actionType)
                <a href="{{ route('employee.attendance.show', $actionType) }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
                    <span class="material-symbols-outlined text-[18px]">{{ $actionType === 'clock-in' ? 'login' : 'logout' }}</span>
                    {{ $actionLabel }}
                </a>
            @else
                <span class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    {{ $actionLabel }}
                </span>
            @endif
        </div>
    </section>

    @if(isset($pendingLhp) && $pendingLhp->isNotEmpty())
        <section class="rounded-xl border border-amber-200 bg-amber-50/60 shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-[20px] text-amber-600">assignment_late</span>
                <h2 class="text-[14px] font-black text-gray-900">Pengingat LHP</h2>
                <span class="ml-auto inline-flex items-center justify-center rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $pendingLhp->count() }}</span>
            </div>
            <p class="text-[12px] text-gray-500 mb-3">Perjalanan berikut belum dibuat LHP-nya. Segera buat sebelum melewati batas.</p>
            <div class="space-y-2">
                @foreach($pendingLhp as $budgetRequest)
                    @include('employee.budget-requests.partials.lhp-reminder', ['budgetRequest' => $budgetRequest])
                @endforeach
            </div>
        </section>
    @endif

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg {{ $employee->face_photo ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }} flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">{{ $employee->face_photo ? 'verified_user' : 'face' }}</span>
                </div>
                <div>
                    <div class="text-[14px] font-black text-gray-900">Verifikasi Wajah</div>
                    <div class="text-[12px] text-gray-500 mt-1">
                        @if($employee->face_photo)
                            Foto referensi sudah terdaftar.
                        @elseif($settings['face_verification_enabled'])
                            Daftarkan wajah sebelum presensi.
                        @else
                            Verifikasi wajah belum diwajibkan.
                        @endif
                    </div>
                </div>
            </div>
            <a href="{{ route('employee.face-photo.show') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                <span class="material-symbols-outlined text-[17px]">photo_camera</span>
                {{ $employee->face_photo ? 'Update Foto Wajah' : 'Daftarkan Wajah' }}
            </a>
        </div>
    </section>

    @if(($pendingApprovalCount ?? 0) > 0)
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">priority_high</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[14px] font-black text-gray-900">Persetujuan Tim</div>
                        <div class="text-[12px] text-amber-800 mt-1">
                            Ada {{ $pendingApprovalCount }} pengajuan menunggu approval Anda.
                        </div>
                    </div>
                </div>
                <a href="{{ route('employee.approvals.index') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-amber-900 bg-white border border-amber-200 rounded-lg hover:bg-amber-100 transition-all">
                    <span class="material-symbols-outlined text-[17px]">fact_check</span>
                    Lihat Persetujuan Tim
                </a>
            </div>
        </section>
    @endif

    @php
        $shortcuts = [
            [
                'href' => route('employee.attendance-requests.index'),
                'icon' => 'edit_calendar',
                'title' => 'Absensi',
                'description' => 'Koreksi clock',
                'color' => 'bg-emerald-50 text-emerald-600',
            ],
            [
                'href' => route('employee.leaves.index'),
                'icon' => 'event_available',
                'title' => 'Cuti',
                'description' => 'Ajukan izin',
                'color' => 'bg-sky-50 text-sky-600',
            ],
            [
                'href' => route('employee.overtimes.index'),
                'icon' => 'more_time',
                'title' => 'Lembur',
                'description' => 'Ajukan lembur',
                'color' => 'bg-violet-50 text-violet-600',
            ],
            [
                'href' => route('employee.budget-requests.index'),
                'icon' => 'request_quote',
                'title' => 'Anggaran',
                'description' => 'Budget kerja',
                'color' => 'bg-teal-50 text-teal-600',
            ],
            [
                'href' => route('employee.travel-reports.index'),
                'icon' => 'flight_takeoff',
                'title' => 'LHP',
                'description' => 'Laporan dinas',
                'color' => 'bg-rose-50 text-rose-600',
            ],
            [
                'href' => route('employee.lpj.index'),
                'icon' => 'receipt_long',
                'title' => 'LPJ',
                'description' => 'Pertanggungjawaban',
                'color' => 'bg-indigo-50 text-indigo-600',
            ],
            [
                'href' => route('employee.approvals.index'),
                'icon' => 'fact_check',
                'title' => 'Persetujuan Tim',
                'description' => 'Setujui tim',
                'color' => 'bg-amber-50 text-amber-600',
            ],
            [
                'href' => route('employee.company-info.index'),
                'icon' => 'domain',
                'title' => 'Info Perusahaan',
                'description' => 'Peraturan & kontak',
                'color' => 'bg-blue-50 text-blue-600',
            ],
            [
                'href' => route('employee.violation-report.index'),
                'icon' => 'report',
                'title' => 'Aduan Pelanggaran',
                'description' => 'Form resmi',
                'color' => 'bg-red-50 text-red-600',
            ],
        ];

        // Presensi tim hanya untuk manager yang punya departemen — sama seperti penjagaan
        // di TeamAttendanceController, jadi pintasan ini tak pernah menuju halaman 403.
        if ($employee->role === 'manager' && $employee->department_id) {
            $shortcuts[] = [
                'href' => route('employee.team-attendance.index'),
                'icon' => 'groups',
                'title' => 'Presensi Tim',
                'description' => 'Rekap & riwayat',
                'color' => 'bg-teal-50 text-teal-600',
            ];
        }
    @endphp
    <section class="employee-dashboard-shortcuts grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
        @foreach($shortcuts as $shortcut)
            <a href="{{ $shortcut['href'] }}"
               @if(!empty($shortcut['external'])) target="_blank" rel="noopener noreferrer" @endif
               class="min-h-[96px] bg-white rounded-xl border border-gray-200 shadow-sm p-3.5 hover:-translate-y-0.5 hover:shadow-md transition-all">
                <div class="flex h-full flex-col justify-between gap-3">
                    <div class="w-9 h-9 rounded-lg {{ $shortcut['color'] }} flex items-center justify-center">
                        <span class="material-symbols-outlined text-[19px]">{{ $shortcut['icon'] }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[13px] font-black text-gray-900 leading-tight truncate flex items-center gap-1">
                            <span class="truncate">{{ $shortcut['title'] }}</span>
                            @if(!empty($shortcut['external']))
                                <span class="material-symbols-outlined text-[13px] text-gray-300 shrink-0">open_in_new</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-gray-500 mt-1 leading-tight truncate">{{ $shortcut['description'] }}</div>
                    </div>
                </div>
            </a>
        @endforeach
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 stat-border-blue">
            <div class="flex items-center gap-2 text-[12px] font-bold text-gray-500 uppercase tracking-wide">
                <span class="material-symbols-outlined text-[17px] text-blue-500">calendar_month</span>
                Jadwal Hari Ini
            </div>
            <div class="mt-3 text-[17px] font-black text-gray-900">{{ $schedule['name'] }}</div>
            <div class="text-[13px] text-gray-500 mt-1">{{ $schedule['time'] }}</div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 stat-border-green">
            <div class="flex items-center gap-2 text-[12px] font-bold text-gray-500 uppercase tracking-wide">
                <span class="material-symbols-outlined text-[17px] text-emerald-500">login</span>
                Clock In
            </div>
            <div class="mt-3 text-[28px] font-black text-gray-900 leading-none">{{ $todayAttendance?->clock_in ? substr($todayAttendance->clock_in, 0, 5) : '-' }}</div>
            <div class="mt-2">
                @if($todayAttendance?->status === \App\Support\AttendanceLateExcuse::LATE_EXCUSE_STATUS)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Izin Terlambat</span>
                @elseif($todayLateExcuse)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Izin Terlambat</span>
                @elseif($todayAttendance?->is_late)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">Terlambat</span>
                @elseif($todayAttendance?->clock_in)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Tercatat</span>
                @else
                    <span class="text-[12px] text-gray-400">Belum clock in</span>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 stat-border-purple">
            <div class="flex items-center gap-2 text-[12px] font-bold text-gray-500 uppercase tracking-wide">
                <span class="material-symbols-outlined text-[17px] text-indigo-500">logout</span>
                Clock Out
            </div>
            <div class="mt-3 text-[28px] font-black text-gray-900 leading-none">{{ $todayAttendance?->clock_out ? substr($todayAttendance->clock_out, 0, 5) : '-' }}</div>
            <div class="mt-2">
                @if($todayAttendance?->status === \App\Support\AttendanceLateExcuse::EARLY_DEPARTURE_STATUS && $todayAttendance?->clock_out)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800">Izin Pulang Cepat</span>
                @elseif($todayEarlyDeparture && $todayAttendance?->clock_out)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800">Izin Pulang Cepat</span>
                @elseif($todayAttendance?->clock_out)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800">Tercatat</span>
                @elseif($todayAttendance?->clock_in)
                    <span class="text-[12px] text-gray-400">Belum clock out</span>
                @else
                    <span class="text-[12px] text-gray-400">Menunggu clock in</span>
                @endif
            </div>
        </div>
    </section>

    @if($todayAttendance?->is_remote || $todayAttendance?->review_status)
        <section class="rounded-xl border {{ $todayAttendance->review_status === 'rejected' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[20px] {{ $todayAttendance->review_status === 'rejected' ? 'text-red-600' : 'text-amber-600' }}">info</span>
                <div>
                    <div class="text-[13px] font-bold text-gray-900">
                        {{ $todayAttendance->review_status === 'pending' ? 'Presensi menunggu review HRD' : ($todayAttendance->review_status === 'rejected' ? 'Presensi ditolak HRD' : 'Presensi remote') }}
                    </div>
                    <div class="text-[12px] text-gray-600 mt-1">{{ $todayAttendance->suspicious_reason ?: $todayAttendance->remote_notes }}</div>
                </div>
            </div>
        </section>
    @endif

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="text-[15px] font-bold text-gray-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">history</span>
                Riwayat Presensi
            </h2>
            <form method="GET" action="{{ route('employee.dashboard') }}" class="flex items-center gap-2">
                <label class="text-[12px] font-semibold text-gray-500">Bulan</label>
                <input type="month" name="history_period"
                       value="{{ $historyPeriod->format('Y-m') }}"
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
                    @forelse($recentAttendances as $attendance)
                        @php
                            $attendanceDateKey = $attendance->date?->format('Y-m-d');
                            $manualPermissionLabel = \App\Support\AttendanceLateExcuse::manualPermissionStatusLabel($attendance->status);
                            $hasLateExcuse = $manualPermissionLabel === null && $attendance->is_late && $attendanceDateKey && $historyLateExcuseDates->has($attendanceDateKey);
                            $hasEarlyDeparture = $manualPermissionLabel === null && $attendanceDateKey && $historyEarlyDepartureDates->has($attendanceDateKey);
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
                            <td colspan="4" class="text-center py-10 text-[13px] text-gray-400">Belum ada riwayat presensi pada {{ $historyPeriod->translatedFormat('F Y') }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
