@extends('admin.layouts.app')
@section('title', 'Laporan Payroll')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <a href="{{ route('admin.reports.index') }}" class="text-[12px] text-gray-400 hover:text-indigo-600 transition-colors">← Kembali</a>
        <h2 class="text-[20px] font-bold text-gray-900">Laporan Payroll</h2>
    </div>
    <a href="{{ route('admin.reports.export-payroll', ['period' => $period]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-violet-600 to-violet-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
        <span class="material-symbols-outlined text-[16px]">download</span> Export CSV
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
    <form method="GET" class="flex gap-3 items-end">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Periode</label>
            <input type="month" name="period" value="{{ $period }}" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" onchange="this.form.submit()">
        </div>
    </form>
</div>

{{-- Summary Cards --}}
@if($totals['count'] > 0)
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
    <div class="bg-gradient-to-br from-gray-700 to-gray-800 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Karyawan</div>
        <div class="text-[24px] font-bold">{{ $totals['count'] }}</div>
    </div>
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Gaji Pokok</div>
        <div class="text-[18px] font-bold">{{ number_format($totals['basic'], 0, ',', '.') }}</div>
    </div>
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Total Earning</div>
        <div class="text-[18px] font-bold">{{ number_format($totals['earning'], 0, ',', '.') }}</div>
    </div>
    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Total Potongan</div>
        <div class="text-[18px] font-bold">{{ number_format($totals['deduction'], 0, ',', '.') }}</div>
    </div>
    <div class="bg-gradient-to-br from-violet-500 to-violet-600 rounded-xl p-4 text-white">
        <div class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Total Gaji Bersih</div>
        <div class="text-[18px] font-bold">{{ number_format($totals['net'], 0, ',', '.') }}</div>
    </div>
</div>
@endif

{{-- Payroll Detail Table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-[13px] font-bold text-gray-700">Detail Payroll — {{ $period }} ({{ $details->count() }} karyawan)</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead><tr class="bg-gray-50">
                <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan</th>
                <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Gaji Pokok</th>
                <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Total Earning</th>
                <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Total Potongan</th>
                <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Gaji Bersih</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-bold uppercase text-gray-500 border-b">Detail</th>
            </tr></thead>
            <tbody>
                @forelse($details as $d)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 border-b border-gray-100">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $d->employee->full_name ?? '-' }}</div>
                        <div class="text-[11px] text-gray-400">{{ $d->employee->employee_code ?? '' }} · {{ $d->employee->position ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-right text-[12px] text-gray-700">Rp {{ number_format($d->basic_salary, 0, ',', '.') }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-right text-[12px] font-semibold text-emerald-700">Rp {{ number_format($d->total_earning, 0, ',', '.') }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-right text-[12px] font-semibold text-red-600">Rp {{ number_format($d->total_deduction, 0, ',', '.') }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-right text-[12px] font-bold text-gray-800">Rp {{ number_format($d->net_salary, 0, ',', '.') }}</td>
                    <td class="px-4 py-2.5 border-b border-gray-100 text-center">
                        <button onclick="toggleDetail({{ $d->id }})" class="text-indigo-500 hover:text-indigo-700 cursor-pointer">
                            <span class="material-symbols-outlined text-[14px]">expand_more</span>
                        </button>
                    </td>
                </tr>
                <tr id="detail-{{ $d->id }}" class="hidden">
                    <td colspan="6" class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-[11px] font-bold text-emerald-600 uppercase mb-1">Pendapatan</div>
                                @php $comps = is_array($d->components) ? $d->components : json_decode($d->components, true); @endphp
                                @foreach($comps ?? [] as $c)
                                    @if(($c['type'] ?? '') === 'earning')
                                    <div class="flex justify-between text-[12px] py-0.5"><span class="text-gray-700">{{ $c['name'] }}</span><span class="font-semibold text-gray-800">Rp {{ number_format($c['amount'] ?? 0, 0, ',', '.') }}</span></div>
                                    @endif
                                @endforeach
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-red-600 uppercase mb-1">Potongan</div>
                                @foreach($comps ?? [] as $c)
                                    @if(($c['type'] ?? '') === 'deduction')
                                    <div class="flex justify-between text-[12px] py-0.5"><span class="text-gray-700">{{ $c['name'] }}</span><span class="font-semibold text-red-600">Rp {{ number_format($c['amount'] ?? 0, 0, ',', '.') }}</span></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @foreach($comps ?? [] as $c)
                            @if(($c['type'] ?? '') === 'info')
                            <div class="text-[11px] text-blue-600 mt-1">ℹ️ {{ $c['name'] }}: {{ $c['detail'] ?? '' }} @if(($c['amount'] ?? 0) > 0) · Rp {{ number_format($c['amount'], 0, ',', '.') }}@endif</div>
                            @endif
                        @endforeach
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-gray-400 text-sm">Tidak ada data payroll untuk periode ini</td></tr>
                @endforelse
                @if($details->count() > 0)
                <tr class="bg-gray-100 font-bold">
                    <td class="px-4 py-3 text-[13px] text-gray-800">TOTAL</td>
                    <td class="px-4 py-3 text-right text-[12px] text-gray-800">Rp {{ number_format($totals['basic'], 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right text-[12px] text-emerald-700">Rp {{ number_format($totals['earning'], 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right text-[12px] text-red-600">Rp {{ number_format($totals['deduction'], 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right text-[12px] text-gray-800">Rp {{ number_format($totals['net'], 0, ',', '.') }}</td>
                    <td></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    row.classList.toggle('hidden');
}
</script>
@endsection
