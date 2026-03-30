@extends('admin.layouts.app')
@section('title', 'Payslip Karyawan')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">receipt</span> Payslip Karyawan</h3>
    </div>
    <div class="p-5">
        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3 mb-5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / kode karyawan..."
                   class="px-3 py-2 text-[13px] border border-gray-300 rounded-lg w-64 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <select name="period" class="px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Periode</option>
                @foreach($periods as $p)
                    <option value="{{ $p }}" {{ request('period') === $p ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($p . '-01')->translatedFormat('F Y') }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Filter</button>
            @if(request('search') || request('period'))
                <a href="{{ route('admin.payslips.index') }}" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Periode</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Gaji Pokok</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Earning</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Deduction</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Net Salary</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payslips as $ps)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($ps->employee->full_name ?? '?', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $ps->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $ps->employee->employee_code ?? '' }} · {{ $ps->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700">{{ \Carbon\Carbon::parse($ps->payrollRun->period . '-01')->translatedFormat('F Y') }}</span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] text-gray-700">Rp {{ number_format($ps->basic_salary, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-emerald-700">Rp {{ number_format($ps->total_earning, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-red-600">Rp {{ number_format($ps->total_deduction, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[14px] font-bold text-gray-900">Rp {{ number_format($ps->net_salary, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.payslips.show', $ps->id) }}" class="p-1.5 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors" title="Detail"><span class="material-symbols-outlined text-[16px]">visibility</span></a>
                                <a href="{{ route('admin.payslips.download', $ps->id) }}" class="p-1.5 rounded-lg hover:bg-emerald-50 text-gray-400 hover:text-emerald-600 transition-colors" title="Download PDF"><span class="material-symbols-outlined text-[16px]">download</span></a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Belum ada payslip yang di-publish</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $payslips->links() }}</div>
    </div>
</div>
@endsection
