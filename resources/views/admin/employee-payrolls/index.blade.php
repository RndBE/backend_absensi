@extends('admin.layouts.app')
@section('title', 'Master Payroll Karyawan')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManagePayrollMaster = $adminPermission->can($currentAdmin, 'payroll.master.manage');
@endphp

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">account_balance</span> Master Payroll Karyawan</h3>
    </div>
    <div class="p-5">
        {{-- Filters --}}
        <form method="GET" id="employeePayrollFilterForm" class="flex items-center gap-3 mb-5 flex-wrap">
            <input type="search" id="employeePayrollSearch" placeholder="Cari nama / kode karyawan..."
                   class="w-full max-w-[280px] h-[42px] px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <select name="department_id" onchange="document.getElementById('employeePayrollFilterForm').submit()" class="w-full max-w-[280px] h-[42px] px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Departemen</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" {{ request('department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
            @if(request()->filled('department_id'))
                <a href="{{ route('admin.employee-payrolls.index') }}" class="inline-flex items-center px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Departemen</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Gaji Pokok</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Jadwal</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    @php $payroll = $emp->activePayroll; @endphp
                    <tr class="hover:bg-gray-50 transition-colors" data-fuse-row="employee-payroll" data-search="{{ e($emp->full_name . ' ' . $emp->employee_code . ' ' . $emp->email . ' ' . ($emp->department->name ?? '') . ' ' . ($payroll ? $payroll->payment_schedule : '') . ' ' . ($payroll && $payroll->is_active ? 'aktif' : ($payroll ? 'nonaktif' : 'belum setup'))) }}">
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
                            @if($canManagePayrollMaster)
                            <a href="{{ route('admin.employee-payrolls.edit', $emp->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                <span class="material-symbols-outlined text-[14px]">settings</span> Kelola
                            </a>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-12 text-gray-400 text-sm">Tidak ada karyawan ditemukan</td></tr>
                    @endforelse
                    <tr id="employeePayrollFuseEmpty" class="hidden">
                        <td colspan="6" class="text-center py-12 text-gray-400 text-sm">Tidak ada karyawan payroll yang cocok dengan pencarian</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0"></script>
<script>
const employeePayrollSearch = document.getElementById('employeePayrollSearch');
const employeePayrollEmpty = document.getElementById('employeePayrollFuseEmpty');
const employeePayrollItems = Array.from(document.querySelectorAll('[data-fuse-row="employee-payroll"]')).map((row, index) => ({
    index,
    row,
    text: row.dataset.search || '',
}));
const employeePayrollFuse = window.Fuse ? new Fuse(employeePayrollItems, {
    keys: ['text'],
    threshold: 0.45,
    ignoreLocation: true,
}) : null;

function applyEmployeePayrollSearch() {
    if (!employeePayrollSearch) return;

    const keyword = employeePayrollSearch.value.trim();
    const matched = keyword && employeePayrollFuse
        ? new Set(employeePayrollFuse.search(keyword).map((result) => result.item.index))
        : new Set(employeePayrollItems.map((item) => item.index));
    let visibleCount = 0;

    employeePayrollItems.forEach((item) => {
        const isVisible = matched.has(item.index);
        item.row.classList.toggle('hidden', !isVisible);
        if (isVisible) visibleCount++;
    });

    if (employeePayrollEmpty) {
        employeePayrollEmpty.classList.toggle('hidden', !keyword || visibleCount > 0);
    }
}

if (employeePayrollSearch) {
    employeePayrollSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    employeePayrollSearch.addEventListener('input', applyEmployeePayrollSearch);
    applyEmployeePayrollSearch();
}
</script>
@endsection
