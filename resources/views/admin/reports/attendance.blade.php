@extends('admin.layouts.app')
@section('title', 'Laporan Absensi')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <a href="{{ route('admin.reports.index') }}" class="text-[12px] text-gray-400 hover:text-indigo-600 transition-colors">← Kembali</a>
        <h2 class="text-[20px] font-bold text-gray-900">Laporan Absensi</h2>
    </div>
    <a href="{{ route('admin.reports.export-attendance', request()->query()) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-blue-600 to-blue-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
        <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Bulan</label>
            <input type="month" name="month" value="{{ $month }}" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Departemen</label>
            <select name="department_id" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" {{ $departmentId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Karyawan</label>
            <select name="employee_id" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" {{ $employeeId == $e->id ? 'selected' : '' }}>{{ $e->full_name }}</option>
                @endforeach
            </select>
        </div>
    </form>
</div>

{{-- Summary Cards --}}
@if(count($summary) > 0)
<div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Total Hadir</div>
        <div class="text-[24px] font-bold">{{ collect($summary)->sum('present') }}</div>
    </div>
    <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Total Terlambat</div>
        <div class="text-[24px] font-bold">{{ collect($summary)->sum('late') }}</div>
    </div>
    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Total Alpha</div>
        <div class="text-[24px] font-bold">{{ collect($summary)->sum('absent') }}</div>
    </div>
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Total Cuti</div>
        <div class="text-[24px] font-bold">{{ collect($summary)->sum('leave') }}</div>
    </div>
</div>
@endif

{{-- Summary Table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-3 border-b border-gray-100">
        <h3 class="text-[13px] font-bold text-gray-700">Ringkasan Per Karyawan</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Hadir</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Terlambat</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Alpha</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Cuti</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary as $s)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 border-b border-gray-100">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $s['employee']->full_name }}</div>
                        <div class="text-[11px] text-gray-400">{{ $s['employee']->employee_code }}</div>
                    </td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-blue-600">{{ $s['present'] }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-amber-600">{{ $s['late'] }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-red-600">{{ $s['absent'] }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-emerald-600">{{ $s['leave'] }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-gray-800">{{ $s['total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Detail Table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-3 border-b border-gray-100">
        <h3 class="text-[13px] font-bold text-gray-700">Detail Harian ({{ $attendances->count() }} record)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Tanggal</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Clock In</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Clock Out</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Status</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Terlambat</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $a)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 border-b border-gray-100 text-[12px] text-gray-700">{{ $a->date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-[12px] font-semibold text-gray-800">{{ $a->employee->full_name }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] text-gray-700">{{ $a->clock_in ?? '-' }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] text-gray-700">{{ $a->clock_out ?? '-' }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center">
                        @php $sc = ['present'=>'emerald','absent'=>'red','leave'=>'blue']; @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $sc[$a->status] ?? 'gray' }}-50 text-{{ $sc[$a->status] ?? 'gray' }}-700">{{ ucfirst($a->status) }}</span>
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center">
                        @if($a->is_late)<span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Ya</span>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-gray-400 text-sm">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
