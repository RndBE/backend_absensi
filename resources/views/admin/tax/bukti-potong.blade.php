@extends('admin.layouts.app')
@section('title', 'Bukti Potong PPh 21')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-[20px] font-bold text-gray-900">Bukti Potong 1721-A1</h2>
        <p class="text-[13px] text-gray-500">Generate dan kelola bukti potong PPh 21 tahunan</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.tax.export-efiling', ['year' => $year]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
            <span class="material-symbols-outlined text-[16px]">download</span> Export E-Filing CSV
        </a>
    </div>
</div>

{{-- Generate Form --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <form action="{{ route('admin.tax.generate-bukti-potong') }}" method="POST" class="flex flex-wrap gap-3 items-end">
        @csrf
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Karyawan</label>
            <select name="employee_id" required class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg min-w-[250px]">
                <option value="">— Pilih —</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->employee_code }} — {{ $emp->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Tahun Pajak</label>
            <input type="number" name="tax_year" value="{{ $year }}" min="2020" max="2099" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg w-[100px]">
        </div>
        <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Generate</button>
    </form>
</div>

{{-- List --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">No. Bukti Potong</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Bruto</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">PPh 21</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">BPJS</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Netto</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $cert)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 border-b border-gray-100 text-[12px] font-mono text-indigo-600">{{ $cert->certificate_number }}</td>
                    <td class="px-4 py-3 border-b border-gray-100">
                        <div class="text-[13px] font-semibold text-gray-800">{{ $cert->employee->full_name }}</div>
                        <div class="text-[11px] text-gray-400">{{ $cert->employee->employee_code }}</div>
                    </td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px] font-semibold text-gray-700">Rp {{ number_format($cert->gross_annual, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px] font-semibold text-red-600">Rp {{ number_format($cert->tax_annual, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px] font-semibold text-gray-600">Rp {{ number_format($cert->bpjs_annual, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-right text-[12px] font-bold text-gray-800">Rp {{ number_format($cert->nett_annual, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 border-b border-gray-100 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $cert->status === 'final' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ ucfirst($cert->status) }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Belum ada bukti potong untuk tahun {{ $year }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
