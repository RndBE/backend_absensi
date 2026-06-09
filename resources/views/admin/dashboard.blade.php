@extends('admin.layouts.app')
@section('title', 'Dashboard')

@section('content')
{{-- Stat Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-7">
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start gap-4 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 stat-border-blue animate-fade-in-up delay-1">
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[24px]">group</span></div>
        <div>
            <div class="text-[28px] font-extrabold text-gray-900 leading-none mb-1 tracking-tight">{{ $totalEmployees }}</div>
            <div class="text-[13px] text-gray-500 font-medium">Total Karyawan</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start gap-4 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 stat-border-green animate-fade-in-up delay-2">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[24px]">check_circle</span></div>
        <div>
            <div class="text-[28px] font-extrabold text-gray-900 leading-none mb-1 tracking-tight">{{ $presentToday }}</div>
            <div class="text-[13px] text-gray-500 font-medium">Hadir Hari Ini</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start gap-4 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 stat-border-yellow animate-fade-in-up delay-3">
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[24px]">schedule</span></div>
        <div>
            <div class="text-[28px] font-extrabold text-gray-900 leading-none mb-1 tracking-tight">{{ $lateToday }}</div>
            <div class="text-[13px] text-gray-500 font-medium">Terlambat</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start gap-4 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 stat-border-red animate-fade-in-up delay-4">
        <div class="w-12 h-12 rounded-xl bg-red-50 text-red-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[24px]">cancel</span></div>
        <div>
            <div class="text-[28px] font-extrabold text-gray-900 leading-none mb-1 tracking-tight">{{ $absentToday }}</div>
            <div class="text-[13px] text-gray-500 font-medium">Tidak Hadir</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start gap-4 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 stat-border-purple animate-fade-in-up delay-5">
        <div class="w-12 h-12 rounded-xl bg-violet-50 text-violet-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[24px]">pending_actions</span></div>
        <div>
            <div class="text-[28px] font-extrabold text-gray-900 leading-none mb-1 tracking-tight">{{ $totalPending }}</div>
            <div class="text-[13px] text-gray-500 font-medium">Menunggu Persetujuan</div>
        </div>
    </div>
</div>

{{-- Monthly HR & Attendance Summary --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-7">
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200">
        <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[22px]">running_with_errors</span></div>
        <div>
            <div class="text-[24px] font-extrabold text-gray-900 leading-none mb-1">{{ $lateThisMonth }}</div>
            <div class="text-[12.5px] text-gray-500 font-medium">Terlambat Bulan Ini</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[22px]">event_busy</span></div>
        <div>
            <div class="text-[24px] font-extrabold text-gray-900 leading-none mb-1">{{ $pendingLeave }}</div>
            <div class="text-[12.5px] text-gray-500 font-medium">Cuti Pending</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200">
        <div class="w-10 h-10 rounded-lg bg-violet-50 text-violet-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[22px]">more_time</span></div>
        <div>
            <div class="text-[24px] font-extrabold text-gray-900 leading-none mb-1">{{ $pendingOvertime }}</div>
            <div class="text-[12.5px] text-gray-500 font-medium">Lembur Pending</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200">
        <div class="w-10 h-10 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[22px]">fact_check</span></div>
        <div>
            <div class="text-[24px] font-extrabold text-gray-900 leading-none mb-1">{{ $pendingAttendance }}</div>
            <div class="text-[12.5px] text-gray-500 font-medium">Presensi Pending</div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200">
        <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-500 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[22px]">person_remove</span></div>
        <div>
            <div class="text-[24px] font-extrabold text-gray-900 leading-none mb-1">{{ $resignedThisMonth }}</div>
            <div class="text-[12.5px] text-gray-500 font-medium">Resign Bulan Ini</div>
        </div>
    </div>
</div>

{{-- Contract Watch --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-7 animate-fade-in-up">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">contract</span> Kontrak Hampir Habis</h3>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-amber-100 text-amber-800">{{ $contractsEndingSoonCount }} karyawan</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Akhir Kontrak</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Sisa Hari</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contractsEndingSoon as $employee)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="text-[13px] font-semibold text-gray-800">{{ $employee->full_name }}</div>
                            <div class="text-[11px] text-gray-400">{{ $employee->employee_code }} - {{ $employee->position ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            @php
                                $statusLabel = match($employee->employment_status) {
                                    'contract' => 'Kontrak',
                                    'intern' => 'Magang',
                                    'probation' => 'Probation',
                                    default => ucfirst($employee->employment_status),
                                };
                                $statusColor = match($employee->employment_status) {
                                    'contract' => 'bg-blue-100 text-blue-800',
                                    'intern' => 'bg-orange-100 text-orange-700',
                                    'probation' => 'bg-amber-100 text-amber-800',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-[13px] text-gray-700">{{ $employee->contract_end_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-bold text-amber-700">{{ now()->startOfDay()->diffInDays($employee->contract_end_date, false) }} hari</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-8 text-gray-400 text-sm">Tidak ada kontrak yang habis dalam 60 hari ke depan</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Grid 2 --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    {{-- Recent Attendance --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm animate-fade-in-up">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">schedule</span> Absensi Hari Ini</h3>
            <a href="{{ route('admin.attendance.realtime') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">Lihat Semua</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Masuk</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Pulang</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentAttendance as $att)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                @if($att->employee?->photo)
                                    <img src="{{ asset('storage/' . $att->employee->photo) }}" class="w-7 h-7 rounded-full object-cover shrink-0" alt="">
                                @else
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($att->employee->full_name ?? '?', 0, 1) }}</div>
                                @endif
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $att->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $att->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $att->clock_in ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $att->clock_out ?? '-' }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            @if($att->is_late)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-amber-100 text-amber-800">Terlambat</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">Tepat Waktu</span>
                            @endif
                            @if($att->is_remote)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-orange-100 text-orange-700 ml-1"><span class="material-symbols-outlined text-[10px]">share_location</span></span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-12 text-gray-400 text-sm">Belum ada data absensi hari ini</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent Requests --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm animate-fade-in-up">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">description</span> Pengajuan Terbaru</h3>
            <a href="{{ route('admin.approvals.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">Kelola</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tipe</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRequests as $request)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ $request['employee_initial'] }}</div>
                                <span class="text-[13px] font-semibold text-gray-800">{{ $request['employee_name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <a href="{{ $request['url'] }}" class="inline-flex flex-col gap-1 group">
                                <span class="inline-flex w-fit items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-blue-100 text-blue-800">{{ $request['category'] }}</span>
                                <span class="text-[12px] font-semibold text-gray-600 group-hover:text-indigo-600">{{ $request['type'] }}</span>
                            </a>
                        </td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ $request['date']->format('d/m/Y') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            @if($request['status'] === 'pending')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-amber-100 text-amber-800">Pending</span>
                            @elseif($request['status'] === 'in_review')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-blue-100 text-blue-800">Diproses</span>
                            @elseif($request['status'] === 'approved')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">Disetujui</span>
                            @elseif($request['status'] === 'rejected')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-red-100 text-red-800">Ditolak</span>
                            @elseif($request['status'] === 'paid')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-purple-100 text-purple-800">Dibayar</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-gray-100 text-gray-800">{{ ucfirst($request['status']) }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-12 text-gray-400 text-sm">Tidak ada pengajuan terbaru</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
