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
            <a href="{{ route('admin.approvals.index', ['tab' => 'budget']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                      {{ $tab === 'budget' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                Anggaran
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === 'budget' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">{{ $budget->count() }}</span>
            </a>
            <a href="{{ route('admin.approvals.index', ['tab' => 'travel_report']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                      {{ $tab === 'travel_report' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                LHP
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === 'travel_report' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">{{ $travelReport->count() }}</span>
            </a>
            @if($admin->role === 'superadmin')
            <a href="{{ route('admin.approvals.index', ['tab' => 'data-change']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200 flex items-center gap-2
                      {{ $tab === 'data-change' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                Perubahan Data
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full {{ $tab === 'data-change' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">{{ $dataChange->count() }}</span>
            </a>
            @endif
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
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tipe</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Detail</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Alasan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($overtime as $ot)
                    <tr class="hover:bg-gray-50 transition-colors" id="ot-row-{{ $ot->id }}">
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
                        <td class="px-4 py-3.5 text-center border-b border-gray-100">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $ot->overtime_type === 'holiday' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                                {{ $ot->overtime_type === 'holiday' ? 'Libur' : 'Kerja' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="text-[13.5px] font-semibold text-gray-800">{{ $ot->total_duration_formatted }}</div>
                            @if($ot->overtime_type === 'holiday' && $ot->planned_start)
                                <div class="text-[11px] text-gray-400">{{ substr($ot->planned_start, 0, 5) }} - {{ substr($ot->planned_end, 0, 5) }}</div>
                            @else
                                @if(($ot->pre_shift_duration ?? 0) > 0)
                                    <div class="text-[11px] text-gray-400">Pre: {{ $ot->pre_shift_duration }}m (break {{ $ot->pre_shift_break ?? 0 }}m)</div>
                                @endif
                                @if(($ot->post_shift_duration ?? 0) > 0)
                                    <div class="text-[11px] text-gray-400">Post: {{ $ot->post_shift_duration }}m (break {{ $ot->post_shift_break ?? 0 }}m)</div>
                                @endif
                            @endif
                            @if(($ot->break_duration ?? 0) > 0)
                                <div class="text-[11px] text-amber-600">Break: {{ $ot->break_duration }}m</div>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-[13px] text-gray-600 border-b border-gray-100 max-w-[200px]">{{ Str::limit($ot->reason, 60) }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex flex-col gap-1.5">
                                <button type="button" onclick="document.getElementById('ot-adjust-{{ $ot->id }}').classList.toggle('hidden')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                                    <span class="material-symbols-outlined text-[14px]">tune</span> Sesuaikan
                                </button>
                                <div class="flex gap-1.5">
                                    <form action="{{ route('admin.approvals.approve', ['type' => 'overtime', 'id' => $ot->id]) }}" method="POST" id="ot-approve-form-{{ $ot->id }}">
                                        @csrf
                                        <input type="hidden" name="adjusted_duration" id="ot-adj-dur-{{ $ot->id }}">
                                        <input type="hidden" name="adjusted_break" id="ot-adj-brk-{{ $ot->id }}">
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="syncAdjust({{ $ot->id }}); return confirm('Setujui lembur ini?')">
                                            <span class="material-symbols-outlined text-[14px] align-text-bottom">check_circle</span> Setujui
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.approvals.reject', ['type' => 'overtime', 'id' => $ot->id]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Tolak lembur ini?')">
                                            <span class="material-symbols-outlined text-[14px] align-text-bottom">cancel</span> Tolak
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    {{-- Adjust row (hidden by default) --}}
                    <tr id="ot-adjust-{{ $ot->id }}" class="hidden bg-indigo-50/50">
                        <td colspan="6" class="px-4 py-3 border-b border-gray-200">
                            <div class="flex flex-wrap items-end gap-4">
                                <div class="text-[11px] font-bold text-indigo-600 uppercase">Sesuaikan Durasi & Break</div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-0.5">Durasi (menit)</label>
                                    <input type="number" min="0" id="ot-dur-input-{{ $ot->id }}" value="{{ $ot->total_duration }}"
                                        class="w-24 px-2.5 py-1.5 text-[12px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-0.5">Break (menit)</label>
                                    <input type="number" min="0" id="ot-brk-input-{{ $ot->id }}" value="{{ $ot->break_duration ?? 0 }}"
                                        class="w-24 px-2.5 py-1.5 text-[12px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
                                </div>
                                <div class="text-[11px] text-gray-500">
                                    OT Terhitung = <span class="font-bold text-amber-600" id="ot-calc-{{ $ot->id }}">{{ max(0, $ot->total_duration - ($ot->break_duration ?? 0)) }}m</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-12 text-gray-400 text-sm">Tidak ada pengajuan lembur pending</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <script>
        function syncAdjust(id) {
            const durInput = document.getElementById('ot-dur-input-' + id);
            const brkInput = document.getElementById('ot-brk-input-' + id);
            const adjRow = document.getElementById('ot-adjust-' + id);

            if (adjRow && !adjRow.classList.contains('hidden')) {
                document.getElementById('ot-adj-dur-' + id).value = durInput.value;
                document.getElementById('ot-adj-brk-' + id).value = brkInput.value;
            }
        }

        document.querySelectorAll('[id^="ot-dur-input-"], [id^="ot-brk-input-"]').forEach(input => {
            input.addEventListener('input', function() {
                const id = this.id.replace('ot-dur-input-', '').replace('ot-brk-input-', '');
                const dur = parseInt(document.getElementById('ot-dur-input-' + id)?.value || 0);
                const brk = parseInt(document.getElementById('ot-brk-input-' + id)?.value || 0);
                const calc = document.getElementById('ot-calc-' + id);
                if (calc) calc.textContent = Math.max(0, dur - brk) + 'm';
            });
        });
        </script>
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

        {{-- Budget Tab --}}
        @if($tab === 'budget')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Judul</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tipe</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Total</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($budget as $br)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($br->employee->full_name ?? '?', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $br->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $br->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="text-[13px] font-medium text-gray-800 max-w-[200px] truncate">{{ $br->title }}</div>
                            <div class="text-[11px] text-gray-400">{{ $br->items->count() }} item</div>
                        </td>
                        <td class="px-4 py-3.5 text-center border-b border-gray-100">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $br->type === 'budget' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                {{ $br->type === 'budget' ? 'Budget' : 'Reimburse' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right text-[13px] font-semibold text-gray-900 border-b border-gray-100">Rp {{ number_format($br->total_amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex gap-2">
                                <form action="{{ route('admin.approvals.approve', ['type' => 'budget', 'id' => $br->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Setujui pengajuan anggaran ini?')"><span class="material-symbols-outlined text-[14px] align-text-bottom">check_circle</span> Setujui</button>
                                </form>
                                <form action="{{ route('admin.approvals.reject', ['type' => 'budget', 'id' => $br->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer" onclick="return confirm('Tolak pengajuan anggaran ini?')"><span class="material-symbols-outlined text-[14px] align-text-bottom">cancel</span> Tolak</button>
                                </form>
                                <a href="{{ route('admin.budget-requests.show', $br->id) }}" class="inline-flex items-center px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">Detail</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-12 text-gray-400 text-sm">Tidak ada pengajuan anggaran pending</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- Travel Report (LHP) Tab --}}
        @if($tab === 'travel_report')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Kota Tujuan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($travelReport as $tr)
                    <tr class="hover:bg-gray-50/50 border-b border-gray-100">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-teal-400 to-teal-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($tr->employee->full_name ?? '', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $tr->employee->full_name ?? '-' }}</div>
                                    <div class="text-[10.5px] text-gray-400">{{ $tr->employee->department->name ?? '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-[13px] text-gray-700 font-medium">{{ $tr->destination_city }}</td>
                        <td class="px-4 py-3 text-[12px] text-gray-500 whitespace-nowrap">{{ $tr->departure_date->format('d M') }} — {{ $tr->return_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            @php
                                $sBg = match($tr->status) {
                                    'approved' => 'bg-emerald-100 text-emerald-700',
                                    'in_review' => 'bg-blue-100 text-blue-700',
                                    'rejected' => 'bg-red-100 text-red-700',
                                    default => 'bg-amber-100 text-amber-700',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sBg }}">{{ strtoupper($tr->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admin.travel-reports.show', $tr->id) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-all">Detail</a>
                                <form method="POST" action="{{ route('admin.approvals.approve', ['type' => 'travel_report', 'id' => $tr->id]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-600 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-all cursor-pointer">✓ Setujui</button>
                                </form>
                                <form method="POST" action="{{ route('admin.approvals.reject', ['type' => 'travel_report', 'id' => $tr->id]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all cursor-pointer">✗ Tolak</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Tidak ada LHP yang menunggu persetujuan.</td></tr>
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
