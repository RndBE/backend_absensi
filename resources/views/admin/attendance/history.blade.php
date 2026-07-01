@extends('admin.layouts.app')
@section('title', 'Riwayat Absensi')

@section('content')
@php
    $canManageAttendance = app(\App\Support\AdminPermission::class)->can($currentAdmin, 'attendance.manage');
    $reviewStatusMeta = [
        'pending' => ['label' => 'Butuh Review', 'class' => 'bg-amber-100 text-amber-800'],
        'approved' => ['label' => 'Disetujui HRD', 'class' => 'bg-emerald-100 text-emerald-800'],
        'rejected' => ['label' => 'Ditolak HRD', 'class' => 'bg-red-100 text-red-800'],
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
    <a href="{{ route('admin.attendance.history', array_merge(request()->except('page'), ['review_status' => 'pending'])) }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100 transition-colors">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-[12px] font-bold text-amber-800">Presensi Mencurigakan</div>
                <div class="text-[11px] text-amber-700 mt-1">Butuh Review HRD</div>
            </div>
            <div class="text-[24px] font-black text-amber-800">{{ $reviewSummary['pending'] ?? 0 }}</div>
        </div>
    </a>
    <a href="{{ route('admin.attendance.history', array_merge(request()->except('page'), ['review_status' => 'rejected'])) }}" class="rounded-xl border border-red-200 bg-red-50 p-4 hover:bg-red-100 transition-colors">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-[12px] font-bold text-red-800">Ditolak HRD</div>
                <div class="text-[11px] text-red-700 mt-1">Tidak dihitung hadir</div>
            </div>
            <div class="text-[24px] font-black text-red-800">{{ $reviewSummary['rejected'] ?? 0 }}</div>
        </div>
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">history</span> Riwayat Absensi</h3>
    </div>
    <div class="p-5">
        {{-- Filters --}}
        <form method="GET" class="flex items-end gap-3 mb-5 flex-wrap">
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Dari</label>
                <input type="date" name="date_from" class="px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 max-w-[180px]" value="{{ request('date_from') }}">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Sampai</label>
                <input type="date" name="date_to" class="px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 max-w-[180px]" value="{{ request('date_to') }}">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Karyawan</label>
                <select name="employee_id" class="max-w-[220px] px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                    <option value="">Semua Karyawan</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                            {{ $emp->full_name }}{{ $emp->employment_status === 'intern' ? ' (Magang)' : ($emp->employment_status === 'outsourcing' ? ' (Outsourcing)' : '') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Status Karyawan</label>
                <select name="employment_status" class="px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                    <option value="">Semua Status</option>
                    <option value="permanent" {{ request('employment_status') === 'permanent' ? 'selected' : '' }}>Tetap</option>
                    <option value="contract" {{ request('employment_status') === 'contract' ? 'selected' : '' }}>Kontrak</option>
                    <option value="intern" {{ request('employment_status') === 'intern' ? 'selected' : '' }}>Magang</option>
                    <option value="probation" {{ request('employment_status') === 'probation' ? 'selected' : '' }}>Probation</option>
                    <option value="outsourcing" {{ request('employment_status') === 'outsourcing' ? 'selected' : '' }}>Outsourcing</option>
                </select>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Review Lokasi</label>
                <select name="review_status" class="px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                    <option value="">Semua Review</option>
                    <option value="pending" {{ request('review_status') === 'pending' ? 'selected' : '' }}>Butuh Review</option>
                    <option value="approved" {{ request('review_status') === 'approved' ? 'selected' : '' }}>Disetujui HRD</option>
                    <option value="rejected" {{ request('review_status') === 'rejected' ? 'selected' : '' }}>Ditolak HRD</option>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center px-3 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 cursor-pointer">🔍 Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Kode</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Departemen</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Masuk</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Pulang</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Foto</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $att)
                    <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="openDetail({{ $att->id }})">
                        <td class="px-4 py-3.5 text-[13.5px] font-semibold text-gray-800 border-b border-gray-100">{{ $att->date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[13.5px] text-gray-700">{{ $att->employee->full_name ?? '-' }}</span>
                                @if(($att->employee->employment_status ?? '') === 'intern')
                                    <span class="inline-flex items-center px-1.5 py-0 rounded-full text-[9.5px] font-bold bg-orange-100 text-orange-700 uppercase tracking-wide">Magang</span>
                                @elseif(($att->employee->employment_status ?? '') === 'outsourcing')
                                    <span class="inline-flex items-center px-1.5 py-0 rounded-full text-[9.5px] font-bold bg-cyan-100 text-cyan-700 uppercase tracking-wide">Outsourcing</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100"><code class="text-[11px] bg-gray-100 px-1.5 py-0.5 rounded">{{ $att->employee->employee_code ?? '-' }}</code></td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $att->employee->department->name ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $att->clock_in ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $att->clock_out ?? '-' }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            @if($att->clock_in_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($att->clock_in_photo))
                                <img src="{{ asset('storage/' . $att->clock_in_photo) }}" class="w-9 h-9 rounded-lg object-cover border border-gray-200" alt="Selfie">
                            @elseif($att->clock_in_photo)
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-semibold bg-blue-50 text-blue-700">Arsip</span>
                            @else
                                <span class="text-[10px] text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            @php
                                $rowLeave = \App\Support\AttendanceLateExcuse::firstForDate($leavesByEmployee[$att->employee_id] ?? collect(), $att->date);
                                $rowLateExcuse = \App\Support\AttendanceLateExcuse::isLateArrivalLeave($rowLeave) ? $rowLeave : null;
                                $rowEarlyDeparture = \App\Support\AttendanceLateExcuse::isEarlyDepartureLeave($rowLeave) ? $rowLeave : null;
                                $rowManualPermission = \App\Support\AttendanceLateExcuse::manualPermissionStatusLabel($att->status);
                            @endphp
                            @if($att->review_status === 'rejected' || $att->status === 'absent')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-red-100 text-red-800">Absen</span>
                            @elseif($att->status === 'leave')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-blue-100 text-blue-800">Cuti</span>
                            @elseif($att->status === 'sick')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-violet-100 text-violet-800">Sakit</span>
                            @elseif($att->status === 'wfh')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-teal-100 text-teal-800">WFH</span>
                            @elseif($rowManualPermission)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">{{ $rowManualPermission }}</span>
                            @elseif($att->status === 'present' && $att->is_late && $rowLateExcuse)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">{{ \App\Support\AttendanceLateExcuse::STATUS_LABEL }}</span>
                            @elseif($att->status === 'present' && $att->is_late)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-amber-100 text-amber-800">Terlambat</span>
                            @elseif($att->status === 'present' && $rowEarlyDeparture)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">{{ \App\Support\AttendanceLateExcuse::EARLY_DEPARTURE_STATUS_LABEL }}</span>
                            @elseif($att->status === 'present')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">Hadir</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-red-100 text-red-800">Absen</span>
                            @endif
                            @if($att->is_remote)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-orange-100 text-orange-700 ml-1"><span class="material-symbols-outlined text-[10px]">share_location</span></span>
                            @endif
                            @if($att->review_status)
                                @php $reviewMeta = $reviewStatusMeta[$att->review_status] ?? ['label' => strtoupper($att->review_status), 'class' => 'bg-gray-100 text-gray-700']; @endphp
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $reviewMeta['class'] }}">
                                        <span class="material-symbols-outlined text-[11px] mr-0.5">shield</span>{{ $reviewMeta['label'] }}
                                    </span>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-12 text-gray-400 text-sm">Tidak ada data absensi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($attendances->hasPages())
            <div class="flex justify-end pt-4">{{ $attendances->links() }}</div>
        @endif
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
    $attData = $attendances->map(function($att) use ($leavesByEmployee) {
        return [
            'id' => $att->id,
            'name' => $att->employee->full_name ?? '-',
            'department' => $att->employee->department->name ?? '-',
            'date' => $att->date->format('d/m/Y'),
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
            'late_excused' => $att->is_late && (
                \App\Support\AttendanceLateExcuse::manualPermissionStatusLabel($att->status) !== null
                || \App\Support\AttendanceLateExcuse::isLateArrivalLeave(
                    \App\Support\AttendanceLateExcuse::firstForDate($leavesByEmployee[$att->employee_id] ?? collect(), $att->date)
                )
            ),
            'is_remote' => $att->is_remote,
            'remote_notes' => $att->remote_notes,
            'review_status' => $att->review_status,
            'suspicious_reason' => $att->suspicious_reason,
            'review_notes' => $att->review_notes,
            'reviewed_by' => $att->reviewer->full_name ?? null,
            'reviewed_at' => $att->reviewed_at?->format('d/m/Y H:i'),
            'clock_in_accuracy_meters' => $att->clock_in_accuracy_meters,
            'clock_out_accuracy_meters' => $att->clock_out_accuracy_meters,
            'clock_in_is_mocked' => $att->clock_in_is_mocked,
            'clock_out_is_mocked' => $att->clock_out_is_mocked,
            'approve_url' => route('admin.attendance.security-review.approve', $att->id),
            'reject_url' => route('admin.attendance.security-review.reject', $att->id),
        ];
    })->keyBy('id');
@endphp
<script>
const attendanceData = @json($attData);
const csrfToken = @json(csrf_token());
const canManageAttendance = @json($canManageAttendance);
let detailMap = null;

function openDetail(id) {
    const att = attendanceData[id];
    if (!att) return;
    document.getElementById('detailName').textContent = att.name + ' — ' + att.date;

    let html = '';
    html += '<div class="flex items-center gap-2 flex-wrap">';
    if (att.is_late && att.late_excused) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">✅ Hadir - Izin Terlambat</span>';
    else if (att.is_late) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">⏰ Terlambat</span>';
    else html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">✅ Tepat Waktu</span>';
    if (att.is_remote) html += '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-orange-100 text-orange-700">📍 Remote</span>';
    html += '</div>';

    if (att.review_status) {
        html += '<div class="p-3 rounded-xl border ' + (att.review_status === 'rejected' ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200') + '">';
        html += '<p class="text-[12px] font-bold text-gray-800 mb-1">Presensi Mencurigakan</p>';
        html += '<p class="text-[12px] text-gray-700">' + escapeHtml(att.suspicious_reason || 'Perlu review HRD.') + '</p>';
        html += '<div class="grid grid-cols-2 gap-2 mt-3 text-[11px] text-gray-600">';
        html += '<div>Akurasi masuk: <b>' + formatMeters(att.clock_in_accuracy_meters) + '</b></div>';
        html += '<div>Akurasi pulang: <b>' + formatMeters(att.clock_out_accuracy_meters) + '</b></div>';
        html += '<div>Fake GPS masuk: <b>' + (att.clock_in_is_mocked ? 'Ya' : 'Tidak') + '</b></div>';
        html += '<div>Fake GPS pulang: <b>' + (att.clock_out_is_mocked ? 'Ya' : 'Tidak') + '</b></div>';
        html += '</div>';
        if (att.reviewed_by) {
            html += '<p class="mt-3 text-[11px] text-gray-500">Direview oleh <b>' + escapeHtml(att.reviewed_by) + '</b>' + (att.reviewed_at ? ' pada ' + escapeHtml(att.reviewed_at) : '') + '</p>';
        }
        if (att.review_notes) {
            html += '<p class="mt-1 text-[11px] text-gray-500">Catatan: ' + escapeHtml(att.review_notes) + '</p>';
        }
        if (att.review_status === 'pending' && canManageAttendance) {
            html += '<div class="mt-3 grid grid-cols-2 gap-2">';
            html += buildReviewForm(att.approve_url, 'Approve Presensi', 'bg-emerald-600 hover:bg-emerald-700');
            html += buildReviewForm(att.reject_url, 'Tolak Presensi', 'bg-red-600 hover:bg-red-700');
            html += '</div>';
        }
        html += '</div>';
    }

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

function buildReviewForm(action, label, buttonClass) {
    return '<form method="POST" action="' + action + '" onclick="event.stopPropagation()">' +
        '<input type="hidden" name="_token" value="' + csrfToken + '">' +
        '<input type="hidden" name="review_notes" value="">' +
        '<button type="submit" class="w-full px-3 py-2 rounded-lg text-[12px] font-bold text-white ' + buttonClass + '">' +
        label +
        '</button></form>';
}

function formatMeters(value) {
    if (value === null || value === undefined || value === '') return '-';
    return Math.round(Number(value)) + 'm';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
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
