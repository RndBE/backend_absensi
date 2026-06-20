@extends('admin.layouts.app')
@section('title', 'Payslip Karyawan')

@section('content')
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
                                <a href="{{ route('admin.payslips.show', $ps->id) }}" class="p-1.5 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors" title="Detail"><span class="material-symbols-outlined text-[16px]">visibility</span></a>
                                <a href="{{ route('admin.payslips.download', $ps->id) }}" class="p-1.5 rounded-lg hover:bg-emerald-50 text-gray-400 hover:text-emerald-600 transition-colors" title="Download PDF"><span class="material-symbols-outlined text-[16px]">download</span></a>
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
    if (event.key === 'Escape') closePayslipImportModal();
});
</script>
@endsection
