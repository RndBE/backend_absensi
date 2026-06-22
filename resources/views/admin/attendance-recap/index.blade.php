@extends('admin.layouts.app')
@section('title', 'Rekap Presensi')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManageAttendance = $adminPermission->can($currentAdmin, 'attendance.manage');
@endphp
{{-- Stat Cards --}}
<div class="grid grid-cols-2 md:grid-cols-7 gap-3 mb-5">
    @php
        $statCards = [
            ['label' => 'Hadir', 'value' => $stats['hadir'], 'icon' => 'check_circle', 'accent' => 'border-l-emerald-500', 'iconBox' => 'bg-emerald-50 text-emerald-600 ring-emerald-100', 'valueClass' => 'text-emerald-700'],
            ['label' => 'Terlambat', 'value' => $stats['terlambat'], 'icon' => 'schedule', 'accent' => 'border-l-amber-500', 'iconBox' => 'bg-amber-50 text-amber-600 ring-amber-100', 'valueClass' => 'text-amber-700'],
            ['label' => 'Sakit', 'value' => $stats['sakit'], 'icon' => 'sick', 'accent' => 'border-l-violet-500', 'iconBox' => 'bg-violet-50 text-violet-600 ring-violet-100', 'valueClass' => 'text-violet-700'],
            ['label' => 'Cuti', 'value' => $stats['cuti'], 'icon' => 'beach_access', 'accent' => 'border-l-blue-500', 'iconBox' => 'bg-blue-50 text-blue-600 ring-blue-100', 'valueClass' => 'text-blue-700'],
            ['label' => 'Alpha', 'value' => $stats['alpha'], 'icon' => 'cancel', 'accent' => 'border-l-red-500', 'iconBox' => 'bg-red-50 text-red-600 ring-red-100', 'valueClass' => 'text-red-700'],
            ['label' => 'Off', 'value' => $stats['off'], 'icon' => 'bedtime', 'accent' => 'border-l-slate-400', 'iconBox' => 'bg-slate-50 text-slate-600 ring-slate-100', 'valueClass' => 'text-slate-700'],
            ['label' => 'Libur', 'value' => $stats['libur'], 'icon' => 'block', 'accent' => 'border-l-rose-500', 'iconBox' => 'bg-rose-50 text-rose-600 ring-rose-100', 'valueClass' => 'text-rose-700'],
        ];
    @endphp
    @foreach($statCards as $card)
        <div class="bg-white rounded-xl border border-gray-200 {{ $card['accent'] }} border-l-4 shadow-sm p-4 flex items-center gap-3 transition-all hover:-translate-y-0.5 hover:shadow-md">
            <span class="w-10 h-10 rounded-lg ring-1 ring-inset flex items-center justify-center shrink-0 {{ $card['iconBox'] }}">
                <span class="material-symbols-outlined text-[22px] leading-none">{{ $card['icon'] }}</span>
            </span>
            <div>
                <div class="text-[22px] font-black leading-none {{ $card['valueClass'] }}">{{ $card['value'] }}</div>
                <div class="mt-1 text-[11px] text-gray-500 font-semibold">{{ $card['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">analytics</span> Rekap Presensi</h3>
        @if($canManageAttendance)
            <button type="button" class="inline-flex h-[38px] shrink-0 items-center gap-1.5 rounded-lg bg-indigo-600 px-3 text-[12px] font-semibold text-white transition hover:bg-indigo-700 cursor-pointer" data-attendance-import-open>
                <span class="material-symbols-outlined text-[15px] shrink-0">upload</span>
                Import
            </button>
        @endif
    </div>

    {{-- Date + Filters --}}
    @php
        $recapFilterParams = array_filter([
            'department_id' => $departmentId,
            'status' => $filterStatus,
        ], fn($value) => filled($value));
    @endphp
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.attendance-recap.index', array_merge($recapFilterParams, ['date' => $date->copy()->subDay()->format('Y-m-d')])) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">←</a>
            <form method="GET" action="{{ route('admin.attendance-recap.index') }}" class="flex items-center gap-2" id="dateForm">
                <input type="hidden" name="department_id" value="{{ $departmentId }}">
                <input type="hidden" name="status" value="{{ $filterStatus }}">
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()"
                    class="px-3 py-1.5 text-[13px] font-bold text-gray-800 border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </form>
            <a href="{{ route('admin.attendance-recap.index', array_merge($recapFilterParams, ['date' => $date->copy()->addDay()->format('Y-m-d')])) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">→</a>
            <a href="{{ route('admin.attendance-recap.index', $recapFilterParams) }}"
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
                <option value="sick" {{ $filterStatus === 'sick' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">sick</span> Sakit</option>
                <option value="leave" {{ $filterStatus === 'leave' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">beach_access</span> Cuti</option>
                <option value="absent" {{ $filterStatus === 'absent' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">cancel</span> Alpha</option>
                <option value="off" {{ $filterStatus === 'off' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">bedtime</span> Off</option>
                <option value="scheduled" {{ $filterStatus === 'scheduled' ? 'selected' : '' }}><span class="material-symbols-outlined text-[14px] align-text-bottom">event</span> Terjadwal</option>
            </select>
            <input type="search" id="attendanceRecapSearch" value="{{ $search }}" placeholder="Cari nama..." autocomplete="off" class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none w-[160px] focus:border-indigo-500">
            @if(request()->filled('department_id') || request()->filled('status'))
                <a href="{{ route('admin.attendance-recap.index', ['date' => $date->format('Y-m-d')]) }}" class="px-3 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">Reset</a>
            @endif
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
                        'sick' => 'bg-violet-50 text-violet-700 border-violet-200',
                        'leave' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'absent' => 'bg-red-50 text-red-700 border-red-200',
                        'off' => 'bg-gray-100 text-gray-500 border-gray-200',
                        'holiday' => 'bg-red-50 text-red-600 border-red-200',
                        'scheduled' => 'bg-indigo-50 text-indigo-600 border-indigo-200',
                        default => 'bg-gray-50 text-gray-400 border-gray-200',
                    };
                @endphp
                <tr class="border-b border-gray-50 hover:bg-gray-50/30 transition-all {{ $row['status'] === 'absent' ? 'bg-red-50/30' : '' }} {{ $row['attendance'] ? 'cursor-pointer' : '' }}" data-fuse-row="attendance-recap" data-search="{{ e($row['employee']->full_name . ' ' . ($row['employee']->position ?? '') . ' ' . ($row['employee']->employee_code ?? '') . ' ' . ($row['employee']->department->name ?? '') . ' ' . $row['status_label']) }}" {{ $row['attendance'] ? 'onclick=openDetail(' . $row['attendance']->id . ')' : '' }}>
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
                            @if($canManageAttendance)
                            <button onclick="event.stopPropagation(); openEdit({{ $row['employee']->id }}, '{{ $row['employee']->full_name }}', '{{ $row['clock_in'] ? substr($row['clock_in'], 0, 5) : '' }}', '{{ $row['clock_out'] ? substr($row['clock_out'], 0, 5) : '' }}', '{{ $row['attendance']?->status ?? 'present' }}')"
                                class="px-2 py-1.5 text-[10px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-10 text-center text-gray-400 text-sm">Tidak ada data presensi.</td>
                </tr>
                @endforelse
                <tr id="attendanceRecapFuseEmpty" class="hidden">
                    <td colspan="8" class="py-10 text-center text-gray-400 text-sm">Tidak ada data yang cocok dengan pencarian.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@if($canManageAttendance)
<div id="attendanceImportModal" class="hidden fixed inset-0 z-[90] items-center justify-center px-4 py-6">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" data-attendance-import-close></div>
    <form action="{{ route('admin.attendance-recap.import') }}" method="POST" enctype="multipart/form-data" class="relative w-full max-w-[420px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl" data-attendance-import-form>
        @csrf
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <div>
                <h3 class="text-[15px] font-bold text-gray-900">Import Presensi</h3>
                <p class="mt-0.5 text-[11px] text-gray-500">Pilih file presensi manual.</p>
            </div>
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 cursor-pointer" data-attendance-import-close>
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="space-y-3 px-4 py-4">
            <div>
                <label class="mb-1.5 block text-[12px] font-semibold text-gray-700">File Import</label>
                <label class="inline-flex h-[38px] min-w-0 w-full items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 text-[12px] font-semibold text-gray-700 transition hover:bg-gray-100 cursor-pointer" title="Format import: employee_code,date,clock_in,clock_out">
                    <span class="material-symbols-outlined text-[15px] shrink-0">upload_file</span>
                    <span class="truncate" data-import-file-name>Pilih CSV/XLSX</span>
                    <span class="sr-only">Format import: employee_code,date,clock_in,clock_out</span>
                    <input type="file" name="attendance_file" accept=".csv,.txt,.xlsx" required class="sr-only" onchange="this.form.querySelector('[data-import-file-name]').textContent = this.files[0]?.name || 'Pilih CSV/XLSX'">
                </label>
                <p class="mt-2 text-[11px] leading-4 text-gray-500">Format import: employee_code,date,clock_in,clock_out.</p>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
            <button type="button" class="inline-flex h-[38px] items-center rounded-lg border border-gray-300 bg-white px-4 text-[12px] font-semibold text-gray-700 transition hover:bg-gray-100 cursor-pointer" data-attendance-import-close>Batal</button>
            <button type="submit" class="inline-flex h-[38px] items-center gap-1.5 rounded-lg bg-indigo-600 px-4 text-[12px] font-semibold text-white transition hover:bg-indigo-700 cursor-pointer">
                <span class="material-symbols-outlined text-[15px] shrink-0">upload</span>
                Import
            </button>
        </div>
    </form>
</div>
@endif

{{-- Edit Offcanvas --}}
@if($canManageAttendance)
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
                @php
                    $manualStatusOptions = [
                        'hadir' => ['status' => 'present', 'icon' => 'check_circle', 'label' => 'Hadir'],
                        'late_excuse' => ['status' => 'late_excuse', 'icon' => 'schedule', 'label' => 'Hadir - Izin Terlambat'],
                        'early_departure' => ['status' => 'early_departure', 'icon' => 'logout', 'label' => 'Hadir - Izin Pulang Cepat'],
                        'alpha' => ['status' => 'absent', 'icon' => 'cancel', 'label' => 'Alpha'],
                        'sakit' => ['status' => 'sick', 'icon' => 'sick', 'label' => 'Sakit'],
                        'cuti' => ['status' => 'leave', 'icon' => 'beach_access', 'label' => 'Cuti'],
                        'libur' => ['status' => 'holiday', 'icon' => 'block', 'label' => 'Libur'],
                    ];
                @endphp
                <select name="status" id="editStatus" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_8px_center] bg-no-repeat bg-[length:14px] pr-8 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                    @foreach($manualStatusOptions as $option)
                        <option value="{{ $option['status'] }}"><span class="material-symbols-outlined text-[14px] align-text-bottom">{{ $option['icon'] }}</span> {{ $option['label'] }}</option>
                    @endforeach
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
@endif

<script>
@if($canManageAttendance)
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
@endif
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
<script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0"></script>
<script>
const attendanceRecapSearch = document.getElementById('attendanceRecapSearch');
const attendanceRecapEmpty = document.getElementById('attendanceRecapFuseEmpty');
const attendanceRecapItems = Array.from(document.querySelectorAll('[data-fuse-row="attendance-recap"]')).map((row, index) => ({
    index,
    row,
    text: row.dataset.search || '',
}));
const attendanceRecapFuse = window.Fuse ? new Fuse(attendanceRecapItems, {
    keys: ['text'],
    threshold: 0.45,
    ignoreLocation: true,
}) : null;

function applyAttendanceRecapSearch() {
    if (!attendanceRecapSearch) return;

    const query = attendanceRecapSearch.value.trim();
    let visibleIndexes = null;

    if (query) {
        const matches = attendanceRecapFuse
            ? attendanceRecapFuse.search(query).map(result => result.item.index)
            : attendanceRecapItems.filter(item => item.text.toLowerCase().includes(query.toLowerCase())).map(item => item.index);
        visibleIndexes = new Set(matches);
    }

    let visibleCount = 0;
    attendanceRecapItems.forEach(item => {
        const visible = !visibleIndexes || visibleIndexes.has(item.index);
        item.row.classList.toggle('hidden', !visible);
        if (visible) visibleCount++;
    });

    if (attendanceRecapEmpty) {
        attendanceRecapEmpty.classList.toggle('hidden', !query || visibleCount > 0);
    }
}

if (attendanceRecapSearch) {
    attendanceRecapSearch.addEventListener('input', applyAttendanceRecapSearch);
    applyAttendanceRecapSearch();
}

const attendanceImportModal = document.getElementById('attendanceImportModal');
const attendanceImportOpen = document.querySelector('[data-attendance-import-open]');
const attendanceImportCloses = document.querySelectorAll('[data-attendance-import-close]');

function openAttendanceImportModal() {
    if (!attendanceImportModal) return;
    attendanceImportModal.classList.remove('hidden');
    attendanceImportModal.classList.add('flex');
}

function closeAttendanceImportModal() {
    if (!attendanceImportModal) return;
    attendanceImportModal.classList.add('hidden');
    attendanceImportModal.classList.remove('flex');
}

if (attendanceImportOpen) {
    attendanceImportOpen.addEventListener('click', openAttendanceImportModal);
}

attendanceImportCloses.forEach((button) => {
    button.addEventListener('click', closeAttendanceImportModal);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAttendanceImportModal();
});

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
    const att = recapData[id];
    if (!att) return;
    document.getElementById('detailName').textContent = att.name + ' - ' + att.department;

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
