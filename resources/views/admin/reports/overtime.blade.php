@extends('admin.layouts.app')
@section('title', 'Laporan Lembur')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <a href="{{ route('admin.reports.index') }}" class="text-[12px] text-gray-400 hover:text-indigo-600 transition-colors">← Kembali</a>
        <h2 class="text-[20px] font-bold text-gray-900">Laporan Lembur</h2>
    </div>
    <a href="{{ route('admin.reports.export-overtime', request()->query()) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-amber-600 to-amber-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
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
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Karyawan</label>
            <select name="employee_id" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" {{ request('employee_id') == $e->id ? 'selected' : '' }}>{{ $e->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Status</label>
            <select name="status" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach(['pending','approved','rejected'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </form>
</div>

{{-- Summary --}}
@if(count($otSummary) > 0)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[13px] font-bold text-gray-700">Ringkasan Lembur (Approved)</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr class="bg-gray-50">
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Jumlah Request</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Diajukan</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Terhitung</th>
            </tr></thead>
            <tbody>
                @foreach($otSummary as $os)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 border-b border-gray-100"><div class="text-[13px] font-semibold text-gray-800">{{ $os['employee']->full_name }}</div><div class="text-[11px] text-gray-400">{{ $os['employee']->employee_code }}</div></td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-gray-700">{{ $os['count'] }}x</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] text-gray-500">{{ intdiv($os['total_minutes'], 60) }}j {{ $os['total_minutes'] % 60 }}m</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-amber-600">{{ intdiv($os['actual_minutes'], 60) }}j {{ $os['actual_minutes'] % 60 }}m</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Detail --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[13px] font-bold text-gray-700">Detail Lembur ({{ $overtimes->count() }} record)</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr class="bg-gray-50">
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Tanggal</th>
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Tipe</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Diajukan</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Break</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Aktual</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Clock Out</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Status</th>
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Alasan</th>
            </tr></thead>
            <tbody>
                @forelse($overtimes as $o)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 border-b border-gray-100 text-[12px] text-gray-700">{{ $o->date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-[12px] font-semibold text-gray-800">{{ $o->employee->full_name }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $o->overtime_type === 'holiday' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                            {{ $o->overtime_type === 'holiday' ? 'Libur' : 'Kerja' }}
                        </span>
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] text-gray-700">
                        {{ $o->total_duration_formatted }}
                        @if($o->overtime_type === 'holiday' && $o->planned_start)
                            <div class="text-[10px] text-gray-400">{{ substr($o->planned_start, 0, 5) }} - {{ substr($o->planned_end, 0, 5) }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] text-gray-500">
                        {{ ($o->approved_break ?? $o->break_duration ?? 0) }}m
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] font-bold {{ !is_null($o->actual_duration) ? 'text-amber-600' : 'text-gray-400' }}">
                        {{ $o->actual_duration_formatted }}
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] text-gray-500">
                        {{ $o->actual_clock_out ? substr($o->actual_clock_out, 0, 5) : '-' }}
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center">
                        @php $oc = ['approved'=>'emerald','pending'=>'amber','rejected'=>'red']; @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $oc[$o->status] ?? 'gray' }}-50 text-{{ $oc[$o->status] ?? 'gray' }}-700">{{ ucfirst($o->status) }}</span>
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-[12px] text-gray-600 max-w-[200px] truncate">{{ $o->reason ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center py-8 text-gray-400 text-sm">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
