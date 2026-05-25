@extends('admin.layouts.app')
@section('title', 'Tax & BPJS Settings')

@section('content')
<div class="mb-5">
    <h2 class="text-[20px] font-bold text-gray-900">Tax & BPJS Settings</h2>
    <p class="text-[13px] text-gray-500">Kelola tarif pajak, PTKP, dan rate BPJS</p>
</div>

@if(session('success'))
<div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-[13px] rounded-lg">{{ session('success') }}</div>
@endif

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-lg">
    <div class="font-semibold mb-1">Data belum bisa disimpan.</div>
    <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ==================== PPh 21 ==================== --}}

{{-- Tarif Progresif PPh 21 --}}
@php $brackets = $taxSettings->firstWhere('key', 'pph21_brackets'); @endphp
@if($brackets)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-indigo-500">receipt_long</span>
            <h3 class="text-[15px] font-bold text-gray-900">Tarif Progresif PPh 21</h3>
        </div>
        <span class="text-[11px] text-gray-400">Berlaku sejak {{ $brackets->effective_date->format('d/m/Y') }}</span>
    </div>
    <form action="{{ route('admin.tax.update-setting', $brackets->id) }}" method="POST">
        @csrf @method('PUT')
        <input type="hidden" name="setting_type" value="brackets">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Layer</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Dari (Rp)</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Sampai (Rp)</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Tarif (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($brackets->value as $i => $b)
                    <tr>
                        <td class="px-4 py-2.5 border-b border-gray-100 text-[12px] text-gray-500">{{ $i + 1 }}</td>
                        <td class="px-4 py-2.5 border-b border-gray-100 text-right">
                            <input type="hidden" name="brackets[{{ $i }}][min]" value="{{ $b['min'] }}">
                            <input type="text" data-target="brackets[{{ $i }}][min]" value="{{ $b['min'] }}" class="currency-input w-[160px] px-2.5 py-1.5 text-[12px] text-right border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        </td>
                        <td class="px-4 py-2.5 border-b border-gray-100 text-right">
                            @if($b['max'] !== null)
                            <input type="hidden" name="brackets[{{ $i }}][max]" value="{{ $b['max'] }}">
                            <input type="text" data-target="brackets[{{ $i }}][max]" value="{{ $b['max'] }}" class="currency-input w-[160px] px-2.5 py-1.5 text-[12px] text-right border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            @else
                            <input type="hidden" name="brackets[{{ $i }}][max]" value="">
                            <span class="text-[12px] text-gray-400 italic">Tidak terbatas</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 border-b border-gray-100 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <input type="number" name="brackets[{{ $i }}][rate]" value="{{ $b['rate'] }}" step="0.1" min="0" max="100" class="w-[80px] px-2.5 py-1.5 text-[12px] text-right border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                <span class="text-[12px] text-gray-400">%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan Tarif</button>
        </div>
    </form>
</div>
@endif

{{-- PTKP --}}
@php $ptkp = $taxSettings->firstWhere('key', 'ptkp_values'); @endphp
@if($ptkp)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-emerald-500">person</span>
            <h3 class="text-[15px] font-bold text-gray-900">PTKP (Penghasilan Tidak Kena Pajak)</h3>
        </div>
        <span class="text-[11px] text-gray-400">Berlaku sejak {{ $ptkp->effective_date->format('d/m/Y') }}</span>
    </div>
    <form action="{{ route('admin.tax.update-setting', $ptkp->id) }}" method="POST">
        @csrf @method('PUT')
        <input type="hidden" name="setting_type" value="ptkp">
        <div class="p-5">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($ptkp->value as $status => $value)
                <div class="p-3 rounded-lg border border-gray-200 bg-gray-50">
                    <label class="block text-[11px] font-bold text-gray-600 mb-1">{{ $status }}</label>
                    <div class="flex items-center gap-1">
                        <span class="text-[11px] text-gray-400">Rp</span>
                        <input type="hidden" name="ptkp[{{ $status }}]" value="{{ $value }}">
                        <input type="text" data-target="ptkp[{{ $status }}]" value="{{ $value }}" class="currency-input w-full px-2.5 py-1.5 text-[12px] text-right border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan PTKP</button>
        </div>
    </form>
</div>
@endif

{{-- Biaya Jabatan --}}
@php $bj = $taxSettings->firstWhere('key', 'biaya_jabatan'); @endphp
@if($bj)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-amber-500">work</span>
            <h3 class="text-[15px] font-bold text-gray-900">Biaya Jabatan</h3>
        </div>
        <span class="text-[11px] text-gray-400">Berlaku sejak {{ $bj->effective_date->format('d/m/Y') }}</span>
    </div>
    <form action="{{ route('admin.tax.update-setting', $bj->id) }}" method="POST">
        @csrf @method('PUT')
        <input type="hidden" name="setting_type" value="biaya_jabatan">
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Persentase (%)</label>
                    <div class="flex items-center gap-1">
                        <input type="number" name="bj_percentage" value="{{ $bj->value['percentage'] ?? 5 }}" step="0.1" min="0" max="100" class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <span class="text-[12px] text-gray-400">%</span>
                    </div>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Maks. per Bulan</label>
                    <div class="flex items-center gap-1">
                        <span class="text-[12px] text-gray-400">Rp</span>
                        <input type="hidden" name="bj_max_monthly" value="{{ $bj->value['max_monthly'] ?? 500000 }}">
                        <input type="text" data-target="bj_max_monthly" value="{{ $bj->value['max_monthly'] ?? 500000 }}" class="currency-input w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Maks. per Tahun</label>
                    <div class="flex items-center gap-1">
                        <span class="text-[12px] text-gray-400">Rp</span>
                        <input type="hidden" name="bj_max_annual" value="{{ $bj->value['max_annual'] ?? 6000000 }}">
                        <input type="text" data-target="bj_max_annual" value="{{ $bj->value['max_annual'] ?? 6000000 }}" class="currency-input w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan</button>
        </div>
    </form>
</div>
@endif

{{-- ==================== BPJS ==================== --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px] text-blue-500">health_and_safety</span>
        <h3 class="text-[15px] font-bold text-gray-900">Tarif BPJS</h3>
    </div>
    <form action="{{ route('admin.tax.update-bpjs-all') }}" method="POST">
        @csrf @method('PUT')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Program</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Perusahaan (%)</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Karyawan (%)</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase text-gray-500 border-b">Batas Gaji (Rp)</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Berlaku</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $bpjsLabels = [
                            'kes_rate' => ['name' => 'BPJS Kesehatan', 'cap_key' => 'kes_cap', 'icon' => '🏥'],
                            'jht_rate' => ['name' => 'Jaminan Hari Tua (JHT)', 'cap_key' => null, 'icon' => '🏦'],
                            'jkk_rate' => ['name' => 'Jaminan Kecelakaan Kerja (JKK)', 'cap_key' => null, 'icon' => '⚠️'],
                            'jkm_rate' => ['name' => 'Jaminan Kematian (JKM)', 'cap_key' => null, 'icon' => '🛡️'],
                            'jp_rate'  => ['name' => 'Jaminan Pensiun (JP)', 'cap_key' => 'jp_cap', 'icon' => '👴'],
                        ];
                    @endphp
                    @foreach($bpjsLabels as $key => $info)
                        @php
                            $setting = $bpjsSettings->firstWhere('key', $key);
                            $capSetting = $info['cap_key'] ? $bpjsSettings->firstWhere('key', $info['cap_key']) : null;
                        @endphp
                        @if($setting)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <span class="text-[16px]">{{ $info['icon'] }}</span>
                                    <div>
                                        <div class="text-[13px] font-semibold text-gray-800">{{ $info['name'] }}</div>
                                        <input type="hidden" name="bpjs[{{ $key }}][id]" value="{{ $setting->id }}">
                                        @if($capSetting)
                                        <input type="hidden" name="bpjs[{{ $key }}][cap_id]" value="{{ $capSetting->id }}">
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 border-b border-gray-100 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <input type="number" name="bpjs[{{ $key }}][company]" value="{{ $setting->value['company'] ?? 0 }}" step="0.01" min="0" max="100" class="w-[80px] px-2.5 py-1.5 text-[12px] text-right border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <span class="text-[12px] text-gray-400">%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 border-b border-gray-100 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <input type="number" name="bpjs[{{ $key }}][employee]" value="{{ $setting->value['employee'] ?? 0 }}" step="0.01" min="0" max="100" class="w-[80px] px-2.5 py-1.5 text-[12px] text-right border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <span class="text-[12px] text-gray-400">%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 border-b border-gray-100 text-right">
                                @if($capSetting)
                                <div class="flex items-center justify-end gap-1">
                                    <span class="text-[11px] text-gray-400">Rp</span>
                                    <input type="hidden" name="bpjs[{{ $key }}][salary_cap]" value="{{ $capSetting->value['salary_cap'] ?? 0 }}">
                                    <input type="text" data-target="bpjs[{{ $key }}][salary_cap]" value="{{ $capSetting->value['salary_cap'] ?? 0 }}" class="currency-input w-[140px] px-2.5 py-1.5 text-[12px] text-right border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                </div>
                                @else
                                <span class="text-[12px] text-gray-400 italic">Tidak ada batas</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 border-b border-gray-100 text-[11px] text-gray-400">{{ $setting->effective_date->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- NPP --}}
        <div class="px-5 py-3 border-t border-gray-100">
            <div class="flex items-center gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">NPP BPJS Ketenagakerjaan</label>
                    <input type="text" name="npp" value="{{ $bpjsSettings->first()?->npp }}" placeholder="Contoh: 12345678" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg w-[200px] focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex-1"></div>
                <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan Semua BPJS</button>
            </div>
        </div>
    </form>
</div>
@endsection
