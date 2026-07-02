@extends('admin.layouts.app')
@section('title', 'Detail Payroll — ' . \Carbon\Carbon::parse($run->period . '-01')->translatedFormat('F Y'))

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canUpdatePayrollRun = $adminPermission->can($currentAdmin, 'payroll.runs.update');
    $canPublishPayrollRun = $adminPermission->can($currentAdmin, 'payroll.runs.publish');
@endphp
<div class="mb-4">
    <a href="{{ route('admin.payroll-runs.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke Daftar
    </a>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Periode</div>
        <div class="text-[18px] font-bold text-gray-900">{{ \Carbon\Carbon::parse($run->period . '-01')->translatedFormat('F Y') }}</div>
        <div class="text-[12px] text-gray-500 mt-0.5">{{ $details->count() }} karyawan</div>
    </div>
    <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Total Earning</div>
        <div class="text-[18px] font-bold text-emerald-700">Rp {{ number_format($run->total_earning, 0, ',', '.') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-red-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-red-600 uppercase tracking-wider mb-1">Total Deduction</div>
        <div class="text-[18px] font-bold text-red-600">Rp {{ number_format($run->total_deduction, 0, ',', '.') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-indigo-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider mb-1">Net Salary</div>
        <div class="text-[18px] font-bold text-indigo-700">Rp {{ number_format($run->total_net, 0, ',', '.') }}</div>
        <div class="mt-1">
            @if($run->status === 'locked')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-gray-700 text-white">🔒 Locked {{ $run->locked_at?->format('d/m/Y') }}</span>
            @elseif($run->status === 'published')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700">📢 Published {{ $run->published_at?->format('d/m/Y') }}</span>
            @elseif($run->status === 'finalized')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700">✅ Finalized {{ $run->finalized_at?->format('d/m/Y') }}</span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700">📝 Draft</span>
            @endif
        </div>
    </div>
</div>

{{-- Action Buttons --}}
<div class="flex flex-wrap gap-2 mb-5">
    @if($run->status === 'draft' && $canPublishPayrollRun)
    <form action="{{ route('admin.payroll-runs.finalize', $run->id) }}" method="POST" data-confirm="Finalize payroll ini?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">check_circle</span> Finalize
        </button>
    </form>
    @endif
    @if($run->status === 'draft' && $canUpdatePayrollRun)
    <form action="{{ route('admin.payroll-runs.regenerate', $run->id) }}" method="POST" data-confirm="Regenerate semua data?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">refresh</span> Regenerate
        </button>
    </form>
    @endif

    @if($run->status === 'finalized' && $canPublishPayrollRun)
    <form action="{{ route('admin.payroll-runs.publish', $run->id) }}" method="POST" data-confirm="Publish payslip ke karyawan?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-blue-600 to-blue-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">publish</span> Publish Payslip
        </button>
    </form>
    <form action="{{ route('admin.payroll-runs.lock', $run->id) }}" method="POST" data-confirm="Lock payroll? Data tidak bisa diubah.">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-gray-700 to-gray-600 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">lock</span> Lock Payroll
        </button>
    </form>
    <form action="{{ route('admin.payroll-runs.reopen', $run->id) }}" method="POST" data-confirm="Reopen ke draft?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">undo</span> Reopen
        </button>
    </form>
    @endif

    @if($run->status === 'published' && $canPublishPayrollRun)
    <form action="{{ route('admin.payroll-runs.unpublish', $run->id) }}" method="POST" data-confirm="Unpublish payslip?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">unpublished</span> Unpublish
        </button>
    </form>
    <form action="{{ route('admin.payroll-runs.lock', $run->id) }}" method="POST" data-confirm="Lock payroll?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-gray-700 to-gray-600 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">lock</span> Lock
        </button>
    </form>
    @endif

    @if($run->status === 'locked' && $canPublishPayrollRun)
    <form action="{{ route('admin.payroll-runs.unlock', $run->id) }}" method="POST" data-confirm="Unlock payroll?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">lock_open</span> Unlock
        </button>
    </form>
    @endif

    {{-- Download semua payslip jadi satu PDF (tersedia setelah finalized) --}}
    @if(in_array($run->status, ['finalized', 'published', 'locked']))
    <button type="button" onclick="openPayslipDownloadModal()" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span> Download Semua Payslip (PDF)
    </button>
    @endif

    {{-- Inject BPJS: tersedia di semua status untuk fix data lama --}}
    @php
        $hasBpjs = $details->contains(function($d) {
            $comps = is_array($d->components) ? $d->components : json_decode($d->components, true) ?? [];
            return collect($comps)->contains(fn($c) => str_contains($c['name'] ?? '', 'BPJS'));
        });
    @endphp
    @if((!$hasBpjs || $run->status === 'draft') && $canUpdatePayrollRun)
    <form action="{{ route('admin.payroll-runs.inject-bpjs', $run->id) }}" method="POST" data-confirm="Inject komponen BPJS ke semua karyawan dalam payroll ini?">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-blue-600 to-cyan-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">health_and_safety</span> Inject BPJS
        </button>
    </form>
    @endif
</div>

{{-- Modal: pilih urutan download semua payslip --}}
@if(in_array($run->status, ['finalized', 'published', 'locked']))
<style>
    @keyframes payslipModalIn {
        from { opacity: 0; transform: translateY(8px) scale(0.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    #payslipDownloadModal:not(.hidden) .payslip-modal-box {
        animation: payslipModalIn 0.18s ease-out;
    }
</style>
<div id="payslipDownloadModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/45 backdrop-blur-sm px-4 py-5 overflow-y-auto">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 payslip-modal-box">
        {{-- Header dengan aksen gradient --}}
        <div class="relative flex items-center gap-3.5 rounded-t-2xl bg-gradient-to-br from-red-600 to-rose-500 px-5 py-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm ring-1 ring-white/30">
                <span class="material-symbols-outlined text-[24px] text-white">picture_as_pdf</span>
            </div>
            <div class="min-w-0">
                <h3 class="text-[15px] font-bold text-white">Download Semua Payslip</h3>
                <p class="mt-0.5 text-[11.5px] text-white/80">{{ $details->count() }} karyawan · {{ \Carbon\Carbon::parse($run->period . '-01')->translatedFormat('F Y') }}</p>
            </div>
            <button type="button" onclick="closePayslipDownloadModal()" class="ml-auto rounded-lg p-1 text-white/80 hover:bg-white/20 hover:text-white transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <form method="GET" action="{{ route('admin.payslips.download-run', $run->id) }}">
            <div class="p-5">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Urutkan berdasarkan</label>
                <select name="sort" class="w-full text-[13px]">
                    <option value="name">Abjad Nama (A–Z)</option>
                    <option value="join_date">Tanggal Masuk (paling lama bergabung)</option>
                </select>
                <p class="mt-2.5 flex items-start gap-1.5 text-[11px] text-gray-400">
                    <span class="material-symbols-outlined text-[14px] mt-px">info</span>
                    Semua payslip digabung jadi satu file PDF sesuai urutan yang dipilih.
                </p>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gray-100 bg-gray-50/60 px-5 py-3.5">
                <button type="button" onclick="closePayslipDownloadModal()" class="rounded-lg px-4 py-2 text-[12.5px] font-semibold text-gray-600 hover:bg-gray-200/70 transition-colors cursor-pointer">Batal</button>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-br from-red-600 to-rose-500 px-5 py-2 text-[12.5px] font-semibold text-white shadow-sm shadow-red-500/30 hover:-translate-y-0.5 hover:shadow-md transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">download</span> Download PDF
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Detail Table --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">receipt_long</span> Detail per Karyawan</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Gaji Pokok</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Komponen</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Earning</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Deduction</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Net Salary</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($details as $detail)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3.5 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($detail->employee->full_name ?? '?', 0, 1) }}</div>
                            <div>
                                <div class="text-[13px] font-semibold text-gray-800">{{ $detail->employee->full_name ?? '-' }}</div>
                                <div class="text-[11px] text-gray-400">{{ $detail->employee->employee_code ?? '' }} · {{ $detail->employee->department->name ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] text-gray-700">Rp {{ number_format($detail->basic_salary, 0, ',', '.') }}</td>
                    <td class="px-4 py-3.5 border-b border-gray-100">
                        @if($detail->components && count($detail->components) > 0)
                        <div class="space-y-0.5">
                            @foreach($detail->components as $comp)
                            <div class="flex items-center gap-1.5 text-[11px]">
                                @if($comp['type'] === 'earning')
                                    <span class="text-emerald-600">+</span>
                                    <span class="text-gray-700">{{ $comp['name'] }}</span>
                                    <span class="font-semibold text-emerald-600 ml-auto">Rp {{ number_format($comp['amount'], 0, ',', '.') }}</span>
                                @elseif($comp['type'] === 'info')
                                    <span class="text-blue-500">ℹ</span>
                                    <span class="text-gray-500">{{ $comp['name'] }}</span>
                                    @if($comp['amount'] > 0)
                                        <span class="font-semibold text-blue-500 ml-auto">Rp {{ number_format($comp['amount'], 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-gray-400 ml-auto">{{ $comp['detail'] ?? '' }}</span>
                                    @endif
                                @else
                                    <span class="text-red-600">−</span>
                                    <span class="text-gray-700">{{ $comp['name'] }}</span>
                                    <span class="font-semibold text-red-600 ml-auto">Rp {{ number_format($comp['amount'], 0, ',', '.') }}</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        @else
                            <span class="text-[11px] text-gray-400">Tidak ada komponen</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-emerald-700">Rp {{ number_format($detail->total_earning, 0, ',', '.') }}</td>
                    <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-red-600">Rp {{ number_format($detail->total_deduction, 0, ',', '.') }}</td>
                    <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[14px] font-bold text-gray-900">Rp {{ number_format($detail->net_salary, 0, ',', '.') }}</td>
                    <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                        <div class="flex items-center justify-center gap-1">
                            @if($run->status === 'draft' && $canUpdatePayrollRun)
                            <button type="button" onclick="openPayrollDetailEdit({{ $detail->id }})" class="p-1 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors cursor-pointer" title="Edit Detail"><span class="material-symbols-outlined text-[14px]">edit</span></button>
                            @endif
                            @if(in_array($run->status, ['finalized', 'published', 'locked']))
                            <a href="{{ route('admin.payslips.show', ['id' => $detail->id, 'from_run' => $run->id]) }}" class="p-1 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors" title="Lihat Payslip"><span class="material-symbols-outlined text-[14px]">receipt</span></a>
                            <a href="{{ route('admin.payslips.download', $detail->id) }}" class="p-1 rounded-lg hover:bg-emerald-50 text-gray-400 hover:text-emerald-600 transition-colors" title="Download PDF"><span class="material-symbols-outlined text-[14px]">download</span></a>
                            @endif
                            @if($detail->is_manual_edited)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-600" title="Edited">✏️</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Tidak ada data karyawan</td></tr>
                @endforelse
            </tbody>
            @if($details->count() > 0)
            <tfoot>
                <tr class="bg-gray-50">
                    <td colspan="3" class="px-4 py-3.5 text-right text-[12px] font-bold text-gray-600 uppercase">Total</td>
                    <td class="px-4 py-3.5 text-right text-[14px] font-bold text-emerald-700">Rp {{ number_format($details->sum('total_earning'), 0, ',', '.') }}</td>
                    <td class="px-4 py-3.5 text-right text-[14px] font-bold text-red-600">Rp {{ number_format($details->sum('total_deduction'), 0, ',', '.') }}</td>
                    <td class="px-4 py-3.5 text-right text-[14px] font-bold text-indigo-700">Rp {{ number_format($details->sum('net_salary'), 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@if($run->status === 'draft' && $canUpdatePayrollRun)
@foreach($details as $detail)
@php
    $components = collect($detail->components ?? [])->values();
@endphp
<div id="editPayrollDetail-{{ $detail->id }}" class="hidden fixed inset-0 z-50 bg-slate-900/45 backdrop-blur-sm px-4 py-5 overflow-y-auto">
    <div class="mx-auto w-full max-w-2xl rounded-xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-4 py-3">
            <div>
                <h3 class="text-[14px] font-bold text-gray-900">Edit Detail Payroll</h3>
                <p class="mt-0.5 text-[11px] text-gray-500">{{ $detail->employee->full_name ?? '-' }} · {{ \Carbon\Carbon::parse($run->period . '-01')->translatedFormat('F Y') }}</p>
            </div>
            <button type="button" onclick="closePayrollDetailEdit({{ $detail->id }})" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[17px]">close</span>
            </button>
        </div>
        <form action="{{ route('admin.payroll-runs.update-detail', [$run->id, $detail->id]) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="p-4 pb-32">
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-4 text-amber-800">
                    Perubahan manual akan menghitung ulang total earning, deduction, dan net salary karyawan ini.
                </div>
                <div class="overflow-x-auto overflow-y-visible rounded-lg border border-gray-200 pb-32">
                    <table class="w-full min-w-[600px]">
                        <thead>
                            <tr>
                                <th class="bg-gray-50 px-2 py-1.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500">Nama Komponen</th>
                                <th class="bg-gray-50 px-2 py-1.5 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500">Tipe</th>
                                <th class="bg-gray-50 px-2 py-1.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-500">Nominal</th>
                                <th class="bg-gray-50 px-2 py-1.5 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="payrollDetailComponents-{{ $detail->id }}" data-next-index="{{ $components->count() }}">
                            @foreach($components as $index => $component)
                            @php
                                $isAutoComponent = !empty($component['is_auto']);
                                // Lembur (auto) boleh diedit NOMINAL-nya; komponen auto lain tetap terkunci.
                                $isEditableAuto = $isAutoComponent && ($component['name'] ?? '') === 'Lembur';
                                $amountEditable = ! $isAutoComponent || $isEditableAuto;
                            @endphp
                            <tr data-component-row>
                                <td class="border-t border-gray-100 px-2 py-1">
                                    <input type="text" name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}" required @disabled($isAutoComponent) class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500">
                                    <input type="hidden" name="components[{{ $index }}][category]" value="{{ $component['category'] ?? 'recurring' }}">
                                    <input type="hidden" name="components[{{ $index }}][is_taxable]" value="{{ !empty($component['is_taxable']) ? 1 : 0 }}">
                                    <input type="hidden" name="components[{{ $index }}][is_auto]" value="{{ !empty($component['is_auto']) ? 1 : 0 }}">
                                    <input type="hidden" name="components[{{ $index }}][detail]" value="{{ $component['detail'] ?? '' }}">
                                    @if($isAutoComponent)
                                        <input type="hidden" name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}">
                                    @endif
                                </td>
                                <td class="border-t border-gray-100 px-2 py-1">
                                    <select name="components[{{ $index }}][type]" required @disabled($isAutoComponent) class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500">
                                        <option value="earning" @selected(($component['type'] ?? '') === 'earning')>Earning</option>
                                        <option value="deduction" @selected(($component['type'] ?? '') === 'deduction')>Deduction</option>
                                        <option value="info" @selected(($component['type'] ?? '') === 'info')>Info</option>
                                    </select>
                                    @if($isAutoComponent)
                                        <input type="hidden" name="components[{{ $index }}][type]" value="{{ $component['type'] ?? 'info' }}">
                                    @endif
                                </td>
                                <td class="border-t border-gray-100 px-2 py-1">
                                    <input type="number" min="0" step="1" name="components[{{ $index }}][amount]" value="{{ $component['amount'] ?? 0 }}" required @disabled(! $amountEditable) class="h-8 w-full rounded-md border border-gray-300 px-2 text-right text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500">
                                    @if(! $amountEditable)
                                        <input type="hidden" name="components[{{ $index }}][amount]" value="{{ $component['amount'] ?? 0 }}">
                                    @endif
                                </td>
                                <td class="border-t border-gray-100 px-2 py-1 text-center">
                                    @unless($isAutoComponent)
                                    <button type="button" onclick="this.closest('[data-component-row]').remove()" class="rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined text-[15px]">delete</span>
                                    </button>
                                    @elseif($isEditableAuto)
                                        <span class="material-symbols-outlined text-[15px] text-indigo-400" title="Nominal bisa diedit, tidak bisa dihapus">edit</span>
                                    @else
                                        <span class="material-symbols-outlined text-[15px] text-gray-300" title="Tidak bisa diubah">lock</span>
                                    @endunless
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" onclick="addPayrollDetailComponent({{ $detail->id }})" class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[14px]">add</span> Tambah Komponen
                </button>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 px-4 py-3">
                <button type="button" onclick="closePayrollDetailEdit({{ $detail->id }})" class="rounded-md bg-gray-100 px-3 py-1.5 text-[11px] font-semibold text-gray-700 hover:bg-gray-200 transition-colors cursor-pointer">Batal</button>
                <button type="submit" class="rounded-md bg-gradient-to-br from-indigo-600 to-indigo-500 px-3 py-1.5 text-[11px] font-semibold text-white shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endif

{{-- Activity Log --}}
@if($run->logs && $run->logs->count() > 0)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">history</span> Riwayat Aksi</h3>
    </div>
    <div class="p-5">
        <div class="relative pl-6 space-y-4">
            <div class="absolute left-[9px] top-1 bottom-1 w-0.5 bg-gray-200"></div>
            @foreach($run->logs as $log)
            <div class="relative flex gap-3">
                <div class="absolute -left-6 top-0.5 w-[18px] h-[18px] rounded-full border-2 border-white shadow-sm
                    @if(in_array($log->action, ['created', 'finalized', 'published'])) bg-emerald-400
                    @elseif($log->action === 'locked') bg-gray-600
                    @elseif(in_array($log->action, ['unlocked', 'reopened', 'unpublished'])) bg-amber-400
                    @elseif($log->action === 'regenerated') bg-blue-400
                    @else bg-gray-300
                    @endif
                    "></div>
                <div>
                    <div class="text-[13px] font-semibold text-gray-800">{{ ucfirst($log->action) }}</div>
                    <div class="text-[11px] text-gray-500">{{ $log->performer->full_name ?? 'System' }} · {{ $log->created_at->format('d/m/Y H:i') }}</div>
                    @if($log->notes)
                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $log->notes }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
function openPayslipDownloadModal() {
    const modal = document.getElementById('payslipDownloadModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closePayslipDownloadModal() {
    const modal = document.getElementById('payslipDownloadModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Tutup modal saat klik area gelap di luar kotak
document.getElementById('payslipDownloadModal')?.addEventListener('click', function (e) {
    if (e.target === this) closePayslipDownloadModal();
});

function openPayrollDetailEdit(id) {
    document.getElementById('editPayrollDetail-' + id)?.classList.remove('hidden');
}

function closePayrollDetailEdit(id) {
    document.getElementById('editPayrollDetail-' + id)?.classList.add('hidden');
}

function addPayrollDetailComponent(id) {
    const tbody = document.getElementById('payrollDetailComponents-' + id);
    if (!tbody) return;

    const index = Number(tbody.dataset.nextIndex || 0);
    tbody.dataset.nextIndex = String(index + 1);
    tbody.insertAdjacentHTML('beforeend', `
        <tr data-component-row>
            <td class="border-t border-gray-100 px-2 py-1">
                <input type="text" name="components[${index}][name]" required class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                <input type="hidden" name="components[${index}][category]" value="recurring">
                <input type="hidden" name="components[${index}][is_taxable]" value="0">
                <input type="hidden" name="components[${index}][is_auto]" value="0">
                <input type="hidden" name="components[${index}][detail]" value="">
            </td>
            <td class="border-t border-gray-100 px-2 py-1">
                <select name="components[${index}][type]" required class="h-8 w-full rounded-md border border-gray-300 px-2 text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="earning">Earning</option>
                    <option value="deduction">Deduction</option>
                    <option value="info">Info</option>
                </select>
            </td>
            <td class="border-t border-gray-100 px-2 py-1">
                <input type="number" min="0" step="1" name="components[${index}][amount]" value="0" required class="h-8 w-full rounded-md border border-gray-300 px-2 text-right text-[11px] focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </td>
            <td class="border-t border-gray-100 px-2 py-1 text-center">
                <button type="button" onclick="this.closest('[data-component-row]').remove()" class="rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[15px]">delete</span>
                </button>
            </td>
        </tr>
    `);
}
</script>
@endpush
@endsection
