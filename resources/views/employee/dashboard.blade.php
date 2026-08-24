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
    {{--
        Kartu presensi hari ini — satu kartu untuk satu pertanyaan: "hari ini aku gimana?"
        Urutannya mengikuti cara orang membacanya: tanggal, sapaan, jadwal, jam masuk &
        pulang, lalu tombol aksinya.

        Kartu sapaan terpisah sengaja DIHAPUS, bukan dipindah ke topbar. Topbar sudah memuat
        nama karyawan (lihat employee/layouts/app.blade.php), cuma disembunyikan di HP; kalau
        nama dipaksa masuk ke situ sementara "Halo, ..." tetap ada, namanya tampil di dua
        tempat pada satu layar. Melebur ke kartu ini juga menghemat satu kartu penuh di HP.

        Ukuran font dan padding punya dua tingkat (mobile lalu sm:) karena kartu ini kini
        memuat empat blok — memakai ukuran lama membuatnya jauh melebihi satu layar HP.
    --}}
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden stat-border-blue">
        <div class="p-4 sm:p-5">
            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ $today->locale('id')->translatedFormat('l, d F Y') }}</div>
            {{-- `break-words` bukan `truncate`: nama di basis data panjang-panjang dan huruf besar semua, lebih baik turun baris daripada terpotong. --}}
            <h1 class="mt-1 text-[18px] sm:text-[22px] font-black text-gray-900 tracking-tight leading-tight break-words">Halo, {{ $employee->full_name }}</h1>
            <p class="text-[12px] text-gray-500 mt-0.5">{{ $employee->position ?? 'Karyawan' }}</p>
        </div>

        <div class="px-4 sm:px-5 py-3.5 border-t border-gray-100 bg-gray-50/60">
            <div class="flex items-center gap-2 text-[11px] font-bold text-gray-500 uppercase tracking-wide">
                <span class="material-symbols-outlined text-[16px] text-blue-500">calendar_month</span>
                Jadwal Hari Ini
            </div>
            <div class="mt-1.5 text-[13px] sm:text-[16px] font-bold text-gray-800 leading-snug">{{ $schedule['name'] }}</div>
            <div class="text-[12px] text-gray-500 mt-0.5">{{ $schedule['time'] }}</div>

            {{--
                Jam masuk & pulang jadi satu baris ringkas di bawah jam kerja, bukan dua
                sub-kartu tersendiri. Angka besar 18-28px dulu memakan tinggi setara satu
                kartu untuk memuat dua bilangan lima karakter; label + jam sebaris membaca
                sama cepat dengan sepersepuluh ruang.

                Label + jam DIBUNGKUS jadi satu chip berwarna, bukan teks lepas. Warna chip
                yang mengabarkan status, jadi badge "Tercatat" tidak perlu lagi — dia cuma
                mengulang apa yang sudah dikatakan hijaunya. Titik kecil di kiri chip
                menegaskan status untuk yang sukar membedakan hijau dari kuning.

                Badge yang TIDAK boleh ikut dihapus: Terlambat, Izin Terlambat, Izin Pulang
                Cepat. Ketiganya membawa keterangan yang tak bisa disampaikan warna — kuning
                cuma bilang "ada yang beda", bukan "kamu telat" atau "telatmu diizinkan".

                `flex-wrap` supaya di HP sempit chip-nya turun baris, bukan terpotong.
                `tabular-nums` menjaga jamnya sejajar antar baris.
            --}}
            @php
                // Satu palet chip per status. Izin terlambat tetap HIJAU: telatnya sudah
                // disahkan, jadi tidak pantas ditandai kuning seperti pelanggaran.
                $clockInExcused = $todayAttendance?->status === \App\Support\AttendanceLateExcuse::LATE_EXCUSE_STATUS || $todayLateExcuse;

                $chipHijau = 'bg-emerald-50 border-emerald-200 text-emerald-700';
                $chipKuning = 'bg-amber-50 border-amber-200 text-amber-700';
                $chipAbu = 'bg-gray-100 border-gray-200 text-gray-400';

                $clockInChip = match (true) {
                    ! $todayAttendance?->clock_in => $chipAbu,
                    (bool) $clockInExcused => $chipHijau,
                    (bool) $todayAttendance?->is_late => $chipKuning,
                    default => $chipHijau,
                };
                $clockInDot = match (true) {
                    ! $todayAttendance?->clock_in => 'bg-gray-300',
                    ! $clockInExcused && $todayAttendance?->is_late => 'bg-amber-500',
                    default => 'bg-emerald-500',
                };

                $clockOutExcused = ($todayAttendance?->status === \App\Support\AttendanceLateExcuse::EARLY_DEPARTURE_STATUS || $todayEarlyDeparture) && $todayAttendance?->clock_out;
                $clockOutChip = $todayAttendance?->clock_out ? $chipHijau : $chipAbu;
                $clockOutDot = $todayAttendance?->clock_out ? 'bg-emerald-500' : 'bg-gray-300';
            @endphp
            <div class="mt-2.5 flex flex-wrap items-center gap-2 border-t border-gray-200/70 pt-2.5">
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 {{ $clockInChip }}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $clockInDot }}"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wide">Clock In</span>
                    <span class="text-[12px] font-black tabular-nums">{{ $todayAttendance?->clock_in ? substr($todayAttendance->clock_in, 0, 5) : '-' }}</span>
                </span>

                @if($clockInExcused)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Izin Terlambat</span>
                @elseif($todayAttendance?->is_late)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Terlambat</span>
                @endif

                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 {{ $clockOutChip }}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $clockOutDot }}"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wide">Clock Out</span>
                    <span class="text-[12px] font-black tabular-nums">{{ $todayAttendance?->clock_out ? substr($todayAttendance->clock_out, 0, 5) : '-' }}</span>
                </span>

                @if($clockOutExcused)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800">Izin Pulang Cepat</span>
                @elseif(! $todayAttendance?->clock_in)
                    {{-- Chip abu saja tidak menjelaskan KENAPA kosong; ini yang membedakan "belum pulang" dari "belum masuk". --}}
                    <span class="text-[11px] text-gray-400">Menunggu clock in</span>
                @endif
            </div>
        </div>

        {{--
            Tombol selebar kartu, BUKAN dua tombol sejajar kolom di atasnya. Pada satu saat
            hanya satu aksi yang sah — sebelum clock in, clock out tidak boleh bisa ditekan.
            Dua tombol berdampingan menyiratkan keduanya terbuka.
        --}}
        <div class="p-4 sm:p-5 border-t border-gray-100">
            @if($actionType)
                <a href="{{ route('employee.attendance.show', $actionType) }}"
                   class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[14px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
                    <span class="material-symbols-outlined text-[19px]">{{ $actionType === 'clock-in' ? 'login' : 'logout' }}</span>
                    {{ $actionLabel }}
                </a>
            @else
                <span class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[14px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <span class="material-symbols-outlined text-[19px]">check_circle</span>
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
                'href' => route('employee.attendance.history'),
                'icon' => 'history',
                'title' => 'Riwayat Presensi',
                'description' => 'Clock in/out per bulan',
                'color' => 'bg-slate-100 text-slate-600',
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

    @include('employee.partials.timeline')
</div>
@endsection
