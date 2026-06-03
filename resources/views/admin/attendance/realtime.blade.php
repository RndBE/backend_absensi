@extends('admin.layouts.app')
@section('title', 'Presensi Hari Ini')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">location_on</span> Realtime Presensi — {{ now()->format('d F Y') }}</h3>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-blue-100 text-blue-800">{{ $attendances->count() }} Orang</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">#</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Departemen</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock In</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Clock Out</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Foto</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $i => $att)
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="openDetail({{ $att->id }})">
                    <td class="px-4 py-3.5 text-[13.5px] text-gray-500 border-b border-gray-100">{{ $i + 1 }}</td>
                    <td class="px-4 py-3.5 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            @if($att->employee->photo)
                                <img src="{{ asset('storage/' . $att->employee->photo) }}" class="w-9 h-9 rounded-full object-cover shrink-0" alt="">
                            @else
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[13px] font-bold shrink-0">{{ substr($att->employee->full_name ?? '?', 0, 1) }}</div>
                            @endif
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[13.5px] font-semibold text-gray-800">{{ $att->employee->full_name ?? '-' }}</span>
                                    @if(($att->employee->employment_status ?? '') === 'intern')
                                        <span class="inline-flex items-center px-1.5 py-0 rounded-full text-[9.5px] font-bold bg-orange-100 text-orange-700 uppercase tracking-wide">Magang</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-400">{{ $att->employee->position ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $att->employee->department->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 border-b border-gray-100">
                        @if($att->clock_in)
                            <span class="font-semibold text-emerald-600">{{ $att->clock_in }}</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 border-b border-gray-100">
                        @if($att->clock_out)
                            <span class="font-semibold text-blue-600">{{ $att->clock_out }}</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 border-b border-gray-100">
                        @if($att->clock_in_photo)
                            <img src="{{ asset('storage/' . $att->clock_in_photo) }}" class="w-10 h-10 rounded-lg object-cover border border-gray-200 hover:scale-110 transition-transform cursor-pointer" alt="Selfie">
                        @else
                            <span class="text-[10px] text-gray-300">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 border-b border-gray-100">
                        @if($att->is_late)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-amber-100 text-amber-800">Terlambat</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">Tepat Waktu</span>
                        @endif
                        @if($att->is_remote)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-orange-100 text-orange-700 mt-1"><span class="material-symbols-outlined text-[11px]">share_location</span> Remote</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-gray-400">
                        <div class="text-5xl mb-3"><span class="material-symbols-outlined text-[42px]">schedule</span></div>
                        <p class="text-sm font-medium">Belum ada yang clock in hari ini</p>
                    </td>
                </tr>
                @endforelse
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

{{-- Attendance data for JS --}}
@php
    $attData = $attendances->map(function($att) {
        return [
            'id' => $att->id,
            'name' => $att->employee->full_name ?? '-',
            'department' => $att->employee->department->name ?? '-',
            'position' => $att->employee->position ?? '',
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
        ];
    })->keyBy('id');
@endphp
<script>
const attendanceData = @json($attData);

let detailMap = null;

function openDetail(id) {
    const att = attendanceData[id];
    if (!att) return;

    document.getElementById('detailName').textContent = att.name + (att.position ? ' — ' + att.position : '');

    let html = '';

    // Status badges
    html += '<div class="flex items-center gap-2 flex-wrap">';
    if (att.is_late) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">⏰ Terlambat</span>';
    else html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">✅ Tepat Waktu</span>';
    if (att.is_remote) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-orange-100 text-orange-700">📍 Remote</span>';
    html += '</div>';

    // Clock In / Out cards
    html += '<div class="grid grid-cols-2 gap-3">';
    html += buildTimeCard('Clock In', att.clock_in, att.clock_in_photo, 'emerald');
    html += buildTimeCard('Clock Out', att.clock_out, att.clock_out_photo, 'blue');
    html += '</div>';

    // Map
    if (att.clock_in_lat && att.clock_in_lng) {
        html += '<div>';
        html += '<p class="text-[12px] font-semibold text-gray-600 mb-2"><span class="material-symbols-outlined text-[14px] align-text-bottom">map</span> Lokasi GPS</p>';
        html += '<div id="detailMapContainer" class="w-full h-[220px] rounded-xl border border-gray-200 overflow-hidden"></div>';
        html += '<div class="mt-2 flex items-center gap-3 text-[11px] text-gray-400">';
        if (att.clock_in_lat) html += '<span>📥 In: ' + Number(att.clock_in_lat).toFixed(6) + ', ' + Number(att.clock_in_lng).toFixed(6) + '</span>';
        if (att.clock_out_lat) html += '<span>📤 Out: ' + Number(att.clock_out_lat).toFixed(6) + ', ' + Number(att.clock_out_lng).toFixed(6) + '</span>';
        html += '</div></div>';
    }

    // Notes
    if (att.remote_notes) {
        html += '<div class="p-3 rounded-xl bg-amber-50 border border-amber-200">';
        html += '<p class="text-[11px] font-semibold text-amber-700 mb-1"><span class="material-symbols-outlined text-[12px] align-text-bottom">notes</span> Catatan Remote</p>';
        html += '<p class="text-[13px] text-gray-700">' + att.remote_notes + '</p>';
        html += '</div>';
    }

    document.getElementById('detailContent').innerHTML = html;

    // Show offcanvas
    const offcanvas = document.getElementById('detailOffcanvas');
    const panel = document.getElementById('detailPanel');
    offcanvas.classList.remove('hidden');
    requestAnimationFrame(() => {
        panel.classList.remove('translate-x-full');
        panel.classList.add('translate-x-0');
    });

    // Init map after DOM render
    setTimeout(() => {
        const mapEl = document.getElementById('detailMapContainer');
        if (mapEl && att.clock_in_lat) {
            if (detailMap) { detailMap.remove(); detailMap = null; }
            detailMap = L.map('detailMapContainer').setView([att.clock_in_lat, att.clock_in_lng], 16);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 17,
                attribution: '© OSM'
            }).addTo(detailMap);

            L.marker([att.clock_in_lat, att.clock_in_lng]).addTo(detailMap)
                .bindPopup('<b>Clock In</b><br>' + (att.clock_in || '-'));

            if (att.clock_out_lat && att.clock_out_lng) {
                L.marker([att.clock_out_lat, att.clock_out_lng]).addTo(detailMap)
                    .bindPopup('<b>Clock Out</b><br>' + (att.clock_out || '-'));
                detailMap.fitBounds([
                    [att.clock_in_lat, att.clock_in_lng],
                    [att.clock_out_lat, att.clock_out_lng]
                ], { padding: [40, 40] });
            }
        }
    }, 100);
}

function buildTimeCard(label, time, photo, color) {
    let html = '<div class="rounded-xl border border-gray-200 overflow-hidden">';
    if (photo) {
        html += '<img src="' + photo + '" class="w-full h-[140px] object-cover" alt="' + label + '">';
    } else {
        html += '<div class="w-full h-[140px] bg-gray-100 flex items-center justify-center"><span class="material-symbols-outlined text-[36px] text-gray-300">no_photography</span></div>';
    }
    html += '<div class="px-3 py-2.5 bg-' + color + '-50 border-t border-' + color + '-100">';
    html += '<div class="text-[10px] font-bold text-' + color + '-600 uppercase tracking-wider">' + label + '</div>';
    html += '<div class="text-[16px] font-black text-gray-800">' + (time ? time.substring(0,5) : '-') + '</div>';
    html += '</div></div>';
    return html;
}

function closeDetail() {
    const panel = document.getElementById('detailPanel');
    panel.classList.remove('translate-x-0');
    panel.classList.add('translate-x-full');
    setTimeout(() => {
        document.getElementById('detailOffcanvas').classList.add('hidden');
        if (detailMap) { detailMap.remove(); detailMap = null; }
    }, 300);
}
</script>

{{-- Leaflet CSS/JS --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endsection
