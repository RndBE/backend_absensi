@extends('admin.layouts.app')
@section('title', 'Jadwal Kerja')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">calendar_month</span> Jadwal Kerja</h3>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.schedule-templates.index') }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all"><span class="material-symbols-outlined text-[18px] align-text-bottom">content_paste</span> Template</a>
            <a href="{{ route('admin.shifts.index') }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all"><span class="material-symbols-outlined text-[18px] align-text-bottom">settings</span> Kelola Shift</a>
            <button onclick="document.getElementById('bulkModal').classList.remove('hidden')" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">content_paste</span> Bulk Assign</button>
        </div>
    </div>

    {{-- View Mode Toggle + Navigation + Filters --}}
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-2">
            {{-- View mode toggle --}}
            <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
                <a href="{{ route('admin.schedules.index', array_merge(['view' => 'week', 'department_id' => $departmentId])) }}"
                   class="px-3 py-1.5 text-[11px] font-semibold rounded-md transition-all {{ $viewMode === 'week' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Minggu</a>
                <a href="{{ route('admin.schedules.index', array_merge(['view' => 'month', 'department_id' => $departmentId])) }}"
                   class="px-3 py-1.5 text-[11px] font-semibold rounded-md transition-all {{ $viewMode === 'month' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Bulan</a>
            </div>

            {{-- Navigation --}}
            <a href="{{ route('admin.schedules.index', array_merge($prevParam, ['department_id' => $departmentId])) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">←</a>
            <span class="px-3 py-1.5 text-[13px] font-bold text-gray-800 bg-indigo-50 rounded-lg">
                {{ $rangeLabel }}
            </span>
            <a href="{{ route('admin.schedules.index', array_merge($nextParam, ['department_id' => $departmentId])) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">→</a>
            <a href="{{ route('admin.schedules.index', array_merge($todayParam, ['department_id' => $departmentId])) }}"
               class="px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">Hari Ini</a>
        </div>
        <form method="GET" action="{{ route('admin.schedules.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            @if($viewMode === 'week' && request('week'))
                <input type="hidden" name="week" value="{{ request('week') }}">
            @elseif($viewMode === 'month' && request('month'))
                <input type="hidden" name="month" value="{{ request('month') }}">
            @endif
            <select name="department_id" onchange="this.form.submit()" class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_6px_center] bg-no-repeat bg-[length:14px] pr-7 focus:border-indigo-500">
                <option value="">Semua Departemen</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama..." class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none w-[140px] focus:border-indigo-500">
        </form>
    </div>

    {{-- Shift Legend --}}
    <div class="px-5 py-2 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <span class="text-[11px] text-gray-400 font-semibold">SHIFT:</span>
        @foreach($shifts as $shift)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold text-white" style="background-color: {{ $shift->color }}">
                {{ $shift->name }} {{ !$shift->is_off ? '(' . substr($shift->start_time, 0, 5) . '-' . substr($shift->end_time, 0, 5) . ')' : '' }}
            </span>
        @endforeach
        <span class="ml-2 text-[10px] text-gray-400">|</span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold text-orange-600 bg-orange-50 border border-orange-200">✎ Override Manual</span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold text-red-600 bg-red-50 border border-red-200"><span class="material-symbols-outlined text-[12px] align-text-bottom">block</span> Libur Nasional</span>
    </div>

    {{-- Grid Calendar --}}
    <div class="overflow-x-auto">
        <table class="w-full {{ $viewMode === 'month' ? 'min-w-[2200px]' : 'min-w-[900px]' }}">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-2 px-3 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider {{ $viewMode === 'month' ? 'w-[180px]' : 'w-[220px]' }} sticky left-0 bg-white z-10">Karyawan</th>
                    @foreach($dates as $date)
                        @php
                            $isToday = $date->isToday();
                            $isWeekend = $date->isWeekend();
                            $dateKey = $date->format('Y-m-d');
                            $holiday = $holidays->get($dateKey);
                            $dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                            $isMonday = $date->dayOfWeekIso === 1;
                        @endphp
                        <th class="py-2 px-0.5 text-center text-[{{ $viewMode === 'month' ? '9' : '11' }}px] font-bold uppercase tracking-wider
                            {{ $viewMode === 'month' ? 'w-[65px]' : 'w-[100px]' }}
                            {{ $holiday ? 'bg-red-50 text-red-600' : ($isToday ? 'bg-indigo-50 text-indigo-700' : ($isWeekend ? 'bg-gray-50 text-gray-400' : 'text-gray-500')) }}
                            {{ $viewMode === 'month' && $isMonday && !$date->eq($rangeStart) ? 'border-l-2 border-indigo-200' : '' }}">
                            <div>{{ $dayNames[$date->dayOfWeekIso - 1] }}</div>
                            <div class="text-[{{ $viewMode === 'month' ? '11' : '13' }}px] {{ $isToday ? 'text-indigo-600 font-black' : '' }}">{{ $date->format('d') }}</div>
                            @if($holiday)
                                <div class="text-[7px] font-semibold text-red-500 leading-tight mt-0.5 normal-case tracking-normal">{{ Str::limit($holiday->name, $viewMode === 'month' ? 10 : 15) }}</div>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                <tr class="border-b border-gray-50 hover:bg-gray-50/30 transition-all">
                    <td class="py-1.5 px-3 sticky left-0 bg-white z-10">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-500 flex items-center justify-center text-white text-[9px] font-bold shrink-0">{{ substr($emp->full_name, 0, 1) }}</div>
                            <div class="min-w-0">
                                <div class="text-[11px] font-semibold text-gray-800 leading-tight truncate">{{ $emp->full_name }}</div>
                                <div class="text-[9px] text-gray-400 truncate">
                                    {{ $emp->department->name ?? '' }}
                                    @if($emp->scheduleTemplate)
                                        · <span class="text-indigo-500">{{ $emp->scheduleTemplate->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    @foreach($dates as $date)
                        @php
                            $key = $emp->id . '-' . $date->format('Y-m-d');
                            $assignment = $assignments->get($key)?->first();
                            $isWeekend = $date->isWeekend();
                            $isToday = $date->isToday();
                            $holiday = $holidays->get($date->format('Y-m-d'));
                            $isMonday = $date->dayOfWeekIso === 1;

                            // Priority: holiday > manual override > template pattern
                            $shift = null;
                            $isOverride = false;
                            $isFromTemplate = false;

                            // Di hari libur pun tetap load assignment (untuk karyawan jaga/satpam)
                            if ($assignment) {
                                $shift = $assignment->shift;
                                $isOverride = true;
                            } elseif (!$holiday && $emp->scheduleTemplate) {
                                $shift = $emp->scheduleTemplate->getShiftForDay($date->dayOfWeekIso);
                                $isFromTemplate = $shift !== null;
                            }
                        @endphp
                        <td class="py-1 px-0.5 text-center
                            {{ ($holiday && !$shift) ? 'bg-red-50/60' : ($isToday ? 'bg-indigo-50/50' : ($isWeekend ? 'bg-gray-50/50' : '')) }}
                            {{ $viewMode === 'month' && $isMonday && !$date->eq($rangeStart) ? 'border-l-2 border-indigo-200' : '' }}">
                            @if($holiday && !$shift)
                                {{-- Hari libur tanpa assignment: tampilkan badge LIBUR + tombol assign kecil --}}
                                <div class="w-full px-0.5 py-0.5 rounded-md text-[{{ $viewMode === 'month' ? '8' : '10' }}px] font-bold text-white bg-red-500">
                                    LIBUR
                                    @if($viewMode !== 'month')
                                    <div class="text-[7px] font-normal opacity-80 leading-tight">{{ Str::limit($holiday->name, 15) }}</div>
                                    @endif
                                </div>
                                <button onclick="openAssign({{ $emp->id }}, '{{ $date->format('Y-m-d') }}', '', false, true)"
                                    title="Assign shift meski hari libur"
                                    class="mt-0.5 w-full text-[{{ $viewMode === 'month' ? '9' : '10' }}px] text-red-400 border border-dashed border-red-300 rounded px-0.5 py-0.5 hover:bg-red-100 hover:text-red-600 hover:border-red-400 transition-all bg-transparent cursor-pointer">
                                    + shift
                                </button>
                            @elseif($holiday && $shift)
                                {{-- Hari libur ADA assignment (misal satpam jaga): tampilkan shift dengan badge libur --}}
                                <div class="relative">
                                    <button onclick="openAssign({{ $emp->id }}, '{{ $date->format('Y-m-d') }}', {{ $shift->id }}, true, true)"
                                        class="w-full px-0.5 py-1 rounded-md text-[{{ $viewMode === 'month' ? '8' : '10' }}px] font-bold text-white cursor-pointer transition-all hover:opacity-80 hover:scale-105 border-2 border-orange-400"
                                        style="background-color: {{ $shift->color }}">
                                        {{ $viewMode === 'month' ? Str::limit($shift->name, 4, '') : $shift->name }}
                                        @if(!$shift->is_off && $viewMode !== 'month')
                                        <div class="text-[8px] font-normal opacity-80">{{ substr($shift->start_time, 0, 5) }}</div>
                                        @endif
                                    </button>
                                    <span class="absolute -top-1 -left-1 w-3 h-3 rounded-full bg-red-500 text-white text-[6px] flex items-center justify-center font-bold" title="Jaga di hari libur">!</span>
                                    <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-orange-500 text-white text-[6px] flex items-center justify-center font-bold">✎</span>
                                </div>
                            @elseif($shift)
                                {{-- Hari biasa dengan shift (dari template atau override) --}}
                                <button onclick="openAssign({{ $emp->id }}, '{{ $date->format('Y-m-d') }}', {{ $shift->id }}, {{ $isOverride ? 'true' : 'false' }}, false)"
                                    class="w-full px-0.5 py-1 rounded-md text-[{{ $viewMode === 'month' ? '8' : '10' }}px] font-bold text-white cursor-pointer transition-all hover:opacity-80 hover:scale-105 relative
                                           {{ $isOverride ? 'border-2 border-orange-400' : 'border-0' }}"
                                    style="background-color: {{ $shift->color }}">
                                    {{ $viewMode === 'month' ? Str::limit($shift->name, 4, '') : $shift->name }}
                                    @if(!$shift->is_off && $viewMode !== 'month')
                                    <div class="text-[8px] font-normal opacity-80">{{ substr($shift->start_time, 0, 5) }}</div>
                                    @endif
                                    @if($isOverride)
                                    <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-orange-500 text-white text-[6px] flex items-center justify-center font-bold">✎</span>
                                    @endif
                                </button>
                            @else
                                <button onclick="openAssign({{ $emp->id }}, '{{ $date->format('Y-m-d') }}', '', false, false)"
                                    class="w-full px-0.5 py-{{ $viewMode === 'month' ? '1.5' : '3' }} rounded-md text-[{{ $viewMode === 'month' ? '14' : '18' }}px] text-gray-300 cursor-pointer border border-dashed border-gray-200 hover:border-indigo-300 hover:text-indigo-400 hover:bg-indigo-50/50 transition-all bg-transparent">
                                    +
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($dates) + 1 }}" class="py-10 text-center text-gray-400 text-sm">Tidak ada karyawan ditemukan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Single Assign Modal --}}
<div id="assignModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl w-[340px] p-5">
        <h4 class="text-[14px] font-bold text-gray-900 mb-1">🔧 Assign Shift (Override)</h4>
        <p class="text-[11px] text-gray-400 mb-3" id="overrideHint">Override ini akan menimpa jadwal dari template.</p>
        <div id="holidayWarning" style="display:none" class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2">
            <span class="text-red-500 text-[14px] leading-none mt-0.5">⚠️</span>
            <p class="text-[11px] text-red-700 font-medium leading-snug">Ini adalah <strong>hari libur nasional</strong>. Karyawan ini akan dijadwalkan jaga/piket di hari libur tersebut.</p>
        </div>
        <form action="{{ route('admin.schedules.store') }}" method="POST">
            @csrf
            <input type="hidden" name="employee_id" id="assignEmpId">
            <input type="hidden" name="date" id="assignDate">
            <div class="mb-3">
                <div class="space-y-1.5" id="shiftOptions">
                    @foreach($shifts as $shift)
                    <label class="flex items-center gap-2.5 p-2 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 transition-all has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="shift_id" value="{{ $shift->id }}" class="accent-indigo-500">
                        <span class="w-4 h-4 rounded-full shrink-0" style="background-color: {{ $shift->color }}"></span>
                        <span class="text-[12px] font-semibold text-gray-700">{{ $shift->name }}</span>
                        <span class="text-[10px] text-gray-400 ml-auto">{{ $shift->is_off ? 'Libur' : substr($shift->start_time, 0, 5) . '-' . substr($shift->end_time, 0, 5) }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Override</button>
                <button type="button" id="clearBtn" onclick="clearAssignment()" class="px-4 py-2 text-[12px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
            </div>
        </form>
        <form id="clearForm" action="{{ route('admin.schedules.clear') }}" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="employee_id" id="clearEmpId">
            <input type="hidden" name="date" id="clearDate">
        </form>
    </div>
</div>

{{-- Bulk Assign Modal --}}
<div id="bulkModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl w-[520px] max-h-[90vh] overflow-y-auto p-5">
        <h4 class="text-[14px] font-bold text-gray-900 mb-4"><span class="material-symbols-outlined text-[14px] align-text-bottom">content_paste</span> Bulk Assign Shift</h4>
        <form action="{{ route('admin.schedules.bulk') }}" method="POST">
            @csrf
            {{-- Shift --}}
            <div class="mb-3">
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Pilih Shift *</label>
                <select name="shift_id" class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_8px_center] bg-no-repeat bg-[length:14px] pr-8 focus:border-indigo-500" required>
                    <option value="">Pilih Shift</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shift->name }} {{ !$shift->is_off ? '(' . substr($shift->start_time, 0, 5) . '-' . substr($shift->end_time, 0, 5) . ')' : '(Libur)' }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Date Range --}}
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ $rangeStart->format('Y-m-d') }}" class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500" required>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Selesai</label>
                    <input type="date" name="end_date" value="{{ $rangeEnd->format('Y-m-d') }}" class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500" required>
                </div>
            </div>
            {{-- Options --}}
            <div class="mb-4 flex flex-col gap-1.5">
                <label class="flex items-center gap-2 text-[12px] text-gray-600 font-medium">
                    <input type="checkbox" name="include_weekends" value="1" class="accent-indigo-500"> Termasuk Sabtu & Minggu
                </label>
                <label class="flex items-center gap-2 text-[12px] text-gray-600 font-medium">
                    <input type="checkbox" name="include_holidays" value="1" class="accent-red-500">
                    <span>Termasuk <span class="text-red-600 font-semibold">Hari Libur Nasional</span></span>
                    <span class="text-[10px] text-gray-400">(misal: satpam/jaga)</span>
                </label>
            </div>
            {{-- Employee Picker --}}
            <div class="mb-4">
                <label class="block text-[12px] font-semibold text-gray-600 mb-2">Pilih Karyawan</label>
                {{-- Search + Dept filter --}}
                <div class="flex items-center gap-2 mb-2">
                    <input type="text" id="bulkEmpSearch" placeholder="Cari nama..." oninput="filterBulkEmployees()"
                           class="flex-1 px-2.5 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 placeholder:text-gray-300">
                    <select id="bulkDeptFilter" onchange="filterBulkEmployees()"
                            class="px-2.5 py-1.5 text-[11px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_5px_center] bg-no-repeat bg-[length:12px] pr-6 focus:border-indigo-500">
                        <option value="">Semua Dept</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Select all / clear --}}
                <div class="flex items-center gap-3 mb-2">
                    <button type="button" onclick="toggleAllBulk(true)" class="text-[10px] font-semibold text-indigo-600 underline cursor-pointer bg-transparent border-0">Pilih Semua</button>
                    <button type="button" onclick="toggleAllBulk(false)" class="text-[10px] font-semibold text-gray-400 underline cursor-pointer bg-transparent border-0">Batal Semua</button>
                    <span class="ml-auto text-[10px] text-gray-400" id="bulkSelectedCount">0 dipilih</span>
                </div>
                {{-- List --}}
                <div class="max-h-[220px] overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-0.5" id="bulkEmployeeList">
                    @foreach($allEmployees ?? $employees as $emp)
                    <label class="bulk-emp-row flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors"
                           data-name="{{ strtolower($emp->full_name) }}"
                           data-dept="{{ $emp->department_id }}">
                        <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}"
                               class="accent-indigo-500 bulk-check w-3.5 h-3.5 shrink-0"
                               onchange="updateBulkCount()">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-500 flex items-center justify-center text-white text-[9px] font-bold shrink-0">{{ substr($emp->full_name, 0, 1) }}</div>
                        <span class="text-[12px] text-gray-700 flex-1 truncate">{{ $emp->full_name }}</span>
                        <span class="text-[10px] text-gray-400 shrink-0">{{ $emp->department->name ?? '-' }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">assignment</span> Assign ke Karyawan Terpilih</button>
        </form>
    </div>
</div>

<script>
function openAssign(empId, date, shiftId, isOverride, isHoliday) {
    document.getElementById('assignEmpId').value = empId;
    document.getElementById('assignDate').value = date;
    document.getElementById('clearEmpId').value = empId;
    document.getElementById('clearDate').value = date;
    document.getElementById('clearBtn').style.display = isOverride ? 'block' : 'none';

    // Tampilkan/sembunyikan peringatan hari libur
    var holidayWarn = document.getElementById('holidayWarning');
    if (holidayWarn) holidayWarn.style.display = isHoliday ? 'block' : 'none';

    document.querySelectorAll('#shiftOptions input[type=radio]').forEach(r => {
        r.checked = (r.value == shiftId);
    });

    document.getElementById('assignModal').classList.remove('hidden');
}

function clearAssignment() {
    if (confirm('Hapus override? Jadwal akan kembali mengikuti template.')) {
        document.getElementById('clearForm').submit();
    }
}

function toggleAllBulk(checked) {
    document.querySelectorAll('.bulk-emp-row:not([style*="display: none"]) .bulk-check').forEach(c => c.checked = checked);
    updateBulkCount();
}

function filterBulkEmployees() {
    const q    = (document.getElementById('bulkEmpSearch')?.value || '').toLowerCase().trim();
    const dept = document.getElementById('bulkDeptFilter')?.value || '';
    document.querySelectorAll('.bulk-emp-row').forEach(function(row) {
        const nameMatch = !q   || row.dataset.name.includes(q);
        const deptMatch = !dept || row.dataset.dept == dept;
        row.style.display = (nameMatch && deptMatch) ? '' : 'none';
    });
    updateBulkCount();
}

function updateBulkCount() {
    const total   = document.querySelectorAll('.bulk-check:checked').length;
    const counter = document.getElementById('bulkSelectedCount');
    if (counter) counter.textContent = total + ' dipilih';
}

// Init count when modal opens
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.bulk-check').forEach(c => c.addEventListener('change', updateBulkCount));
    updateBulkCount();
});
</script>
@endsection
