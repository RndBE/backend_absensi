@extends('admin.layouts.app')
@section('title', 'Payroll — ' . $employee->full_name)

@section('content')
@php $p = $employee->activePayroll; @endphp

{{-- Back --}}
<div class="mb-4">
    <a href="{{ route('admin.employee-payrolls.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke Daftar
    </a>
</div>

{{-- Employee Hero Card --}}
<div class="bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-2xl shadow-lg p-5 mb-5 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl font-extrabold shrink-0 shadow-inner">
            {{ substr($employee->full_name, 0, 1) }}
        </div>
        <div>
            <div class="text-[18px] font-bold text-white">{{ $employee->full_name }}</div>
            <div class="text-[13px] text-indigo-200 mt-0.5 flex items-center gap-2">
                <span>{{ $employee->employee_code }}</span>
                <span class="opacity-50">·</span>
                <span>{{ $employee->department->name ?? '-' }}</span>
                <span class="opacity-50">·</span>
                <span>{{ $employee->position ?? '-' }}</span>
            </div>
        </div>
    </div>
    <div class="text-right shrink-0">
        @if($p && $p->is_active)
            <div class="text-[11px] text-indigo-300 uppercase tracking-wider font-bold mb-0.5">Gaji Pokok Aktif</div>
            <div class="text-[22px] font-extrabold text-white">Rp {{ number_format($p->basic_salary, 0, ',', '.') }}</div>
            <div class="text-[11px] text-indigo-300 mt-0.5">Efektif: {{ $p->effective_date->format('d M Y') }}</div>
        @else
            <div class="text-[13px] text-indigo-300 italic">Belum ada data payroll</div>
        @endif
    </div>
</div>

{{-- Main Grid --}}
<div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

    {{-- LEFT: Payroll Form (3/5 width) --}}
    <div class="xl:col-span-3 space-y-4">

        {{-- Data Gaji & Jadwal --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2 bg-gray-50/60">
                <span class="material-symbols-outlined text-[17px] text-indigo-500">account_balance</span>
                <h3 class="text-[13.5px] font-bold text-gray-800">Data Payroll</h3>
            </div>
            <form action="{{ route('admin.employee-payrolls.update-payroll', $employee->id) }}" method="POST" class="p-5">
                @csrf @method('PUT')

                {{-- Gaji & Efektif --}}
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Gaji Pokok *</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-[12px] text-gray-400 font-semibold pointer-events-none">Rp</span>
                            <input type="hidden" name="basic_salary" value="{{ (int)($p->basic_salary ?? 0) }}">
                            <input type="text" data-target="basic_salary" value="{{ (int)($p->basic_salary ?? 0) }}" required
                                   class="currency-input w-full pl-9 pr-3 py-2.5 text-[13px] font-semibold border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Tanggal Efektif *</label>
                        <input type="date" name="effective_date"
                               value="{{ $p ? $p->effective_date->format('Y-m-d') : date('Y-m-d') }}" required
                               class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Jadwal Bayar *</label>
                        <select name="payment_schedule" required
                                class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="monthly"  {{ ($p && $p->payment_schedule === 'monthly')  ? 'selected' : '' }}>Bulanan</option>
                            <option value="biweekly" {{ ($p && $p->payment_schedule === 'biweekly') ? 'selected' : '' }}>2 Mingguan</option>
                            <option value="weekly"   {{ ($p && $p->payment_schedule === 'weekly')   ? 'selected' : '' }}>Mingguan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Metode Bayar *</label>
                        <select name="payment_method" required
                                class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="transfer" {{ ($p && $p->payment_method === 'transfer') ? 'selected' : '' }}>Transfer Bank</option>
                            <option value="cash"     {{ ($p && $p->payment_method === 'cash')     ? 'selected' : '' }}>Cash</option>
                        </select>
                    </div>
                </div>

                {{-- Bank & Pajak --}}
                {{-- Employee source data for JS sync --}}
                <div id="empSourceData" class="hidden"
                     data-bank-name="{{ $employee->bank_name ?? '' }}"
                     data-bank-account="{{ $employee->bank_account ?? '' }}"
                     data-bank-account-name="{{ $employee->full_name }}"
                     data-bpjs-kesehatan="{{ $employee->bpjs_kesehatan ?? '' }}"
                     data-bpjs-ketenagakerjaan="{{ $employee->bpjs_tk ?? '' }}"
                     data-npwp="{{ $employee->npwp_15 ?? $employee->npwp_16 ?? '' }}"
                     data-ptkp="{{ $employee->ptkp ?? '' }}"
                ></div>
                <div class="border-t border-gray-100 pt-4 mb-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Data Bank & Pajak</div>
                        <button type="button" onclick="syncFromEmployee()"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[13px]">sync</span> Sinkron dari Data Karyawan
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Nama Bank
                                @if($employee->bank_name && ($p?->bank_name !== $employee->bank_name))
                                    <span class="ml-1 text-[10px] font-semibold text-amber-500 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded" title="Data di profil karyawan: {{ $employee->bank_name }}">⚠ Beda dari profil</span>
                                @endif
                            </label>
                            <input type="text" name="bank_name" id="field_bank_name" value="{{ $p->bank_name ?? $employee->bank_name ?? '' }}"
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">No. Rekening
                                @if($employee->bank_account && ($p?->bank_account_number !== $employee->bank_account))
                                    <span class="ml-1 text-[10px] font-semibold text-amber-500 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded" title="Data di profil karyawan: {{ $employee->bank_account }}">⚠ Beda dari profil</span>
                                @endif
                            </label>
                            <input type="text" name="bank_account_number" id="field_bank_account_number" value="{{ $p->bank_account_number ?? $employee->bank_account ?? '' }}"
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Atas Nama Rekening</label>
                            <input type="text" name="bank_account_name" id="field_bank_account_name" value="{{ $p->bank_account_name ?? $employee->full_name ?? '' }}"
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">NPWP</label>
                            <input type="text" name="npwp" id="field_npwp" value="{{ $p->npwp ?? $employee->npwp_15 ?? $employee->npwp_16 ?? '' }}"
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status PTKP</label>
                            <select name="ptkp_status"
                                    class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— Pilih —</option>
                                {{-- Suggest from employee ptkp if not set yet --}}
                                @foreach(['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3','K/I/0','K/I/1','K/I/2','K/I/3'] as $ptkp)
                                    <option value="{{ $ptkp }}"
                                        {{ ($p && $p->ptkp_status === $ptkp) ? 'selected' : ((!$p && $employee->ptkp === $ptkp) ? 'selected' : '') }}>
                                        {{ $ptkp }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">No. BPJS Kesehatan
                                @if($employee->bpjs_kesehatan && ($p?->bpjs_kesehatan !== $employee->bpjs_kesehatan))
                                    <span class="ml-1 text-[10px] font-semibold text-amber-500 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded" title="Data di profil: {{ $employee->bpjs_kesehatan }}">⚠ Beda dari profil</span>
                                @endif
                            </label>
                            <input type="text" name="bpjs_kesehatan" id="field_bpjs_kesehatan" value="{{ $p->bpjs_kesehatan ?? $employee->bpjs_kesehatan ?? '' }}"
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">No. BPJS Ketenagakerjaan
                                @if($employee->bpjs_tk && ($p?->bpjs_ketenagakerjaan !== $employee->bpjs_tk))
                                    <span class="ml-1 text-[10px] font-semibold text-amber-500 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded" title="Data di profil: {{ $employee->bpjs_tk }}">⚠ Beda dari profil</span>
                                @endif
                            </label>
                            <input type="text" name="bpjs_ketenagakerjaan" id="field_bpjs_ketenagakerjaan" value="{{ $p->bpjs_ketenagakerjaan ?? $employee->bpjs_tk ?? '' }}"
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Denda & Lembur --}}
                <div class="border-t border-gray-100 pt-4 mb-5">
                    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3">Pengaturan Denda & Lembur</div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg bg-amber-50 border border-amber-100 hover:bg-amber-100/80 transition">
                                <input type="hidden" name="is_exempt_penalty" value="0">
                                <input type="checkbox" name="is_exempt_penalty" value="1"
                                       {{ ($p && $p->is_exempt_penalty) ? 'checked' : '' }}
                                       class="w-4 h-4 mt-0.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 shrink-0">
                                <div>
                                    <span class="text-[13px] font-semibold text-amber-800">Dikecualikan dari Denda</span>
                                    <span class="text-[11px] text-amber-600 block mt-0.5">Aktifkan untuk Direktur, Komisaris, atau level yang tidak dikenakan potongan keterlambatan & alpha.</span>
                                </div>
                            </label>
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Denda Terlambat / Hari</label>
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-[12px] text-gray-400 font-semibold pointer-events-none">Rp</span>
                                <input type="hidden" name="late_penalty_per_day" value="{{ (int)($p->late_penalty_per_day ?? 50000) }}">
                                <input type="text" data-target="late_penalty_per_day" value="{{ (int)($p->late_penalty_per_day ?? 50000) }}"
                                       class="currency-input w-full pl-9 pr-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <span class="text-[11px] text-gray-400 mt-1 block">Default: Rp 50.000 / hari. Set 0 untuk tidak ada denda.</span>
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Multiplier Lembur</label>
                            <input type="number" name="overtime_multiplier" value="{{ $p->overtime_multiplier ?? 1 }}"
                                   min="0" max="5" step="0.1"
                                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <span class="text-[11px] text-gray-400 mt-1 block">1 = standar UU (1/173), 0 = tidak ada lembur.</span>
                        </div>
                    </div>
                </div>

                {{-- Pajak --}}
                <div class="border-t border-gray-100 pt-4 mb-5">
                    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3">Pengaturan Pajak PPh 21</div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11.5px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Metode Pajak</label>
                            <select name="tax_method"
                                    class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="gross_up" {{ ($p && $p->tax_method === 'gross_up') ? 'selected' : '' }}>Gross Up — ditanggung perusahaan</option>
                                <option value="gross"    {{ ($p && $p->tax_method === 'gross')    ? 'selected' : '' }}>Gross — dipotong dari gaji</option>
                                <option value="nett"     {{ ($p && $p->tax_method === 'nett')     ? 'selected' : '' }}>Nett — perusahaan, tidak tampil</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg bg-blue-50 border border-blue-100 hover:bg-blue-100/80 transition w-full">
                                <input type="hidden" name="pph21_dtp" value="0">
                                <input type="checkbox" name="pph21_dtp" value="1"
                                       {{ ($p && $p->pph21_dtp) ? 'checked' : '' }}
                                       class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 shrink-0">
                                <div>
                                    <span class="text-[13px] font-semibold text-blue-800">PPh 21 DTP</span>
                                    <span class="text-[11px] text-blue-500 block">Ditanggung Pemerintah</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex justify-end border-t border-gray-100 pt-4">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">save</span> Simpan Payroll
                    </button>
                </div>
            </form>
        </div>

        {{-- Salary History --}}
        @if($employee->payroll->count() > 1)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2 bg-gray-50/60">
                <span class="material-symbols-outlined text-[17px] text-gray-400">history</span>
                <h3 class="text-[13.5px] font-bold text-gray-800">Riwayat Gaji</h3>
                <span class="ml-auto text-[11px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ $employee->payroll->count() }} record</span>
            </div>
            <div class="p-4 space-y-2">
                @foreach($employee->payroll as $hist)
                <div class="flex items-center justify-between p-3 rounded-lg
                    {{ $hist->is_active ? 'bg-indigo-50 border border-indigo-200' : 'bg-gray-50 border border-gray-100' }}">
                    <div>
                        <div class="text-[13px] font-bold {{ $hist->is_active ? 'text-indigo-800' : 'text-gray-700' }}">
                            Rp {{ number_format($hist->basic_salary, 0, ',', '.') }}
                        </div>
                        <div class="text-[11px] text-gray-400 mt-0.5">Efektif: {{ $hist->effective_date->format('d M Y') }}</div>
                    </div>
                    @if($hist->is_active)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-600 text-white">
                            <span class="material-symbols-outlined text-[11px]">check_circle</span> Aktif
                        </span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- RIGHT: Components (2/5 width) --}}
    <div class="xl:col-span-2 space-y-4">
        @php
            $activeComps  = $employee->payrollComponents->where('is_active', true);
            $earnings     = $activeComps->filter(fn($c) => $c->component && $c->component->type === 'earning');
            $deductions   = $activeComps->filter(fn($c) => $c->component && $c->component->type === 'deduction');
        @endphp

        {{-- Summary Badges --}}
        @if($activeComps->count() > 0)
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center">
                <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wide mb-0.5">Total Earning</div>
                <div class="text-[15px] font-extrabold text-emerald-700">
                    Rp {{ number_format($earnings->sum('amount'), 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-emerald-500 mt-0.5">{{ $earnings->count() }} komponen</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-xl p-3 text-center">
                <div class="text-[11px] font-bold text-red-600 uppercase tracking-wide mb-0.5">Total Deduction</div>
                <div class="text-[15px] font-extrabold text-red-700">
                    Rp {{ number_format($deductions->sum('amount'), 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-red-500 mt-0.5">{{ $deductions->count() }} komponen</div>
            </div>
        </div>
        @endif

        {{-- Component List --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2 bg-gray-50/60">
                <span class="material-symbols-outlined text-[17px] text-emerald-500">list_alt</span>
                <h3 class="text-[13.5px] font-bold text-gray-800">Komponen Terpasang</h3>
                <button onclick="document.getElementById('assignModal').classList.remove('hidden')"
                        class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 text-[11.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                    <span class="material-symbols-outlined text-[14px]">add</span> Assign
                </button>
            </div>
            <div class="p-4">
                @if($earnings->count() > 0)
                <div class="mb-4">
                    <div class="flex items-center gap-1.5 mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                        <span class="text-[10.5px] font-bold text-emerald-600 uppercase tracking-widest">Earning</span>
                    </div>
                    <div class="space-y-2">
                        @foreach($earnings as $ec)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-emerald-50 border border-emerald-100 group">
                            <div class="min-w-0 flex-1">
                                <div class="text-[12.5px] font-semibold text-gray-800 truncate">{{ $ec->component->name }}</div>
                                <div class="text-[10.5px] text-gray-400">
                                    {{ $ec->start_date->format('d/m/Y') }}
                                    {{ $ec->end_date ? '– ' . $ec->end_date->format('d/m/Y') : '– selamanya' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 ml-2">
                                <span class="text-[13px] font-bold text-emerald-700">Rp {{ number_format($ec->amount, 0, ',', '.') }}</span>
                                <form action="{{ route('admin.employee-payrolls.toggle-component', [$employee->id, $ec->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" title="Nonaktifkan"
                                            class="p-1 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition cursor-pointer opacity-0 group-hover:opacity-100">
                                        <span class="material-symbols-outlined text-[15px]">remove_circle</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($deductions->count() > 0)
                <div class="mb-4">
                    <div class="flex items-center gap-1.5 mb-2">
                        <span class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                        <span class="text-[10.5px] font-bold text-red-600 uppercase tracking-widest">Deduction</span>
                    </div>
                    <div class="space-y-2">
                        @foreach($deductions as $ec)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-red-50 border border-red-100 group">
                            <div class="min-w-0 flex-1">
                                <div class="text-[12.5px] font-semibold text-gray-800 truncate">{{ $ec->component->name }}</div>
                                <div class="text-[10.5px] text-gray-400">
                                    {{ $ec->start_date->format('d/m/Y') }}
                                    {{ $ec->end_date ? '– ' . $ec->end_date->format('d/m/Y') : '– selamanya' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 ml-2">
                                <span class="text-[13px] font-bold text-red-700">Rp {{ number_format($ec->amount, 0, ',', '.') }}</span>
                                <form action="{{ route('admin.employee-payrolls.toggle-component', [$employee->id, $ec->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" title="Nonaktifkan"
                                            class="p-1 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition cursor-pointer opacity-0 group-hover:opacity-100">
                                        <span class="material-symbols-outlined text-[15px]">remove_circle</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($activeComps->count() === 0)
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <span class="material-symbols-outlined text-[36px] text-gray-200 mb-2">list_alt</span>
                    <div class="text-[12px] text-gray-400 font-medium">Belum ada komponen</div>
                    <div class="text-[11px] text-gray-300 mt-0.5">Klik tombol Assign untuk menambahkan</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Info Card --}}
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[18px] text-indigo-400 shrink-0 mt-0.5">info</span>
                <div class="text-[11.5px] text-indigo-700 leading-relaxed">
                    <strong class="block mb-1 font-bold">Info Komponen</strong>
                    Komponen yang nonaktif tidak akan ikut diperhitungkan saat Run Payroll. Hover pada komponen lalu klik ikon hapus untuk menonaktifkan.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Assign Component Modal --}}
<div id="assignModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900">Assign Komponen Gaji</h3>
            <button type="button" onclick="document.getElementById('assignModal').classList.add('hidden')"
                    class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 cursor-pointer transition">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <form action="{{ route('admin.employee-payrolls.assign-component', $employee->id) }}" method="POST" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Komponen *</label>
                <select name="payroll_component_id" required
                        class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        onchange="updateDefaultAmount(this)">
                    <option value="">— Pilih Komponen —</option>
                    @foreach($components as $c)
                        <option value="{{ $c->id }}" data-amount="{{ $c->default_amount }}">
                            {{ $c->name }} ({{ ucfirst($c->type) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Nominal *</label>
                <div class="relative flex items-center">
                    <span class="absolute left-3 text-[12px] text-gray-400 font-semibold pointer-events-none">Rp</span>
                    <input type="hidden" name="amount" id="assignAmountHidden" value="0">
                    <input type="text" data-target="amount" id="assignAmount" value="0" required
                           class="currency-input w-full pl-9 pr-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Tanggal Mulai *</label>
                    <input type="date" name="start_date" value="{{ date('Y-m-01') }}" required
                           class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Tanggal Berakhir</label>
                    <input type="date" name="end_date"
                           class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('assignModal').classList.add('hidden')"
                        class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition cursor-pointer">
                    Batal
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                    <span class="material-symbols-outlined text-[15px]">add_circle</span> Assign
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Toast --}}
<div id="syncToast" class="hidden fixed bottom-6 right-6 z-[999] bg-gray-900 text-white text-[13px] font-semibold px-4 py-2.5 rounded-xl shadow-xl flex items-center gap-2">
    <span class="material-symbols-outlined text-[16px] text-emerald-400">check_circle</span>
    <span id="syncToastMsg">Data disinkronkan dari profil karyawan</span>
</div>

<script>
function syncFromEmployee() {
    const src = document.getElementById('empSourceData');
    if (!src) return;

    const fields = {
        'field_bank_name':             src.dataset.bankName,
        'field_bank_account_number':   src.dataset.bankAccount,
        'field_bank_account_name':     src.dataset.bankAccountName,
        'field_bpjs_kesehatan':        src.dataset.bpjsKesehatan,
        'field_bpjs_ketenagakerjaan':  src.dataset.bpjsKetenagakerjaan,
        'field_npwp':                  src.dataset.npwp,
    };

    let synced = 0;
    Object.entries(fields).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el && val) { el.value = val; synced++; }
    });

    // Sync ptkp_status select
    const ptkpSel = document.querySelector('select[name="ptkp_status"]');
    const ptkpVal = src.dataset.ptkp;
    if (ptkpSel && ptkpVal) {
        Array.from(ptkpSel.options).forEach(o => {
            o.selected = o.value === ptkpVal;
        });
        synced++;
    }

    // Show toast
    const toast = document.getElementById('syncToast');
    const msg = document.getElementById('syncToastMsg');
    msg.textContent = synced > 0
        ? `${synced} field disinkronkan dari profil karyawan`
        : 'Tidak ada data di profil karyawan untuk disinkronkan';
    toast.classList.remove('hidden');
    toast.classList.add('flex');
    setTimeout(() => {
        toast.classList.add('hidden');
        toast.classList.remove('flex');
    }, 3000);
}

function updateDefaultAmount(select) {
    const opt = select.options[select.selectedIndex];
    const amount = opt.dataset.amount || 0;
    const el = document.getElementById('assignAmount');
    const hidden = document.getElementById('assignAmountHidden');
    hidden.value = amount;
    el.value = String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// ── Currency input initialisation ──────────────────────────────────────────
function fmtCurrency(raw) {
    // raw is always a plain integer string from DB (no decimals, no dots)
    const num = parseInt(String(raw).replace(/[^0-9]/g, ''), 10) || 0;
    return num.toLocaleString('id-ID');   // e.g. 2.600.000
}

function rawNum(formatted) {
    // formatted may have dots as thousand separators (id-ID locale) — strip them all
    return parseInt(String(formatted).replace(/\./g, '').replace(/[^0-9]/g, ''), 10) || 0;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.currency-input').forEach(function (el) {
        // On load: format the initial raw value
        const initial = rawNum(el.value);
        el.value = initial ? fmtCurrency(initial) : '';

        // Keep hidden field in sync
        const targetName = el.dataset.target;
        if (targetName) {
            const hidden = document.querySelector(`input[type="hidden"][name="${targetName}"]`);
            if (hidden) hidden.value = initial;

            el.addEventListener('input', function () {
                const raw = rawNum(el.value);
                el.value = fmtCurrency(raw);
                if (hidden) hidden.value = raw;
            });

            // On blur: reformat neatly
            el.addEventListener('blur', function () {
                const raw = rawNum(el.value);
                el.value = raw ? fmtCurrency(raw) : '';
                if (hidden) hidden.value = raw;
            });
        }
    });
});
</script>
@endsection
