@extends('admin.layouts.app')
@section('title', 'Persetujuan')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">task_alt</span> Persetujuan Pengajuan</h3>
    </div>
    <div class="p-5">
        {{-- Tabs --}}
        <div class="flex gap-0 border-b-2 border-gray-200 mb-5">
            <a href="{{ route('admin.approvals.index', ['tab' => 'leave']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                      {{ $tab === 'leave' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                Cuti
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === 'leave' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">{{ $leave->count() }}</span>
            </a>
            <a href="{{ route('admin.approvals.index', ['tab' => 'overtime']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                      {{ $tab === 'overtime' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                Lembur
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === 'overtime' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">{{ $overtime->count() }}</span>
            </a>
            <a href="{{ route('admin.approvals.index', ['tab' => 'attendance']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                      {{ $tab === 'attendance' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                Presensi
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === 'attendance' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">{{ $attendance->count() }}</span>
            </a>
            <a href="{{ route('admin.approvals.index', ['tab' => 'data-change']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                      {{ $tab === 'data-change' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                Perubahan Data
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === 'data-change' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">{{ $dataChange->count() }}</span>
            </a>
        </div>

        {{-- Leave Tab --}}
        @if($tab === 'leave')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tipe Cuti</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Dari</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Sampai</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Hari</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Alasan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leave as $lr)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($lr->employee->full_name ?? '?', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $lr->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $lr->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-blue-100 text-blue-800">{{ $lr->leaveType->name ?? '-' }}</span></td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ $lr->start_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ $lr->end_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] font-semibold text-gray-800 border-b border-gray-100">{{ $lr->total_days }}</td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-600 border-b border-gray-100 max-w-[200px]">{{ Str::limit($lr->reason, 60) }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex gap-2">
                                <form action="{{ route('admin.approvals.approve', ['type' => 'leave', 'id' => $lr->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Setujui cuti ini?')"><span class="material-symbols-outlined text-[14px] align-text-bottom">check_circle</span> Setujui</button>
                                </form>
                                <form action="{{ route('admin.approvals.reject', ['type' => 'leave', 'id' => $lr->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Tolak cuti ini?')"><span class="material-symbols-outlined text-[14px] align-text-bottom">cancel</span> Tolak</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Tidak ada pengajuan cuti pending</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- Overtime Tab --}}
        @if($tab === 'overtime')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Durasi</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Alasan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($overtime as $ot)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($ot->employee->full_name ?? '?', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $ot->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $ot->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ $ot->date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] font-semibold text-gray-800 border-b border-gray-100">{{ intdiv($ot->total_duration, 60) }}j {{ $ot->total_duration % 60 }}m</td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-600 border-b border-gray-100 max-w-[200px]">{{ Str::limit($ot->reason, 60) }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex gap-2">
                                <form action="{{ route('admin.approvals.approve', ['type' => 'overtime', 'id' => $ot->id]) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Setujui?')">check_circle</button></form>
                                <form action="{{ route('admin.approvals.reject', ['type' => 'overtime', 'id' => $ot->id]) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Tolak?')">cancel</button></form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-12 text-gray-400 text-sm">Tidak ada pengajuan lembur pending</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- Attendance Request Tab --}}
        @if($tab === 'attendance')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock In</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock Out</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Alasan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendance as $ar)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-amber-500 to-red-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($ar->employee->full_name ?? '?', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $ar->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $ar->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ $ar->date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $ar->clock_in ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $ar->clock_out ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-600 border-b border-gray-100 max-w-[200px]">{{ Str::limit($ar->reason, 60) }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex gap-2">
                                <form action="{{ route('admin.approvals.approve', ['type' => 'attendance', 'id' => $ar->id]) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Setujui?')">check_circle</button></form>
                                <form action="{{ route('admin.approvals.reject', ['type' => 'attendance', 'id' => $ar->id]) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Tolak?')">cancel</button></form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-12 text-gray-400 text-sm">Tidak ada pengajuan presensi pending</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- Data Change Tab --}}
        @if($tab === 'data-change')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Field</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Nilai Lama</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Nilai Baru</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataChange as $dc)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($dc->employee->full_name ?? '?', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $dc->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $dc->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-gray-100 text-gray-600">{{ $dc->field_name }}</span></td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-600 border-b border-gray-100">{{ Str::limit($dc->old_value, 40) ?: '-' }}</td>
                        <td class="px-4 py-3.5 text-[13px] font-semibold text-gray-800 border-b border-gray-100">{{ Str::limit($dc->new_value, 40) }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex gap-2">
                                <form action="{{ route('admin.approvals.approve', ['type' => 'data-change', 'id' => $dc->id]) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Setujui?')">check_circle</button></form>
                                <form action="{{ route('admin.approvals.reject', ['type' => 'data-change', 'id' => $dc->id]) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Tolak?')">cancel</button></form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-12 text-gray-400 text-sm">Tidak ada pengajuan perubahan data pending</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
