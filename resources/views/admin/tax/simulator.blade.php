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
        </div>

        {{-- PPh 21 Detail (Metode TER — PP 58/2023, Masa Jan–Nov) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider">PPh 21 — Metode TER</div>
                <span class="text-[10.5px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Kategori {{ $result['ter_category'] }}</span>
            </div>
            <table class="w-full text-[12px]">
                <tr><td class="py-1.5 text-gray-600">Bruto Bulanan</td><td class="py-1.5 text-right font-semibold text-gray-800">Rp {{ number_format($result['bruto_monthly'], 0, ',', '.') }}</td></tr>
                @if($result['tunjangan_pajak'] > 0)
                <tr><td class="py-1.5 text-gray-600">Tunjangan Pajak (Gross Up)</td><td class="py-1.5 text-right font-semibold text-emerald-600">+ Rp {{ number_format($result['tunjangan_pajak'], 0, ',', '.') }}</td></tr>
                @endif
                <tr><td class="py-1.5 text-gray-600">Status PTKP</td><td class="py-1.5 text-right font-semibold text-gray-800">{{ $result['ptkp_status'] }}</td></tr>
                <tr><td class="py-1.5 text-gray-600">Tarif Efektif (TER)</td><td class="py-1.5 text-right font-semibold text-gray-800">{{ rtrim(rtrim(number_format($result['ter_rate'], 2, ',', '.'), '0'), ',') }}%</td></tr>
                <tr class="border-t border-gray-200 bg-gray-50"><td class="py-2 font-bold text-gray-800">PPh 21 / Bulan</td><td class="py-2 text-right font-bold text-red-700 text-[14px]">Rp {{ number_format($result['tax_monthly'], 0, ',', '.') }}</td></tr>
            </table>
            <p class="text-[10.5px] text-gray-400 mt-3 leading-relaxed">PPh 21 = Bruto × Tarif Efektif (TER) sesuai PP No. 58 Tahun 2023, berlaku Masa Pajak Jan–Nov. Perhitungan tahunan progresif dilakukan pada Masa Desember.</p>
        </div>

        {{-- BPJS Detail --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider mb-3">BPJS</div>
            <table class="w-full text-[12px]">
                <thead>
                    <tr><th class="text-left py-1 text-gray-500">Program</th><th class="text-right py-1 text-gray-500">Perusahaan</th><th class="text-right py-1 text-gray-500">Karyawan</th></tr>
                </thead>
                <tbody>
                    @foreach(['kesehatan' => 'Kesehatan', 'jht' => 'JHT', 'jkk' => 'JKK', 'jkm' => 'JKM', 'jp' => 'JP'] as $key => $lbl)
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
