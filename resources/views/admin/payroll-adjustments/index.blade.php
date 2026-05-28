@extends('admin.layouts.app')
@section('title', 'Payroll Adjustment')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-[20px] font-bold text-gray-900">Payroll Adjustment</h2>
        <p class="text-[13px] text-gray-500">Kelola adjustment, backpay, koreksi, dan arrears</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.payroll-adjustments.bulk-create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
            <span class="material-symbols-outlined text-[16px]">upload_file</span> Import CSV
        </a>
        <a href="{{ route('admin.payroll-adjustments.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
            <span class="material-symbols-outlined text-[16px]">add</span> Tambah
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Tipe</label>
            <select name="type" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach(['adjustment', 'correction', 'backpay', 'arrears', 'retroactive'] as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Status</label>
            <select name="status" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach(['pending', 'applied', 'cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Target Periode</label>
            <input type="month" name="target_period" value="{{ request('target_period') }}" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
        </div>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tipe</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Keterangan</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Jumlah</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Periode</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adj)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 border-b border-gray-100">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $adj->employee->full_name ?? '-' }}</div>
                        <div class="text-[11px] text-gray-400">{{ $adj->employee->employee_code ?? '' }}</div>
                    </td>
                    <td class="px-4 py-3 border-b border-gray-100">
                        @php $typeColors = ['adjustment'=>'indigo','correction'=>'amber','backpay'=>'emerald','arrears'=>'orange','retroactive'=>'blue']; @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $typeColors[$adj->type] ?? 'gray' }}-50 text-{{ $typeColors[$adj->type] ?? 'gray' }}-700">{{ ucfirst($adj->type) }}</span>
                    </td>
                    <td class="px-4 py-3 border-b border-gray-100">
                        <div class="text-[13px] text-gray-700">{{ $adj->name }}</div>
                        @if($adj->notes)<div class="text-[11px] text-gray-400 truncate max-w-[200px]">{{ $adj->notes }}</div>@endif
                    </td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right">
                        <span class="text-[13px] font-bold {{ $adj->earning_type === 'earning' ? 'text-emerald-700' : 'text-red-600' }}">
                            {{ $adj->earning_type === 'earning' ? '+' : '−' }} Rp {{ number_format($adj->amount, 0, ',', '.') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 border-b border-gray-100 text-center">
                        <div class="text-[12px] font-semibold text-gray-700">{{ $adj->target_period }}</div>
                        @if($adj->reference_period)<div class="text-[10px] text-gray-400">ref: {{ $adj->reference_period }}</div>@endif
                    </td>
                    <td class="px-4 py-3 border-b border-gray-100 text-center">
                        @if($adj->status === 'pending')
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700">Pending</span>
                        @elseif($adj->status === 'applied')
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700">Applied</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">Cancelled</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 border-b border-gray-100 text-center">
                        @if($adj->status === 'pending')
                        <form action="{{ route('admin.payroll-adjustments.cancel', $adj->id) }}" method="POST" class="inline" data-confirm="Batalkan adjustment ini?">
                            @csrf
                            <button type="submit" class="p-1 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors cursor-pointer" title="Batalkan"><span class="material-symbols-outlined text-[14px]">close</span></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Tidak ada adjustment</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($adjustments->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $adjustments->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
