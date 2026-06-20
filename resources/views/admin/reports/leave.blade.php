@extends('admin.layouts.app')
@section('title', 'Laporan Cuti')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <a href="{{ route('admin.reports.index') }}" class="text-[12px] text-gray-400 hover:text-indigo-600 transition-colors">← Kembali</a>
        <h2 class="text-[20px] font-bold text-gray-900">Laporan Cuti</h2>
    </div>
    <a href="{{ route('admin.reports.export-leave', request()->query()) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
        <span class="material-symbols-outlined text-[16px]">download</span> Export Excel
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Tahun</label>
            <input type="number" name="year" value="{{ $year }}" min="2020" max="2099" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg w-[100px]" onchange="this.form.submit()">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Status</label>
            <select name="status" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach(['pending','approved','rejected'] as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
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
    </form>
</div>

{{-- Summary --}}
@if(count($leaveSummary) > 0)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[13px] font-bold text-gray-700">Ringkasan Cuti (Approved)</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr class="bg-gray-50">
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Jumlah Request</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Total Hari</th>
            </tr></thead>
            <tbody>
                @foreach($leaveSummary as $ls)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 border-b border-gray-100"><div class="text-[13px] font-semibold text-gray-800">{{ $ls['employee']->full_name }}</div><div class="text-[11px] text-gray-400">{{ $ls['employee']->employee_code }}</div></td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-gray-700">{{ $ls['count'] }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center text-[13px] font-bold text-emerald-600">{{ $ls['total_days'] }} hari</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Detail --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[13px] font-bold text-gray-700">Detail Cuti ({{ $leaves->count() }} record)</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr class="bg-gray-50">
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan</th>
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Jenis</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Periode</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Hari</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Status</th>
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Alasan</th>
            </tr></thead>
            <tbody>
                @forelse($leaves as $l)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 border-b border-gray-100"><div class="text-[12px] font-semibold text-gray-800">{{ $l->employee->full_name }}</div></td>
                    <td class="px-4 py-2 border-b border-gray-100 text-[12px] text-gray-700">{{ $l->leaveType->name ?? '-' }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] text-gray-700">{{ $l->start_date->format('d/m') }} - {{ $l->end_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center text-[12px] font-bold text-gray-800">{{ $l->total_days }}</td>
                    <td class="px-4 py-2 border-b border-gray-100 text-center">
                        @php $lc = ['approved'=>'emerald','pending'=>'amber','rejected'=>'red']; @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $lc[$l->status] ?? 'gray' }}-50 text-{{ $lc[$l->status] ?? 'gray' }}-700">{{ ucfirst($l->status) }}</span>
                    </td>
                    <td class="px-4 py-2 border-b border-gray-100 text-[12px] text-gray-600 max-w-[200px] truncate">{{ $l->reason ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-gray-400 text-sm">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
