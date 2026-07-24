@extends('admin.layouts.app')
@section('title', 'Payslip Karyawan')

@section('content')
@php
    $currentAdmin = \App\Models\Employee::find(session('admin_id'));
    $canEditPayslip = $currentAdmin && app(\App\Support\AdminPermission::class)->can($currentAdmin, 'payroll.runs.update');
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">receipt</span> Payslip Karyawan</h3>
        <button type="button" class="inline-flex h-[38px] shrink-0 items-center gap-1.5 rounded-lg bg-indigo-600 px-3 text-[12px] font-semibold text-white transition hover:bg-indigo-700 cursor-pointer" data-payslip-import-open>
            <span class="material-symbols-outlined text-[15px] shrink-0">upload</span>
            Import Payslip
        </button>
    </div>
    <div class="p-5">
        {{-- Filters --}}
        <form method="GET" id="payslipFilterForm" class="flex items-center gap-3 mb-5 flex-wrap">
            <input type="search" id="payslipSearch" placeholder="Cari nama / kode karyawan..."
                   class="w-full max-w-[280px] h-[42px] px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <select name="period" onchange="document.getElementById('payslipFilterForm').submit()" class="w-full max-w-[280px] h-[42px] px-3 py-2 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Periode</option>
                @foreach($periods as $p)
                    <option value="{{ $p }}" {{ request('period') === $p ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($p . '-01')->translatedFormat('F Y') }}</option>
                @endforeach
            </select>
            @if(request('period'))
                <a href="{{ route('admin.payslips.index') }}" class="inline-flex items-center px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Periode</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Gaji Pokok</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Earning</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Deduction</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Net Salary</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payslips as $ps)
                    <tr class="hover:bg-gray-50 transition-colors" data-fuse-row="payslip" data-search="{{ e(($ps->employee->full_name ?? '') . ' ' . ($ps->employee->employee_code ?? '') . ' ' . ($ps->employee->department->name ?? '') . ' ' . ($ps->employee->position ?? '') . ' ' . ($ps->payrollRun->period ?? '') . ' ' . \Carbon\Carbon::parse($ps->payrollRun->period . '-01')->translatedFormat('F Y')) }}">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($ps->employee->full_name ?? '?', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $ps->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $ps->employee->employee_code ?? '' }} · {{ $ps->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700">{{ \Carbon\Carbon::parse($ps->payrollRun->period . '-01')->translatedFormat('F Y') }}</span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] text-gray-700">Rp {{ number_format($ps->basic_salary, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-emerald-700">Rp {{ number_format($ps->total_earning, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-red-600">Rp {{ number_format($ps->total_deduction, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[14px] font-bold text-gray-900">Rp {{ number_format($ps->net_salary, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($canEditPayslip && ($ps->payrollRun->status ?? null) !== 'locked')
                                <button type="button" onclick="openPayslipEdit({{ $ps->id }})" class="p-1.5 rounded-lg hover:bg-amber-50 text-gray-400 hover:text-amber-600 transition-colors cursor-pointer" title="Edit Payslip"><span class="material-symbols-outlined text-[16px]">edit</span></button>
                                @endif
                                <a href="{{ route('admin.payslips.show', $ps->id) }}" class="p-1.5 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors" title="Detail"><span class="material-symbols-outlined text-[16px]">visibility</span></a>
                                <a href="{{ route('admin.payslips.download', $ps->id) }}" class="p-1.5 rounded-lg hover:bg-emerald-50 text-gray-400 hover:text-emerald-600 transition-colors" title="Download PDF"><span class="material-symbols-outlined text-[16px]">download</span></a>
                                @if($canEditPayslip && ($ps->payrollRun->status ?? null) !== 'locked')
                                <form action="{{ route('admin.payslips.destroy', $ps->id) }}" method="POST" class="inline" data-confirm="Hapus payslip {{ $ps->employee->full_name ?? 'karyawan' }} periode {{ $ps->payrollRun->period ?? '-' }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors cursor-pointer" title="Hapus Payslip"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Belum ada payslip yang di-publish</td></tr>
                    @endforelse
                    <tr id="payslipFuseEmpty" class="hidden">
                        <td colspan="7" class="text-center py-12 text-gray-400 text-sm">Tidak ada payslip yang cocok dengan pencarian</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canEditPayslip)
@foreach($payslips as $ps)
@continue(($ps->payrollRun->status ?? null) === 'locked')
@php
    $components = collect($ps->components ?? [])->values();
@endphp
<div id="payslipEditModal-{{ $ps->id }}" class="hidden fixed inset-0 z-[95] items-start justify-center overflow-y-auto px-4 py-6">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" data-payslip-edit-close="{{ $ps->id }}"></div>
    <form action="{{ route('admin.payslips.update', $ps->id) }}" method="POST" class="relative w-full max-w-4xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl" data-payslip-edit-form>
        @csrf
        @method('PUT')
        <div class="sticky top-0 z-10 border-b border-gray-100 bg-white px-4 py-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-[13px] font-bold text-indigo-700">{{ substr($ps->employee->full_name ?? '?', 0, 1) }}</div>
                    <div class="min-w-0">
                        <h3 class="truncate text-[15px] font-bold text-gray-900">Edit Payslip</h3>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-gray-500">
                            <span class="font-semibold text-gray-700">{{ $ps->employee->full_name ?? '-' }}</span>
                            <span>&middot;</span>
                            <span>{{ $ps->employee->employee_code ?? '-' }}</span>
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">{{ \Carbon\Carbon::parse($ps->payrollRun->period . '-01')->translatedFormat('F Y') }}</span>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600">{{ strtoupper($ps->payrollRun->status ?? '-') }}</span>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="closePayslipEdit({{ $ps->id }})" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>
        <div class="space-y-4 px-4 py-4">
            <div class="grid gap-3 lg:grid-cols-[minmax(220px,280px)_1fr]">
                <label class="block rounded-lg border border-gray-200 bg-gray-50 px-3 py-3">
                    <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-gray-500">Basic Salary</span>
                    <input type="number" min="0" step="1" name="basic_salary" value="{{ (float) $ps->basic_salary }}" required data-basic-salary class="h-[40px] w-full rounded-lg border border-gray-300 bg-white px-3 text-right text-[14px] font-bold text-gray-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                </label>
                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Total Earning</div>
                        <div class="mt-1 text-right text-[14px] font-bold text-emerald-800" data-summary-earning>Rp {{ number_format($ps->total_earning, 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-red-700">Total Deduction</div>
                        <div class="mt-1 text-right text-[14px] font-bold text-red-700" data-summary-deduction>Rp {{ number_format($ps->total_deduction, 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Take Home Pay</div>
                        <div class="mt-1 text-right text-[14px] font-bold text-indigo-800" data-summary-net>Rp {{ number_format($ps->net_salary, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200" style="max-height: min(52vh, 520px);">
                <table class="w-full min-w-[650px]">
                    <thead class="sticky top-0 z-[1]">
                        <tr>
                            <th class="bg-gray-50 px-2 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500">Nama Komponen</th>
                            <th class="bg-gray-50 px-2 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500">Tipe</th>
                            <th class="bg-gray-50 px-2 py-2 text-right text-[10px] font-bold uppercase tracking-wider text-gray-500">Nominal</th>
                            <th class="bg-gray-50 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="payslipEditComponents-{{ $ps->id }}" data-next-index="{{ $components->count() }}">
                        @if($components->isEmpty())
                        <tr data-empty-row>
                            <td colspan="4" class="border-t border-gray-100 px-3 py-6 text-center text-[12px] text-gray-400">Belum ada komponen manual</td>
                        </tr>
                        @endif
                        @foreach($components as $index => $component)
                        @php
                            $isAutoComponent = !empty($component['is_auto']);
                            $isEditableAuto = $isAutoComponent && ($component['name'] ?? '') === 'Lembur';
                            $amountEditable = ! $isAutoComponent || $isEditableAuto;
                        @endphp
                        <tr data-component-row>
                            <td class="border-t border-gray-100 px-2 py-1.5">
                                <input type="hidden" name="components[{{ $index }}][id]" value="{{ $component['id'] ?? '' }}">
                                <input type="text" name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}" required @disabled($isAutoComponent) class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500">
                                <input type="hidden" name="components[{{ $index }}][category]" value="{{ $component['category'] ?? 'manual' }}">
                                <input type="hidden" name="components[{{ $index }}][is_taxable]" value="{{ !empty($component['is_taxable']) ? 1 : 0 }}">
                                <input type="hidden" name="components[{{ $index }}][is_auto]" value="{{ !empty($component['is_auto']) ? 1 : 0 }}">
                                <input type="hidden" name="components[{{ $index }}][detail]" value="{{ $component['detail'] ?? '' }}">
                                @if($isAutoComponent)
                                    <input type="hidden" name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}">
                                @endif
                            </td>
                            <td class="border-t border-gray-100 px-2 py-1.5">
                                <select name="components[{{ $index }}][type]" required @disabled($isAutoComponent) data-component-type class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500">
                                    <option value="earning" @selected(($component['type'] ?? '') === 'earning')>Earning</option>
                                    <option value="deduction" @selected(($component['type'] ?? '') === 'deduction')>Deduction</option>
                                    <option value="info" @selected(($component['type'] ?? '') === 'info')>Info</option>
                                </select>
                                @if($isAutoComponent)
                                    <input type="hidden" name="components[{{ $index }}][type]" value="{{ $component['type'] ?? 'info' }}">
                                @endif
                            </td>
                            <td class="border-t border-gray-100 px-2 py-1.5">
                                <input type="number" step="1" name="components[{{ $index }}][amount]" value="{{ $component['amount'] ?? 0 }}" required @disabled(! $amountEditable) data-component-amount class="h-8 w-full rounded-md border border-gray-300 px-2 text-right text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500">
                                @if(! $amountEditable)
                                    <input type="hidden" name="components[{{ $index }}][amount]" value="{{ $component['amount'] ?? 0 }}">
                                @endif
                            </td>
                            <td class="border-t border-gray-100 px-2 py-1.5 text-center">
                                @unless($isAutoComponent)
                                <button type="button" data-remove-component class="rounded-md p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 cursor-pointer" title="Hapus komponen">
                                    <span class="material-symbols-outlined text-[15px]">delete</span>
                                </button>
                                @elseif($isEditableAuto)
                                <span class="material-symbols-outlined text-[15px] text-indigo-400" title="Nominal bisa diedit, komponen tidak bisa dihapus">edit</span>
                                @else
                                <span class="material-symbols-outlined text-[15px] text-gray-300" title="Komponen otomatis terkunci">lock</span>
                                @endunless
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" onclick="addPayslipEditComponent({{ $ps->id }})" class="inline-flex h-[34px] items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 text-[11px] font-semibold text-indigo-700 transition hover:bg-indigo-100 cursor-pointer">
                <span class="material-symbols-outlined text-[14px]">add</span>
                Tambah Komponen
            </button>
        </div>
        <div class="sticky bottom-0 flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
            <button type="button" onclick="closePayslipEdit({{ $ps->id }})" class="inline-flex h-[38px] items-center rounded-lg border border-gray-300 bg-white px-4 text-[12px] font-semibold text-gray-700 transition hover:bg-gray-100 cursor-pointer">Batal</button>
            <button class="inline-flex h-[38px] items-center gap-1.5 rounded-lg bg-indigo-600 px-4 text-[12px] font-semibold text-white transition hover:bg-indigo-700 cursor-pointer">
                <span class="material-symbols-outlined text-[15px] shrink-0">save</span>
                Simpan
            </button>
        </div>
    </form>
</div>
@endforeach
@endif

<div id="payslipImportModal" class="hidden fixed inset-0 z-[90] items-center justify-center px-4 py-6">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" data-payslip-import-close></div>
    <form action="{{ route('admin.payslips.import') }}" method="POST" enctype="multipart/form-data" class="relative w-full max-w-[420px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl" data-payslip-import-form>
        @csrf
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <div>
                <h3 class="text-[15px] font-bold text-gray-900">Import Payslip</h3>
                <p class="mt-0.5 text-[11px] text-gray-500">Pilih periode dan file payroll.</p>
            </div>
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 cursor-pointer" data-payslip-import-close>
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="space-y-3 px-4 py-4">
            <div>
                <label class="mb-1.5 block text-[12px] font-semibold text-gray-700">Periode</label>
                <input type="month" name="period" value="{{ request('period', now()->format('Y-m')) }}" required class="h-[38px] w-full rounded-lg border border-gray-300 px-3 text-[13px] font-semibold text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="mb-1.5 block text-[12px] font-semibold text-gray-700">File Import</label>
                <label class="inline-flex h-[38px] min-w-0 w-full items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 text-[12px] font-semibold text-gray-700 transition hover:bg-gray-100 cursor-pointer" title="Format import: employee_code,basic_salary,Tunjangan Makan,Lembur,BPJS Kesehatan">
                    <span class="material-symbols-outlined text-[15px] shrink-0">upload_file</span>
                    <span class="truncate" data-payslip-import-file-name>Pilih CSV/XLSX</span>
                    <span class="sr-only">Format import: employee_code,basic_salary,Tunjangan Makan,Lembur,BPJS Kesehatan</span>
                    <input type="file" name="payslip_file" accept=".csv,.txt,.xlsx" required class="sr-only" onchange="this.form.querySelector('[data-payslip-import-file-name]').textContent = this.files[0]?.name || 'Pilih CSV/XLSX'">
                </label>
                <p class="mt-2 text-[11px] leading-4 text-gray-500">Bisa CSV/XLSX template atau report salary sheet PAYSLIP.</p>
            </div>
            <label class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                <input type="checkbox" name="replace_period" value="1" class="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                <span class="min-w-0">
                    <span class="block text-[12px] font-bold text-amber-900">Replace periode</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-amber-800">Hapus payslip lama pada periode ini untuk company Anda, lalu import ulang dari file.</span>
                </span>
            </label>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
            <button type="button" class="inline-flex h-[38px] items-center rounded-lg border border-gray-300 bg-white px-4 text-[12px] font-semibold text-gray-700 transition hover:bg-gray-100 cursor-pointer" data-payslip-import-close>Batal</button>
            <button class="inline-flex h-[38px] items-center gap-1.5 rounded-lg bg-indigo-600 px-4 text-[12px] font-semibold text-white transition hover:bg-indigo-700 cursor-pointer">
                <span class="material-symbols-outlined text-[15px] shrink-0">upload</span>
                Import
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0"></script>
<script>
const payslipSearch = document.getElementById('payslipSearch');
const payslipEmpty = document.getElementById('payslipFuseEmpty');
const payslipItems = Array.from(document.querySelectorAll('[data-fuse-row="payslip"]')).map((row, index) => ({
    index,
    row,
    text: row.dataset.search || '',
}));
const payslipFuse = window.Fuse ? new Fuse(payslipItems, {
    keys: ['text'],
    threshold: 0.45,
    ignoreLocation: true,
}) : null;

function applyPayslipSearch() {
    if (!payslipSearch) return;

    const keyword = payslipSearch.value.trim();
    const matched = keyword && payslipFuse
        ? new Set(payslipFuse.search(keyword).map((result) => result.item.index))
        : new Set(payslipItems.map((item) => item.index));
    let visibleCount = 0;

    payslipItems.forEach((item) => {
        const isVisible = matched.has(item.index);
        item.row.classList.toggle('hidden', !isVisible);
        if (isVisible) visibleCount++;
    });

    if (payslipEmpty) {
        payslipEmpty.classList.toggle('hidden', !keyword || visibleCount > 0);
    }
}

if (payslipSearch) {
    payslipSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    payslipSearch.addEventListener('input', applyPayslipSearch);
    applyPayslipSearch();
}

function openPayslipEdit(id) {
    const modal = document.getElementById(`payslipEditModal-${id}`);
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    refreshPayslipEditTotals(modal);

    const firstInput = modal.querySelector('input[name="basic_salary"]');
    if (firstInput) firstInput.focus();
}

function closePayslipEdit(id) {
    const modal = document.getElementById(`payslipEditModal-${id}`);
    if (!modal) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function closeAllPayslipEditModals() {
    document.querySelectorAll('[id^="payslipEditModal-"]').forEach((modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
}

function formatPayslipRupiah(value) {
    return `Rp ${Math.round(value || 0).toLocaleString('id-ID')}`;
}

function refreshPayslipEditTotals(target) {
    const modal = target.closest ? target.closest('[id^="payslipEditModal-"]') : target;
    if (!modal) return;

    const basicSalary = Number.parseFloat(modal.querySelector('[data-basic-salary]')?.value || '0') || 0;
    let totalEarning = basicSalary;
    let totalDeduction = 0;

    modal.querySelectorAll('[data-component-row]').forEach((row) => {
        const type = row.querySelector('[data-component-type]')?.value || '';
        const amount = Number.parseFloat(row.querySelector('[data-component-amount]')?.value || '0') || 0;

        if (type === 'earning') totalEarning += amount;
        if (type === 'deduction') totalDeduction += amount;
    });

    const earningEl = modal.querySelector('[data-summary-earning]');
    const deductionEl = modal.querySelector('[data-summary-deduction]');
    const netEl = modal.querySelector('[data-summary-net]');

    if (earningEl) earningEl.textContent = formatPayslipRupiah(totalEarning);
    if (deductionEl) deductionEl.textContent = formatPayslipRupiah(totalDeduction);
    if (netEl) netEl.textContent = formatPayslipRupiah(totalEarning - totalDeduction);
}

function addPayslipEditComponent(id) {
    const tbody = document.getElementById(`payslipEditComponents-${id}`);
    if (!tbody) return;

    tbody.querySelector('[data-empty-row]')?.remove();
    const index = Number.parseInt(tbody.dataset.nextIndex || '0', 10);
    tbody.dataset.nextIndex = String(index + 1);
    tbody.insertAdjacentHTML('beforeend', `
        <tr data-component-row>
            <td class="border-t border-gray-100 px-2 py-1.5">
                <input type="hidden" name="components[${index}][id]" value="">
                <input type="text" name="components[${index}][name]" required class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                <input type="hidden" name="components[${index}][category]" value="manual">
                <input type="hidden" name="components[${index}][is_taxable]" value="0">
                <input type="hidden" name="components[${index}][is_auto]" value="0">
                <input type="hidden" name="components[${index}][detail]" value="">
            </td>
            <td class="border-t border-gray-100 px-2 py-1.5">
                <select name="components[${index}][type]" required data-component-type class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="earning">Earning</option>
                    <option value="deduction">Deduction</option>
                    <option value="info">Info</option>
                </select>
            </td>
            <td class="border-t border-gray-100 px-2 py-1.5">
                <input type="number" step="1" name="components[${index}][amount]" value="0" required data-component-amount class="h-8 w-full rounded-md border border-gray-300 px-2 text-right text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </td>
            <td class="border-t border-gray-100 px-2 py-1.5 text-center">
                <button type="button" data-remove-component class="rounded-md p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600 cursor-pointer" title="Hapus komponen">
                    <span class="material-symbols-outlined text-[15px]">delete</span>
                </button>
            </td>
        </tr>
    `);
    refreshPayslipEditTotals(tbody);
}

document.querySelectorAll('[data-payslip-edit-close]').forEach((backdrop) => {
    backdrop.addEventListener('click', () => closePayslipEdit(backdrop.dataset.payslipEditClose));
});

document.querySelectorAll('[data-payslip-edit-form]').forEach((form) => {
    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-basic-salary], [data-component-amount]')) {
            refreshPayslipEditTotals(form);
        }
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-component-type]')) {
            refreshPayslipEditTotals(form);
        }
    });
    form.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-component]');
        if (!removeButton) return;

        const tbody = removeButton.closest('tbody');
        removeButton.closest('[data-component-row]')?.remove();

        if (tbody && !tbody.querySelector('[data-component-row]')) {
            tbody.insertAdjacentHTML('beforeend', '<tr data-empty-row><td colspan="4" class="border-t border-gray-100 px-3 py-6 text-center text-[12px] text-gray-400">Belum ada komponen manual</td></tr>');
        }

        refreshPayslipEditTotals(form);
    });
    refreshPayslipEditTotals(form);
});

const payslipImportModal = document.getElementById('payslipImportModal');
const payslipImportOpen = document.querySelector('[data-payslip-import-open]');
const payslipImportCloses = document.querySelectorAll('[data-payslip-import-close]');

function openPayslipImportModal() {
    if (!payslipImportModal) return;
    payslipImportModal.classList.remove('hidden');
    payslipImportModal.classList.add('flex');
}

function closePayslipImportModal() {
    if (!payslipImportModal) return;
    payslipImportModal.classList.add('hidden');
    payslipImportModal.classList.remove('flex');
}

if (payslipImportOpen) {
    payslipImportOpen.addEventListener('click', openPayslipImportModal);
}

payslipImportCloses.forEach((button) => {
    button.addEventListener('click', closePayslipImportModal);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closePayslipImportModal();
        closeAllPayslipEditModals();
    }
});
</script>
@endsection
