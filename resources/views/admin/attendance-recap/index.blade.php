@extends('admin.layouts.app')
@section('title', 'Rekap Presensi')

@section('content')
{{-- Stat Cards --}}
<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
    @php
        $statCards = [
            ['label' => 'Hadir', 'value' => $stats['hadir'], 'color' => 'emerald', 'icon' => 'check_circle'],
            ['label' => 'Terlambat', 'value' => $stats['terlambat'], 'color' => 'amber', 'icon' => 'schedule'],
            ['label' => 'Cuti', 'value' => $stats['cuti'], 'color' => 'blue', 'icon' => 'beach_access'],
            ['label' => 'Alpha', 'value' => $stats['alpha'], 'color' => 'red', 'icon' => 'cancel'],
            ['label' => 'Off', 'value' => $stats['off'], 'color' => 'gray', 'icon' => 'bedtime'],
            ['label' => 'Libur', 'value' => $stats['libur'], 'color' => 'rose', 'icon' => 'block'],
        ];
    @endphp
    @foreach($statCards as $card)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
            <span class="material-symbols-outlined text-2xl">{{ $card['icon'] }}</span>
            <div>
                <div class="text-[22px] font-black text-gray-900">{{ $card['value'] }}</div>
                <div class="text-[11px] text-gray-400 font-semibold">{{ $card['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">analytics</span> Rekap Presensi</h3>
    </div>

    {{-- Date + Filters --}}
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.attendance-recap.index', ['date' => $date->copy()->subDay()->format('Y-m-d'), 'department_id' => $departmentId]) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">←</a>
            <form method="GET" action="{{ route('admin.attendance-recap.index') }}" class="flex items-center gap-2" id="dateForm">
                <input type="hidden" name="department_id" value="{{ $departmentId }}">
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()"
                    class="px-3 py-1.5 text-[13px] font-bold text-gray-800 border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </form>
            <a href="{{ route('admin.attendance-recap.index', ['date' => $date->copy()->addDay()->format('Y-m-d'), 'department_id' => $departmentId]) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">→</a>
            <a href="{{ route('admin.attendance-recap.index', ['department_id' => $departmentId]) }}"
               class="px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">Hari Ini</a>

            @php
                $dayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            @endphp
            <span class="text-[13px] font-semibold text-gray-600">{{ $dayNames[$date->dayOfWeek] }}, {{ $date->format('d M Y') }}</span>

            @if($holiday)
                <span class="px-2.5 py-1 text-[10px] font-bold text-red-600 bg-red-50 border border-red-200 rounded-full"><span class="material-symbols-outlined text-[12px]">block</span> {{ $holiday->name }}</span>
            @endif
        </div>
        <form method="GET" action="{{ route('admin.attendance-recap.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
            <select name="department_id" onchange="this.form.submit()" class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_6px_center] bg-no-repeat bg-[length:14px] pr-7 focus:border-indigo-500">
                <option value="">Semua Departemen</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_6px_center] bg-no-repeat bg-[length:14px] pr-7 focus:border-indigo-500">
                <option value="">Semua Status</option>
                <option value="present" {{ $filterStatus === 'present' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">check_circle</span> Hadir</option>
                <option value="late" {{ $filterStatus === 'late' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">schedule</span> Terlambat</option>
                <option value="leave" {{ $filterStatus === 'leave' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">beach_access</span> Cuti</option>
                <option value="absent" {{ $filterStatus === 'absent' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">cancel</span> Alpha</option>
                <option value="off" {{ $filterStatus === 'off' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">bedtime</span> Off</option>
                <option value="scheduled" {{ $filterStatus === 'scheduled' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">event</span> Terjadwal</option>
            </select>
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama..." class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none w-[140px] focus:border-indigo-500">
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">#</th>
                    <th class="py-3 px-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                    <th class="py-3 px-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Departemen</th>
                    <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Shift</th>
                    <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Clock In</th>
                    <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Clock Out</th>
                    <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $row)
                @php
                    $statusBadge = match($row['status']) {
                        'present' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'late' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'leave' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'absent' => 'bg-red-50 text-red-700 border-red-200',
                        'off' => 'bg-gray-100 text-gray-500 border-gray-200',
                        'holiday' => 'bg-red-50 text-red-600 border-red-200',
                        'scheduled' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
                        default => 'bg-gray-50 text-gray-400 border-gray-200',
                    };
                @endphp
                <tr class="border-b border-gray-50 hover:bg-gray-50/30 transition-all {{ $row['status'] === 'absent' ? 'bg-red-50/30' : '' }} {{ $row['attendance'] ? 'cursor-pointer' : '' }}" {{ $row['attendance'] ? 'onclick=openDetail(' . $row['attendance']->id . ')' : '' }}>
                    <td class="py-2.5 px-4 text-[12px] text-gray-400">{{ $i + 1 }}</td>
                    <td class="py-2.5 px-4">
                        <div class="flex items-center gap-2.5">
                            @if($row['employee']->photo)
                                <img src="{{ asset('storage/' . $row['employee']->photo) }}" class="w-8 h-8 rounded-full object-cover shrink-0" alt="">
                            @else
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">
                                    {{ substr($row['employee']->full_name, 0, 1) }}
                                </div>
                            @endif
                            <div>
                                <a href="{{ route('admin.attendance-recap.employee-detail', $row['employee']->id) }}" class="text-[12px] font-semibold text-indigo-600 hover:underline" onclick="event.stopPropagation()">{{ $row['employee']->full_name }}</a>
                                <div class="text-[10px] text-gray-400">{{ $row['employee']->position ?? $row['employee']->employee_code }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="py-2.5 px-4 text-[12px] text-gray-500">{{ $row['employee']->department->name ?? '-' }}</td>
                    <td class="py-2.5 px-4 text-center">
                        @if($row['shift'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold text-white" style="background-color: {{ $row['shift']->color }}">
                                {{ $row['shift']->name }}
                                @if(!$row['shift']->is_off) ({{ substr($row['shift']->start_time, 0, 5) }}) @endif
                            </span>
                        @elseif($row['status'] === 'holiday')
                            <span class="text-[10px] font-semibold text-red-500"><span class="material-symbols-outlined text-[12px] align-text-bottom">block</span> Libur</span>
                        @else
                            <span class="text-[10px] text-gray-300">-</span>
                        @endif
                    </td>
                    <td class="py-2.5 px-4 text-center">
                        @if($row['clock_in'])
                            <span class="text-[13px] font-bold text-gray-800">{{ substr($row['clock_in'], 0, 5) }}</span>
                        @else
                            <span class="text-[10px] text-gray-300">-</span>
                        @endif
                    </td>
                    <td class="py-2.5 px-4 text-center">
                        @if($row['clock_out'])
                            <span class="text-[13px] font-bold text-gray-800">{{ substr($row['clock_out'], 0, 5) }}</span>
                        @else
                            <span class="text-[10px] text-gray-300">-</span>
                        @endif
                    </td>
                    <td class="py-2.5 px-4 text-center">
                        <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $statusBadge }}">
                            {{ $row['status_label'] }}
                        </span>
                        @if($row['attendance'] && $row['attendance']->is_remote)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-orange-100 text-orange-700 mt-0.5"><span class="material-symbols-outlined text-[10px]">share_location</span> Remote</span>
                        @endif
                    </td>
                    <td class="py-2.5 px-4 text-center">
                        <div class="flex items-center justify-center gap-1">
                            @if($row['attendance'])
                                <button onclick="event.stopPropagation(); openDetail({{ $row['attendance']->id }})"
                                    class="px-2 py-1.5 text-[10px] font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-all cursor-pointer">
                                    <span class="material-symbols-outlined text-[14px] align-text-bottom">visibility</span>
                                </button>
                            @endif
                            <button onclick="event.stopPropagation(); openEdit({{ $row['employee']->id }}, '{{ $row['employee']->full_name }}', '{{ $row['clock_in'] ? substr($row['clock_in'], 0, 5) : '' }}', '{{ $row['clock_out'] ? substr($row['clock_out'], 0, 5) : '' }}', '{{ $row['attendance']?->status ?? 'present' }}')"
                                class="px-2 py-1.5 text-[10px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-10 text-center text-gray-400 text-sm">Tidak ada data presensi.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Edit Offcanvas --}}
<div id="editOffcanvas" class="fixed inset-0 z-50 hidden">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 transition-opacity" onclick="closeEdit()"></div>
    {{-- Panel --}}
    <div id="editPanel" class="absolute top-0 right-0 h-full w-[400px] max-w-full bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out overflow-y-auto">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span> Edit Presensi</h3>
                <p class="text-[12px] text-gray-400 mt-0.5" id="editEmpName"></p>
            </div>
            <button onclick="closeEdit()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all cursor-pointer text-[16px]">✕</button>
        </div>
        <form action="{{ route('admin.attendance-recap.update') }}" method="POST" class="p-6">
            @csrf
            <input type="hidden" name="employee_id" id="editEmpId">
            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

            <div class="mb-4">
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Tanggal</label>
                <div class="px-3 py-2 text-[13px] text-gray-800 bg-gray-50 border border-gray-200 rounded-lg font-semibold">
                    {{ $date->format('d M Y') }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Clock In</label>
                    <input type="time" name="clock_in" id="editClockIn" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Clock Out</label>
                    <input type="time" name="clock_out" id="editClockOut" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Status</label>
                <select name="status" id="editStatus" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_8px_center] bg-no-repeat bg-[length:14px] pr-8 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                    <option value="present"><span class="material-symbols-outlined text-[14px] align-text-bottom">check_circle</span> Hadir</option>
                    <option value="absent"><span class="material-symbols-outlined text-[14px] align-text-bottom">cancel</span> Alpha</option>
                    <option value="leave"><span class="material-symbols-outlined text-[14px] align-text-bottom">beach_access</span> Cuti</option>
                    <option value="holiday"><span class="material-symbols-outlined text-[12px] align-text-bottom">block</span> Libur</option>
                </select>
            </div>

            <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200">
                <p class="text-[11px] text-amber-700 font-medium"><span class="material-symbols-outlined text-[14px] align-text-bottom">schedule</span> Status <strong>Terlambat</strong> dihitung otomatis berdasarkan jam Clock In vs jam masuk shift.</p>
            </div>

            <button type="submit" class="w-full px-4 py-3 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg hover:from-indigo-700 hover:to-indigo-600 transition-all cursor-pointer shadow-sm">
                <span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<script>
function openEdit(empId, name, clockIn, clockOut, status) {
    document.getElementById('editEmpId').value = empId;
    document.getElementById('editEmpName').textContent = name;
    document.getElementById('editClockIn').value = clockIn;
    document.getElementById('editClockOut').value = clockOut;
    document.getElementById('editStatus').value = status;

    const offcanvas = document.getElementById('editOffcanvas');
    const panel = document.getElementById('editPanel');
    offcanvas.classList.remove('hidden');
    requestAnimationFrame(() => {
        panel.classList.remove('translate-x-full');
        panel.classList.add('translate-x-0');
    });
}

function closeEdit() {
    const panel = document.getElementById('editPanel');
    panel.classList.remove('translate-x-0');
    panel.classList.add('translate-x-full');
    setTimeout(() => {
        document.getElementById('editOffcanvas').classList.add('hidden');
    }, 300);
}
</script>

{{-- Detail Offcanvas --}}
<div id="detailOffcanvas" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 transition-opacity" onclick="closeDetail()"></div>
    <div id="detailPanel" class="absolute top-0 right-0 h-full w-[480px] max-w-full bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out overflow-y-auto">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[14px] align-text-bottom">info</span> Detail Presensi</h3>
                <p class="text-[12px] text-gray-400 mt-0.5" id="detailName"></p>
            </div>
            <button onclick="closeDetail()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all cursor-pointer text-[16px]">✕</button>
        </div>
        <div class="p-6 space-y-5" id="detailContent"></div>
    </div>
</div>

@php
    $attData = collect($rows)->filter(fn($r) => $r['attendance'])->mapWithKeys(function($row) {
        $att = $row['attendance'];
        return [$att->id => [
            'id' => $att->id,
            'name' => $row['employee']->full_name,
            'department' => $row['employee']->department->name ?? '-',
            'clock_in' => $att->clock_in,
            'clock_out' => $att->clock_out,
            'clock_in_lat' => $att->clock_in_lat,
            'clock_in_lng' => $att->clock_in_lng,
            'clock_out_lat' => $att->clock_out_lat,
            'clock_out_lng' => $att->clock_out_lng,
            'clock_in_photo' => $att->clock_in_photo ? asset('storage/' . $att->clock_in_photo) : null,
            'clock_out_photo' => $att->clock_out_photo ? asset('storage/' . $att->clock_out_photo) : null,
            'is_late' => $att->is_late,
            'is_remote' => $att->is_remote,
            'remote_notes' => $att->remote_notes,
        ]];
    });
@endphp
<script>
const recapData = @json($attData);
let detailMap = null;

function openDetail(id) {
    const att = recapData[id];
    if (!att) return;
    document.getElementById('detailName').textContent = att.name + ' — ' + att.department;

    let html = '';
    html += '<div class="flex items-center gap-2 flex-wrap">';
    if (att.is_late) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">⏰ Terlambat</span>';
    else html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">✅ Tepat Waktu</span>';
    if (att.is_remote) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-orange-100 text-orange-700">📍 Remote</span>';
    html += '</div>';

    html += '<div class="grid grid-cols-2 gap-3">';
    html += buildTimeCard('Clock In', att.clock_in, att.clock_in_photo, 'emerald');
    html += buildTimeCard('Clock Out', att.clock_out, att.clock_out_photo, 'blue');
    html += '</div>';

    if (att.clock_in_lat && att.clock_in_lng) {
        html += '<div><p class="text-[12px] font-semibold text-gray-600 mb-2"><span class="material-symbols-outlined text-[14px] align-text-bottom">map</span> Lokasi GPS</p>';
        html += '<div id="detailMapContainer" class="w-full h-[220px] rounded-xl border border-gray-200 overflow-hidden"></div>';
        html += '<div class="mt-2 flex items-center gap-3 text-[11px] text-gray-400">';
        html += '<span>📥 In: ' + Number(att.clock_in_lat).toFixed(6) + ', ' + Number(att.clock_in_lng).toFixed(6) + '</span>';
        if (att.clock_out_lat) html += '<span>📤 Out: ' + Number(att.clock_out_lat).toFixed(6) + ', ' + Number(att.clock_out_lng).toFixed(6) + '</span>';
        html += '</div></div>';
    }

    if (att.remote_notes) {
        html += '<div class="p-3 rounded-xl bg-amber-50 border border-amber-200">';
        html += '<p class="text-[11px] font-semibold text-amber-700 mb-1">📝 Catatan Remote</p>';
        html += '<p class="text-[13px] text-gray-700">' + att.remote_notes + '</p></div>';
    }

    document.getElementById('detailContent').innerHTML = html;
    const offcanvas = document.getElementById('detailOffcanvas');
    const panel = document.getElementById('detailPanel');
    offcanvas.classList.remove('hidden');
    requestAnimationFrame(() => { panel.classList.remove('translate-x-full'); panel.classList.add('translate-x-0'); });

    setTimeout(() => {
        const mapEl = document.getElementById('detailMapContainer');
        if (mapEl && att.clock_in_lat) {
            if (detailMap) { detailMap.remove(); detailMap = null; }
            detailMap = L.map('detailMapContainer').setView([att.clock_in_lat, att.clock_in_lng], 16);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: '© OSM' }).addTo(detailMap);
            L.marker([att.clock_in_lat, att.clock_in_lng]).addTo(detailMap).bindPopup('<b>Clock In</b><br>' + (att.clock_in || '-'));
            if (att.clock_out_lat && att.clock_out_lng) {
                L.marker([att.clock_out_lat, att.clock_out_lng]).addTo(detailMap).bindPopup('<b>Clock Out</b><br>' + (att.clock_out || '-'));
                detailMap.fitBounds([[att.clock_in_lat, att.clock_in_lng], [att.clock_out_lat, att.clock_out_lng]], { padding: [40, 40] });
            }
        }
    }, 100);
}

function buildTimeCard(label, time, photo, color) {
    let h = '<div class="rounded-xl border border-gray-200 overflow-hidden">';
    h += photo ? '<img src="' + photo + '" class="w-full h-[140px] object-cover">' : '<div class="w-full h-[140px] bg-gray-100 flex items-center justify-center"><span class="material-symbols-outlined text-[36px] text-gray-300">no_photography</span></div>';
    h += '<div class="px-3 py-2.5 bg-' + color + '-50 border-t border-' + color + '-100">';
    h += '<div class="text-[10px] font-bold text-' + color + '-600 uppercase tracking-wider">' + label + '</div>';
    h += '<div class="text-[16px] font-black text-gray-800">' + (time ? time.substring(0,5) : '-') + '</div></div></div>';
    return h;
}

function closeDetail() {
    const panel = document.getElementById('detailPanel');
    panel.classList.remove('translate-x-0'); panel.classList.add('translate-x-full');
    setTimeout(() => { document.getElementById('detailOffcanvas').classList.add('hidden'); if (detailMap) { detailMap.remove(); detailMap = null; } }, 300);
}
</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endsection
