@extends('admin.layouts.app')
@section('title', 'Payroll — ' . $employee->full_name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.employee-payrolls.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke Daftar
    </a>
</div>

{{-- Employee Info --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-lg font-bold shrink-0">{{ substr($employee->full_name, 0, 1) }}</div>
        <div>
            <div class="text-[16px] font-bold text-gray-900">{{ $employee->full_name }}</div>
            <div class="text-[13px] text-gray-500">{{ $employee->employee_code }} · {{ $employee->department->name ?? '-' }} · {{ $employee->position ?? '-' }}</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- Left: Payroll Master --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">account_balance</span> Data Payroll</h3>
        </div>
        <form action="{{ route('admin.employee-payrolls.update-payroll', $employee->id) }}" method="POST" class="p-5 space-y-4">
            @csrf @method('PUT')

            @php $p = $employee->activePayroll; @endphp

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Payroll Group</label>
                    <select name="payroll_group_id" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">— Tidak ada —</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}" {{ ($p && $p->payroll_group_id == $g->id) ? 'selected' : '' }}>{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Gaji Pokok *</label>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[12px] text-gray-400 font-semibold">Rp</span>
                        <input type="hidden" name="basic_salary" value="{{ $p->basic_salary ?? 0 }}">
                        <input type="text" data-target="basic_salary" value="{{ $p->basic_salary ?? 0 }}" required class="currency-input w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Efektif *</label>
                    <input type="date" name="effective_date" value="{{ $p ? $p->effective_date->format('Y-m-d') : date('Y-m-d') }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Jadwal Bayar *</label>
                    <select name="payment_schedule" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="monthly" {{ ($p && $p->payment_schedule === 'monthly') ? 'selected' : '' }}>Bulanan</option>
                        <option value="biweekly" {{ ($p && $p->payment_schedule === 'biweekly') ? 'selected' : '' }}>2 Mingguan</option>
                        <option value="weekly" {{ ($p && $p->payment_schedule === 'weekly') ? 'selected' : '' }}>Mingguan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Metode Bayar *</label>
                    <select name="payment_method" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="transfer" {{ ($p && $p->payment_method === 'transfer') ? 'selected' : '' }}>Transfer Bank</option>
                        <option value="cash" {{ ($p && $p->payment_method === 'cash') ? 'selected' : '' }}>Cash</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider mb-3">Data Bank & Pajak</div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nama Bank</label>
                        <input type="text" name="bank_name" value="{{ $p->bank_name ?? '' }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">No. Rekening</label>
                        <input type="text" name="bank_account_number" value="{{ $p->bank_account_number ?? '' }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">Atas Nama Rekening</label>
                        <input type="text" name="bank_account_name" value="{{ $p->bank_account_name ?? '' }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">NPWP</label>
                        <input type="text" name="npwp" value="{{ $p->npwp ?? '' }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">Status PTKP</label>
                        <select name="ptkp_status" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">— Pilih —</option>
                            @foreach(['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3','K/I/0','K/I/1','K/I/2','K/I/3'] as $ptkp)
                                <option value="{{ $ptkp }}" {{ ($p && $p->ptkp_status === $ptkp) ? 'selected' : '' }}>{{ $ptkp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">No. BPJS Kesehatan</label>
                        <input type="text" name="bpjs_kesehatan" value="{{ $p->bpjs_kesehatan ?? '' }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">No. BPJS Ketenagakerjaan</label>
                        <input type="text" name="bpjs_ketenagakerjaan" value="{{ $p->bpjs_ketenagakerjaan ?? '' }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider mb-3">Pengaturan Denda & Lembur</div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="is_exempt_penalty" value="0">
                            <input type="checkbox" name="is_exempt_penalty" value="1" {{ ($p && $p->is_exempt_penalty) ? 'checked' : '' }}
                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <div>
                                <span class="text-[13px] font-semibold text-gray-700">Dikecualikan dari Denda</span>
                                <span class="text-[11px] text-gray-500 block">Aktifkan untuk Direktur, Komisaris, atau level yang tidak dikenakan potongan keterlambatan & alpha</span>
                            </div>
                        </label>
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">Denda Terlambat / Hari</label>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[12px] text-gray-400 font-semibold">Rp</span>
                            <input type="hidden" name="late_penalty_per_day" value="{{ $p->late_penalty_per_day ?? 50000 }}">
                            <input type="text" data-target="late_penalty_per_day" value="{{ $p->late_penalty_per_day ?? 50000 }}" class="currency-input w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <span class="text-[11px] text-gray-400">Default: Rp 50.000 / hari. Set 0 untuk tidak ada denda terlambat.</span>
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">Multiplier Lembur</label>
                        <input type="number" name="overtime_multiplier" value="{{ $p->overtime_multiplier ?? 1 }}" min="0" max="5" step="0.1" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <span class="text-[11px] text-gray-400">1 = standar UU (1/173), 0 = tidak ada lembur</span>
                    </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wider mb-3">Pengaturan Pajak</div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1">Metode Pajak</label>
                        <select name="tax_method" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="gross_up" {{ ($p && $p->tax_method === 'gross_up') ? 'selected' : '' }}>Gross Up (pajak ditanggung perusahaan)</option>
                            <option value="gross" {{ ($p && $p->tax_method === 'gross') ? 'selected' : '' }}>Gross (pajak dipotong dari gaji)</option>
                            <option value="nett" {{ ($p && $p->tax_method === 'nett') ? 'selected' : '' }}>Nett (pajak perusahaan, tidak tampil)</option>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <label class="flex items-center gap-3 cursor-pointer pt-4">
                            <input type="hidden" name="pph21_dtp" value="0">
                            <input type="checkbox" name="pph21_dtp" value="1" {{ ($p && $p->pph21_dtp) ? 'checked' : '' }}
                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <div>
                                <span class="text-[13px] font-semibold text-gray-700">PPh 21 DTP</span>
                                <span class="text-[11px] text-gray-500 block">Ditanggung Pemerintah</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan Payroll</button>
            </div>
        </form>

        {{-- History --}}
        @if($employee->payroll->count() > 1)
        <div class="px-5 pb-5">
            <details class="group">
                <summary class="text-[12px] font-semibold text-indigo-600 cursor-pointer hover:text-indigo-800">Riwayat Payroll ({{ $employee->payroll->count() }} record)</summary>
                <div class="mt-2 space-y-2">
                    @foreach($employee->payroll as $hist)
                    <div class="flex items-center justify-between p-3 rounded-lg {{ $hist->is_active ? 'bg-indigo-50 border border-indigo-200' : 'bg-gray-50 border border-gray-200' }}">
                        <div>
                            <div class="text-[12px] font-semibold text-gray-800">Rp {{ number_format($hist->basic_salary, 0, ',', '.') }}</div>
                            <div class="text-[11px] text-gray-500">Efektif: {{ $hist->effective_date->format('d/m/Y') }}</div>
                        </div>
                        @if($hist->is_active)
                            <span class="text-[10px] font-bold text-indigo-600 uppercase">Aktif</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </details>
        </div>
        @endif
    </div>

    {{-- Right: Assigned Components --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">list_alt</span> Komponen Terpasang</h3>
            <button onclick="document.getElementById('assignModal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                <span class="material-symbols-outlined text-[14px]">add</span> Assign
            </button>
        </div>
        <div class="p-5">
            @php
                $activeComps = $employee->payrollComponents->where('is_active', true);
                $earnings = $activeComps->filter(fn($c) => $c->component && $c->component->type === 'earning');
                $deductions = $activeComps->filter(fn($c) => $c->component && $c->component->type === 'deduction');
            @endphp

            @if($earnings->count() > 0)
            <div class="mb-4">
                <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider mb-2">Earning</div>
                @foreach($earnings as $ec)
                <div class="flex items-center justify-between p-3 mb-2 rounded-lg bg-emerald-50 border border-emerald-100">
                    <div>
                        <div class="text-[13px] font-semibold text-gray-800">{{ $ec->component->name }}</div>
                        <div class="text-[11px] text-gray-500">{{ $ec->start_date->format('d/m/Y') }} {{ $ec->end_date ? '— ' . $ec->end_date->format('d/m/Y') : '— Tanpa batas' }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[13px] font-bold text-emerald-700">Rp {{ number_format($ec->amount, 0, ',', '.') }}</span>
                        <form action="{{ route('admin.employee-payrolls.toggle-component', [$employee->id, $ec->id]) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="p-1 rounded hover:bg-emerald-100 text-gray-400 hover:text-red-500 transition-colors cursor-pointer" title="Nonaktifkan"><span class="material-symbols-outlined text-[14px]">visibility_off</span></button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            @if($deductions->count() > 0)
            <div class="mb-4">
                <div class="text-[11px] font-bold text-red-600 uppercase tracking-wider mb-2">Deduction</div>
                @foreach($deductions as $ec)
                <div class="flex items-center justify-between p-3 mb-2 rounded-lg bg-red-50 border border-red-100">
                    <div>
                        <div class="text-[13px] font-semibold text-gray-800">{{ $ec->component->name }}</div>
                        <div class="text-[11px] text-gray-500">{{ $ec->start_date->format('d/m/Y') }} {{ $ec->end_date ? '— ' . $ec->end_date->format('d/m/Y') : '— Tanpa batas' }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[13px] font-bold text-red-700">Rp {{ number_format($ec->amount, 0, ',', '.') }}</span>
                        <form action="{{ route('admin.employee-payrolls.toggle-component', [$employee->id, $ec->id]) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="p-1 rounded hover:bg-red-100 text-gray-400 hover:text-red-500 transition-colors cursor-pointer" title="Nonaktifkan"><span class="material-symbols-outlined text-[14px]">visibility_off</span></button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            @if($activeComps->count() === 0)
                <div class="text-center py-8 text-gray-400 text-sm">Belum ada komponen terpasang</div>
            @endif
        </div>
    </div>
</div>

{{-- Assign Component Modal --}}
<div id="assignModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900">Assign Komponen</h3>
        </div>
        <form action="{{ route('admin.employee-payrolls.assign-component', $employee->id) }}" method="POST" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Komponen *</label>
                <select name="payroll_component_id" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" onchange="updateDefaultAmount(this)">
                    <option value="">— Pilih —</option>
                    @foreach($components as $c)
                        <option value="{{ $c->id }}" data-amount="{{ $c->default_amount }}">{{ $c->name }} ({{ ucfirst($c->type) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nominal *</label>
                <div class="flex items-center gap-1.5">
                    <span class="text-[12px] text-gray-400 font-semibold">Rp</span>
                    <input type="hidden" name="amount" id="assignAmountHidden" value="0">
                    <input type="text" data-target="amount" id="assignAmount" value="0" required class="currency-input w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Mulai *</label>
                    <input type="date" name="start_date" value="{{ date('Y-m-01') }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Berakhir</label>
                    <input type="date" name="end_date" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('assignModal').classList.add('hidden')" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Assign</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateDefaultAmount(select) {
    const opt = select.options[select.selectedIndex];
    const amount = opt.dataset.amount || 0;
    const el = document.getElementById('assignAmount');
    const hidden = document.getElementById('assignAmountHidden');
    hidden.value = amount;
    el.value = String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
</script>
@endsection
