@extends('admin.layouts.app')
@section('title', 'Detail Presensi — ' . $employee->full_name)

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManageAttendance = $adminPermission->can($currentAdmin, 'attendance.manage');
@endphp
{{-- Header --}}
<div class="flex items-center justify-between mb-5">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.attendance-recap.index') }}" class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div class="flex items-center gap-3">
            @if($employee->photo)
                <img src="{{ asset('storage/' . $employee->photo) }}" class="w-11 h-11 rounded-full object-cover border-2 border-white shadow" alt="">
            @else
                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-500 flex items-center justify-center text-white text-[15px] font-bold shadow">
                    {{ substr($employee->full_name, 0, 1) }}
                </div>
            @endif
            <div>
                <h2 class="text-[16px] font-bold text-gray-900">{{ $employee->full_name }}</h2>
                <p class="text-[12px] text-gray-400">{{ $employee->department->name ?? '-' }} · {{ $employee->position ?? $employee->employee_code }}</p>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.attendance-recap.employee-detail', ['id' => $employee->id, 'period' => $period->copy()->subMonth()->format('Y-m')]) }}"
            class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">chevron_left</span>
        </a>
        <span class="text-[14px] font-bold text-gray-800 min-w-[120px] text-center">{{ $period->translatedFormat('F Y') }}</span>
        <a href="{{ route('admin.attendance-recap.employee-detail', ['id' => $employee->id, 'period' => $period->copy()->addMonth()->format('Y-m')]) }}"
            class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </a>
    </div>
</div>

{{-- Stat Cards --}}
<div class="grid grid-cols-4 lg:grid-cols-8 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Hadir</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['hadir'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Terlambat</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['terlambat'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-teal-600 uppercase tracking-wider">WFH</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['wfh'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-violet-600 uppercase tracking-wider">Sakit</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['sakit'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-red-600 uppercase tracking-wider">Alpha</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['alpha'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Cuti</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['cuti'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Off</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['off'] }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <div class="text-[10px] font-bold text-pink-600 uppercase tracking-wider">Libur</div>
        <div class="text-[22px] font-black text-gray-800">{{ $stats['libur'] }}</div>
    </div>
</div>

{{-- Template info --}}
@if($employee->scheduleTemplate)
    <div class="mb-4 px-4 py-2.5 bg-indigo-50 border border-indigo-200 rounded-xl inline-flex items-center gap-2">
        <span class="material-symbols-outlined text-[14px] text-indigo-500">calendar_month</span>
        <span class="text-[12px] font-semibold text-indigo-700">Template: {{ $employee->scheduleTemplate->name }}</span>
    </div>
@endif

{{-- Monthly Table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tgl</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Hari</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Shift</th>
                    <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock In</th>
                    <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock Out</th>
                    <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Foto</th>
                    <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                @php
                    $isToday = $row['date']->isToday();
                    $rowBg = $isToday ? 'bg-indigo-50/40' : ($row['status'] === 'absent' ? 'bg-red-50/30' : ($row['status'] === 'sick' ? 'bg-violet-50/30' : ($row['status'] === 'holiday' ? 'bg-pink-50/30' : '')));
                    $statusBadge = match($row['status']) {
                        'present' => 'bg-emerald-100 text-emerald-700',
                        'late' => 'bg-amber-100 text-amber-700',
                        'wfh' => 'bg-teal-100 text-teal-700',
                        'sick' => 'bg-violet-100 text-violet-700',
                        'absent' => 'bg-red-100 text-red-700',
                        'leave' => 'bg-blue-100 text-blue-700',
                        'off' => 'bg-gray-100 text-gray-500',
                        'holiday' => 'bg-pink-100 text-pink-700',
                        default => 'bg-gray-50 text-gray-400',
                    };
                    $manualStatusValue = $row['attendance']?->status ?? match($row['status']) {
                        'absent' => 'absent',
                        'wfh' => 'wfh',
                        'sick' => 'sick',
                        'leave' => 'leave',
                        'holiday' => 'holiday',
                        default => 'present',
                    };
                    $clockInValue = $row['attendance']?->clock_in ? substr($row['attendance']->clock_in, 0, 5) : '';
                    $clockOutValue = $row['attendance']?->clock_out ? substr($row['attendance']->clock_out, 0, 5) : '';
                @endphp
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-all {{ $rowBg }} {{ $row['attendance'] ? 'cursor-pointer' : '' }}" {{ $row['attendance'] ? 'onclick=openDetail(' . $row['attendance']->id . ')' : '' }}>
                    <td class="px-3 py-2.5 text-[13px] font-bold text-gray-800 {{ $isToday ? 'text-indigo-600' : '' }}">{{ $row['day'] }}</td>
                    <td class="px-3 py-2.5 text-[12px] text-gray-500 {{ $isToday ? 'font-semibold text-indigo-600' : '' }}">{{ $row['day_name'] }}</td>
                    <td class="px-3 py-2.5">
                        @if($row['holiday'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold text-white bg-red-500">
                                🎉 LIBUR
                            </span>
                        @elseif($row['shift'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold text-white" style="background-color: {{ $row['shift']->color }}">
                                {{ $row['shift']->name }}
                                @if(!$row['shift']->is_off && $row['shift']->start_time)
                                    <span class="ml-1 opacity-80">{{ substr($row['shift']->start_time, 0, 5) }}</span>
                                @endif
                            </span>
                        @else
                            <span class="text-[10px] text-gray-300">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <div class="inline-flex items-center justify-center gap-1.5" data-inline-clock-cell onclick="event.stopPropagation()">
                            <div class="inline-flex items-center justify-center gap-1.5" data-clock-display>
                                @if($row['attendance'] && $row['attendance']->clock_in)
                                    <span class="text-[13px] font-bold text-gray-800">{{ $clockInValue }}</span>
                                @else
                                    <span class="text-[10px] text-gray-300">-</span>
                                @endif
                                @if($canManageAttendance)
                                    <button type="button"
                                        onclick="startInlineClockEdit(this)"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition hover:bg-indigo-50 hover:text-indigo-600"
                                        title="Edit Clock In" aria-label="Edit Clock In">
                                        <span class="material-symbols-outlined text-[14px]">edit</span>
                                    </button>
                                @endif
                            </div>
                            @if($canManageAttendance)
                                <form action="{{ route('admin.attendance-recap.update') }}" method="POST" class="hidden items-center justify-center gap-1.5" data-clock-form>
                                    @csrf
                                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                    <input type="hidden" name="date" value="{{ $row['date']->format('Y-m-d') }}">
                                    <input type="hidden" name="clock_out" value="{{ $clockOutValue }}">
                                    <input type="hidden" name="status" value="{{ $manualStatusValue }}">
                                    <input type="time" name="clock_in" value="{{ $clockInValue }}" data-original-value="{{ $clockInValue }}" class="w-[86px] rounded-md border border-indigo-200 px-2 py-1 text-[12px] font-semibold text-gray-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-indigo-600 text-white transition hover:bg-indigo-700" title="Simpan Clock In" aria-label="Simpan Clock In">
                                        <span class="material-symbols-outlined text-[15px]">check</span>
                                    </button>
                                    <button type="button" onclick="cancelInlineClockEdit(this)" class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-500 transition hover:bg-gray-200" title="Batal" aria-label="Batal">
                                        <span class="material-symbols-outlined text-[15px]">close</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <div class="inline-flex items-center justify-center gap-1.5" data-inline-clock-cell onclick="event.stopPropagation()">
                            <div class="inline-flex items-center justify-center gap-1.5" data-clock-display>
                                @if($row['attendance'] && $row['attendance']->clock_out)
                                    <span class="text-[13px] font-bold text-gray-800">{{ $clockOutValue }}</span>
                                @else
                                    <span class="text-[10px] text-gray-300">-</span>
                                @endif
                                @if($canManageAttendance)
                                    <button type="button"
                                        onclick="startInlineClockEdit(this)"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition hover:bg-indigo-50 hover:text-indigo-600"
                                        title="Edit Clock Out" aria-label="Edit Clock Out">
                                        <span class="material-symbols-outlined text-[14px]">edit</span>
                                    </button>
                                @endif
                            </div>
                            @if($canManageAttendance)
                                <form action="{{ route('admin.attendance-recap.update') }}" method="POST" class="hidden items-center justify-center gap-1.5" data-clock-form>
                                    @csrf
                                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                    <input type="hidden" name="date" value="{{ $row['date']->format('Y-m-d') }}">
                                    <input type="hidden" name="clock_in" value="{{ $clockInValue }}">
                                    <input type="hidden" name="status" value="{{ $manualStatusValue }}">
                                    <input type="time" name="clock_out" value="{{ $clockOutValue }}" data-original-value="{{ $clockOutValue }}" class="w-[86px] rounded-md border border-indigo-200 px-2 py-1 text-[12px] font-semibold text-gray-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-indigo-600 text-white transition hover:bg-indigo-700" title="Simpan Clock Out" aria-label="Simpan Clock Out">
                                        <span class="material-symbols-outlined text-[15px]">check</span>
                                    </button>
                                    <button type="button" onclick="cancelInlineClockEdit(this)" class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-500 transition hover:bg-gray-200" title="Batal" aria-label="Batal">
                                        <span class="material-symbols-outlined text-[15px]">close</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        @if($row['attendance'] && $row['attendance']->clock_in_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($row['attendance']->clock_in_photo))
                            <img src="{{ asset('storage/' . $row['attendance']->clock_in_photo) }}" class="w-7 h-7 rounded-md object-cover mx-auto border border-gray-200" alt="">
                        @elseif($row['attendance'] && $row['attendance']->clock_in_photo)
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-semibold bg-blue-50 text-blue-700">Arsip</span>
                        @else
                            <span class="text-[10px] text-gray-300">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusBadge }}">
                            {{ $row['status_label'] }}
                        </span>
                        @if($row['attendance'] && $row['attendance']->is_remote)
                            <span class="inline-flex items-center px-1 py-0.5 rounded-full text-[9px] font-semibold bg-orange-100 text-orange-700 ml-0.5">
                                <span class="material-symbols-outlined text-[10px]">share_location</span>
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

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
            'date' => $row['date']->format('d/m/Y'),
            'day_name' => $row['day_name'],
            'status' => $row['status'],
            'status_label' => $row['status_label'],
            'clock_in' => $att->clock_in,
            'clock_out' => $att->clock_out,
            'clock_in_lat' => $att->clock_in_lat,
            'clock_in_lng' => $att->clock_in_lng,
            'clock_out_lat' => $att->clock_out_lat,
            'clock_out_lng' => $att->clock_out_lng,
            'clock_in_photo' => $att->clock_in_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($att->clock_in_photo) ? asset('storage/' . $att->clock_in_photo) : null,
            'clock_out_photo' => $att->clock_out_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($att->clock_out_photo) ? asset('storage/' . $att->clock_out_photo) : null,
            'clock_in_photo_archived' => $att->clock_in_photo && !\Illuminate\Support\Facades\Storage::disk('public')->exists($att->clock_in_photo),
            'clock_out_photo_archived' => $att->clock_out_photo && !\Illuminate\Support\Facades\Storage::disk('public')->exists($att->clock_out_photo),
            'is_late' => $att->is_late,
            'is_remote' => $att->is_remote,
            'remote_notes' => $att->remote_notes,
        ]];
    });
@endphp
<script>
const attData = @json($attData);
let detailMap = null;

@if($canManageAttendance)
function closeInlineClockCell(cell) {
    const form = cell.querySelector('[data-clock-form]');
    const display = cell.querySelector('[data-clock-display]');
    const input = form?.querySelector('input[type="time"]');

    if (input) {
        input.value = input.dataset.originalValue || '';
    }

    form?.classList.add('hidden');
    form?.classList.remove('inline-flex');
    display?.classList.remove('hidden');
}

function startInlineClockEdit(button) {
    const cell = button.closest('[data-inline-clock-cell]');

    document.querySelectorAll('[data-inline-clock-cell]').forEach((otherCell) => {
        if (otherCell !== cell) {
            closeInlineClockCell(otherCell);
        }
    });

    cell.querySelector('[data-clock-display]')?.classList.add('hidden');
    const form = cell.querySelector('[data-clock-form]');
    form?.classList.remove('hidden');
    form?.classList.add('inline-flex');

    const input = form?.querySelector('input[type="time"]');
    input?.focus();
    input?.select();
}

function cancelInlineClockEdit(button) {
    const cell = button.closest('[data-inline-clock-cell]');
    closeInlineClockCell(cell);
}
@endif

function openDetail(id) {
    const att = attData[id];
    if (!att) return;
    document.getElementById('detailName').textContent = '{{ $employee->full_name }}' + ' — ' + att.date + ' (' + att.day_name + ')';

    let html = '';
    html += '<div class="flex items-center gap-2 flex-wrap">';
    if (att.is_late) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">⏰ Terlambat</span>';
    else html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">✅ Tepat Waktu</span>';
    if (att.is_remote) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-orange-100 text-orange-700">📍 Remote</span>';
    html += '</div>';

    html += '<div class="grid grid-cols-2 gap-3">';
    html += buildTimeCard('Clock In', att.clock_in, att.clock_in_photo, 'emerald', att.clock_in_photo_archived);
    html += buildTimeCard('Clock Out', att.clock_out, att.clock_out_photo, 'blue', att.clock_out_photo_archived);
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

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function buildAttendanceStatusBadges(att) {
    let status = att.status || (att.is_late ? 'late' : 'present');
    if (status === 'present' && att.is_late) status = 'late';

    let badgeClass = 'bg-emerald-100 text-emerald-800';
    let icon = 'check_circle';
    let label = att.status_label || 'Hadir';

    if (status === 'sick') {
        badgeClass = 'bg-violet-100 text-violet-800';
        icon = 'sick';
        label = att.status_label || 'Sakit';
    } else if (status === 'late') {
        badgeClass = 'bg-amber-100 text-amber-800';
        icon = 'schedule';
        label = att.status_label || 'Terlambat';
    } else if (status === 'absent') {
        badgeClass = 'bg-red-100 text-red-800';
        icon = 'cancel';
        label = att.status_label || 'Alpha';
    } else if (status === 'wfh') {
        badgeClass = 'bg-teal-100 text-teal-800';
        icon = 'home_work';
        label = att.status_label || 'WFH';
    } else if (status === 'leave') {
        badgeClass = 'bg-blue-100 text-blue-800';
        icon = 'beach_access';
    } else if (status === 'holiday') {
        badgeClass = 'bg-rose-100 text-rose-800';
        icon = 'block';
    }

    let html = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold ' + badgeClass + '"><span class="material-symbols-outlined text-[13px]">' + icon + '</span>' + escapeHtml(label) + '</span>';
    if (att.is_remote) {
        html += '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-orange-100 text-orange-700"><span class="material-symbols-outlined text-[13px]">share_location</span>Remote</span>';
    }

    return html;
}

function hasClockEvidence(att) {
    return Boolean(att.clock_in || att.clock_out || att.clock_in_photo || att.clock_out_photo || att.clock_in_photo_archived || att.clock_out_photo_archived);
}

function buildNoClockDetail(att) {
    return '<div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-[13px] font-semibold text-violet-700">Tidak ada clock in/out untuk status ' + escapeHtml(att.status_label || 'ini') + '.</div>';
}

function openDetail(id) {
    const att = attData[id];
    if (!att) return;
    document.getElementById('detailName').textContent = '{{ $employee->full_name }}' + ' - ' + att.date + ' (' + att.day_name + ')';

    let html = '';
    html += '<div class="flex items-center gap-2 flex-wrap">';
    html += buildAttendanceStatusBadges(att);
    html += '</div>';

    if (hasClockEvidence(att) || att.status === 'present' || att.status === 'late') {
        html += '<div class="grid grid-cols-2 gap-3">';
        html += buildTimeCard('Clock In', att.clock_in, att.clock_in_photo, 'emerald', att.clock_in_photo_archived);
        html += buildTimeCard('Clock Out', att.clock_out, att.clock_out_photo, 'blue', att.clock_out_photo_archived);
        html += '</div>';
    } else {
        html += buildNoClockDetail(att);
    }

    if (att.clock_in_lat && att.clock_in_lng) {
        html += '<div><p class="text-[12px] font-semibold text-gray-600 mb-2"><span class="material-symbols-outlined text-[14px] align-text-bottom">map</span> Lokasi GPS</p>';
        html += '<div id="detailMapContainer" class="w-full h-[220px] rounded-xl border border-gray-200 overflow-hidden"></div>';
        html += '<div class="mt-2 flex items-center gap-3 text-[11px] text-gray-400">';
        html += '<span>In: ' + Number(att.clock_in_lat).toFixed(6) + ', ' + Number(att.clock_in_lng).toFixed(6) + '</span>';
        if (att.clock_out_lat) html += '<span>Out: ' + Number(att.clock_out_lat).toFixed(6) + ', ' + Number(att.clock_out_lng).toFixed(6) + '</span>';
        html += '</div></div>';
    }

    if (att.remote_notes) {
        html += '<div class="p-3 rounded-xl bg-amber-50 border border-amber-200">';
        html += '<p class="text-[11px] font-semibold text-amber-700 mb-1">Catatan Remote</p>';
        html += '<p class="text-[13px] text-gray-700">' + escapeHtml(att.remote_notes) + '</p></div>';
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
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: 'OSM' }).addTo(detailMap);
            L.marker([att.clock_in_lat, att.clock_in_lng]).addTo(detailMap).bindPopup('<b>Clock In</b><br>' + (att.clock_in || '-'));
            if (att.clock_out_lat && att.clock_out_lng) {
                L.marker([att.clock_out_lat, att.clock_out_lng]).addTo(detailMap).bindPopup('<b>Clock Out</b><br>' + (att.clock_out || '-'));
                detailMap.fitBounds([[att.clock_in_lat, att.clock_in_lng], [att.clock_out_lat, att.clock_out_lng]], { padding: [40, 40] });
            }
        }
    }, 100);
}

function buildTimeCard(label, time, photo, color, archived = false) {
    let h = '<div class="rounded-xl border border-gray-200 overflow-hidden">';
    if (photo) {
        h += '<img src="' + photo + '" class="w-full h-[140px] object-cover">';
    } else if (archived) {
        h += '<div class="w-full h-[140px] bg-blue-50 flex flex-col items-center justify-center text-center px-4"><span class="material-symbols-outlined text-[34px] text-blue-400">archive</span><span class="mt-2 text-[12px] font-semibold text-blue-700">Foto sudah diarsipkan</span></div>';
    } else {
        h += '<div class="w-full h-[140px] bg-gray-100 flex items-center justify-center"><span class="material-symbols-outlined text-[36px] text-gray-300">no_photography</span></div>';
    }
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
