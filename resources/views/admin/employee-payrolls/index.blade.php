@extends('admin.layouts.app')
@section('title', 'Master Payroll Karyawan')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">account_balance</span> Master Payroll Karyawan</h3>
    </div>
    <div class="p-5">
        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3 mb-5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / kode karyawan..."
                   class="px-3 py-2 text-[13px] border border-gray-300 rounded-lg w-64 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <select name="department_id" class="px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Departemen</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" {{ request('department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Filter</button>
            @if(request('search') || request('department_id'))
                <a href="{{ route('admin.employee-payrolls.index') }}" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Departemen</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Group</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Gaji Pokok</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Jadwal</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    @php $payroll = $emp->activePayroll; @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($emp->full_name, 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $emp->full_name }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $emp->employee_code }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-[13px] text-gray-600">{{ $emp->department->name ?? '-' }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-[13px] text-gray-600">
                            @if($payroll && $payroll->payrollGroup)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700">{{ $payroll->payrollGroup->name }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-gray-800">
                            @if($payroll)
                                Rp {{ number_format($payroll->basic_salary, 0, ',', '.') }}
                            @else
                                <span class="text-gray-400">Belum diset</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center text-[13px] text-gray-600">{{ $payroll ? ucfirst($payroll->payment_schedule) : '-' }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            @if($payroll && $payroll->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700">Aktif</span>
                            @elseif($payroll)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">Nonaktif</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700">Belum Setup</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <a href="{{ route('admin.employee-payrolls.edit', $emp->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                <span class="material-symbols-outlined text-[14px]">settings</span> Kelola
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Tidak ada karyawan ditemukan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $employees->links() }}</div>
    </div>
</div>
@endsection
