@extends('admin.layouts.app')
@section('title', 'Kalkulator Pajak Gaji')

@section('content')
<div class="mb-5">
    <h2 class="text-[20px] font-bold text-gray-900">Kalkulator Pajak Gaji</h2>
    <p class="text-[13px] text-gray-500">Simulasi PPh 21 & BPJS untuk calon karyawan atau perencanaan gaji</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- Input --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">calculate</span> Input Simulasi</h3>
        </div>
        <form action="{{ route('admin.tax.simulate') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Gaji Pokok + Tunjangan Tetap *</label>
                <div class="flex items-center gap-1.5">
                    <span class="text-[12px] text-gray-400 font-semibold">Rp</span>
                    <input type="hidden" name="gross_salary" value="{{ $gross_salary ?? '' }}">
                    <input type="text" data-target="gross_salary" value="{{ $gross_salary ?? '' }}" required placeholder="5.000.000" class="currency-input w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Masa Pajak</label>
                <input type="month" name="period_month" value="{{ $period_month ?? now()->format('Y-m') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <p class="mt-1 text-[11px] text-gray-400">Januari-November memakai TER bulanan. Desember payroll dihitung ulang tahunan.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Status PTKP *</label>
                    <select name="ptkp_status" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach(['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3','K/I/0','K/I/1','K/I/2','K/I/3'] as $ptkp)
                            <option value="{{ $ptkp }}" {{ ($ptkp_status ?? '') === $ptkp ? 'selected' : '' }}>{{ $ptkp }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Metode Pajak *</label>
                    <select name="tax_method" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="gross" {{ ($tax_method ?? '') === 'gross' ? 'selected' : '' }}>Gross (pajak karyawan)</option>
                        <option value="gross_up" {{ ($tax_method ?? 'gross_up') === 'gross_up' ? 'selected' : '' }}>Gross Up (pajak perusahaan)</option>
                        <option value="nett" {{ ($tax_method ?? '') === 'nett' ? 'selected' : '' }}>Nett (pajak perusahaan, tidak tampil)</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Hitung</button>
            </div>
        </form>
    </div>

    {{-- Result --}}
    @if(isset($result))
    <div class="space-y-4">
        {{-- Summary Card --}}
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-xl shadow-lg p-5 text-white">
            <div class="text-[12px] font-semibold uppercase tracking-wider opacity-80 mb-1">Take Home Pay</div>
            <div class="text-[28px] font-bold">Rp {{ number_format($result['take_home'], 0, ',', '.') }}</div>
            <div class="text-[12px] opacity-70 mt-1">Per bulan · Metode: {{ strtoupper(str_replace('_', ' ', $result['tax_method'])) }}</div>
            @if(($result['method'] ?? null) === 'ter_monthly')
            <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/15 text-[11px] font-semibold">
                TER {{ $result['ter_category'] ?? '-' }} - {{ number_format((float) ($result['ter_rate'] ?? 0), 2, ',', '.') }}%
            </div>
            @endif
        </div>

        {{-- PPh 21 Detail --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider mb-3">PPh 21</div>
            <table class="w-full text-[12px]">
                @if(($result['taxable_employer_benefit'] ?? 0) > 0)
                <tr><td class="py-1.5 text-gray-600">Gaji Bruto (input)</td><td class="py-1.5 text-right font-semibold text-gray-800">Rp {{ number_format($result['gross_input'] ?? $result['bruto_monthly'], 0, ',', '.') }}</td></tr>
                <tr><td class="py-1.5 text-gray-600">Penambah Bruto (premi Kesehatan/JKK/JKM perusahaan)</td><td class="py-1.5 text-right font-semibold text-amber-600">+ Rp {{ number_format($result['taxable_employer_benefit'], 0, ',', '.') }}</td></tr>
                <tr class="border-t border-gray-100"><td class="py-1.5 text-gray-600">Bruto Bulanan (dasar pajak)</td><td class="py-1.5 text-right font-bold text-gray-800">Rp {{ number_format($result['bruto_monthly'], 0, ',', '.') }}</td></tr>
                @else
                <tr><td class="py-1.5 text-gray-600">Bruto Bulanan</td><td class="py-1.5 text-right font-semibold text-gray-800">Rp {{ number_format($result['bruto_monthly'], 0, ',', '.') }}</td></tr>
                @endif
                @if(($result['method'] ?? null) === 'ter_monthly')
                <tr><td class="py-1.5 text-gray-600">Metode</td><td class="py-1.5 text-right font-semibold text-gray-800">TER Bulanan PP 58/2023</td></tr>
                <tr><td class="py-1.5 text-gray-600">Kategori TER</td><td class="py-1.5 text-right font-semibold text-gray-800">{{ $result['ter_category'] ?? '-' }}</td></tr>
                <tr><td class="py-1.5 text-gray-600">Tarif TER</td><td class="py-1.5 text-right font-semibold text-gray-800">{{ number_format((float) ($result['ter_rate'] ?? 0), 2, ',', '.') }}%</td></tr>
                <tr><td class="py-1.5 text-gray-600">Dasar Pengenaan</td><td class="py-1.5 text-right font-semibold text-gray-700">Penghasilan bruto bulanan</td></tr>
                <tr class="border-t border-gray-200 bg-gray-50"><td class="py-2 font-bold text-gray-800">PPh 21 Jan-Nov</td><td class="py-2 text-right font-bold text-red-700 text-[14px]">Rp {{ number_format($result['tax_monthly'], 0, ',', '.') }}</td></tr>
                @if(!empty($result['note']))
                <tr><td colspan="2" class="py-2 text-[11px] text-gray-500">{{ $result['note'] }}</td></tr>
                @endif
                @else
                <tr><td class="py-1.5 text-gray-600">Biaya Jabatan (5%)</td><td class="py-1.5 text-right font-semibold text-red-600">- Rp {{ number_format($result['biaya_jabatan'], 0, ',', '.') }}</td></tr>
                <tr><td class="py-1.5 text-gray-600">BPJS Karyawan</td><td class="py-1.5 text-right font-semibold text-red-600">- Rp {{ number_format($result['bpjs_employee'], 0, ',', '.') }}</td></tr>
                <tr class="border-t border-gray-100"><td class="py-1.5 text-gray-600">Netto Bulanan</td><td class="py-1.5 text-right font-bold text-gray-800">Rp {{ number_format($result['netto_monthly'], 0, ',', '.') }}</td></tr>
                <tr><td class="py-1.5 text-gray-600">Netto Tahunan</td><td class="py-1.5 text-right font-semibold text-gray-700">Rp {{ number_format($result['netto_annual'], 0, ',', '.') }}</td></tr>
                <tr><td class="py-1.5 text-gray-600">PTKP ({{ $result['ptkp_status'] }})</td><td class="py-1.5 text-right font-semibold text-red-600">- Rp {{ number_format($result['ptkp_annual'], 0, ',', '.') }}</td></tr>
                <tr class="border-t border-gray-100"><td class="py-1.5 text-gray-600">PKP</td><td class="py-1.5 text-right font-bold text-gray-800">Rp {{ number_format($result['pkp'], 0, ',', '.') }}</td></tr>
                <tr><td class="py-1.5 text-gray-600">PPh 21 Tahunan</td><td class="py-1.5 text-right font-semibold text-gray-700">Rp {{ number_format($result['tax_annual'], 0, ',', '.') }}</td></tr>
                <tr class="border-t border-gray-200 bg-gray-50"><td class="py-2 font-bold text-gray-800">PPh 21 / Bulan</td><td class="py-2 text-right font-bold text-red-700 text-[14px]">Rp {{ number_format($result['tax_monthly'], 0, ',', '.') }}</td></tr>
                @endif
                @if($result['tunjangan_pajak'] > 0)
                <tr class="bg-emerald-50"><td class="py-2 font-bold text-emerald-700">Tunjangan Pajak</td><td class="py-2 text-right font-bold text-emerald-700">Rp {{ number_format($result['tunjangan_pajak'], 0, ',', '.') }}</td></tr>
                @endif
            </table>
        </div>

        {{-- BPJS Detail --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider mb-3">BPJS</div>
            <table class="w-full text-[12px]">
                <thead>
                    <tr><th class="text-left py-1 text-gray-500">Program</th><th class="text-right py-1 text-gray-500">Perusahaan</th><th class="text-right py-1 text-gray-500">Karyawan</th></tr>
                </thead>
                <tbody>
                    @foreach(['kesehatan' => 'Kesehatan', 'jht' => 'JHT', 'jkk' => 'JKK', 'jkm' => 'JKM'] as $key => $lbl)
                    <tr>
                        <td class="py-1.5 text-gray-700 font-medium">{{ $lbl }}</td>
                        <td class="py-1.5 text-right text-gray-700">Rp {{ number_format($result['bpjs_detail'][$key]['company'], 0, ',', '.') }}</td>
                        <td class="py-1.5 text-right text-gray-700">Rp {{ number_format($result['bpjs_detail'][$key]['employee'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    <tr class="border-t border-gray-200 font-bold">
                        <td class="py-2 text-gray-800">Total</td>
                        <td class="py-2 text-right text-gray-800">Rp {{ number_format($result['bpjs_detail']['company_total'], 0, ',', '.') }}</td>
                        <td class="py-2 text-right text-gray-800">Rp {{ number_format($result['bpjs_detail']['employee_total'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex items-center justify-center p-12">
        <div class="text-center text-gray-400">
            <span class="material-symbols-outlined text-[48px] mb-2">calculate</span>
            <p class="text-[14px]">Masukkan data untuk simulasi</p>
        </div>
    </div>
    @endif
</div>
@endsection
