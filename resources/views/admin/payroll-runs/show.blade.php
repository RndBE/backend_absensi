@extends('admin.layouts.app')
@section('title', 'Detail Payroll — ' . \Carbon\Carbon::parse($run->period . '-01')->translatedFormat('F Y'))

@section('content')
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
        <div class="text-[12px] text-gray-500 mt-0.5">{{ $run->payrollGroup->name ?? 'Semua Group' }}</div>
    </div>
    <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-4">
        <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Total Earning</div>
        <div class="text-[18px] font-bold text-emerald-700">Rp {{ number_format($run->total_earning, 0, ',', '.') }}</div>
        <div class="text-[12px] text-gray-500 mt-0.5">{{ $details->count() }} karyawan</div>
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
    @if($run->status === 'draft')
    <form action="{{ route('admin.payroll-runs.finalize', $run->id) }}" method="POST" onsubmit="return confirm('Finalize payroll ini?')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">check_circle</span> Finalize
        </button>
    </form>
    <form action="{{ route('admin.payroll-runs.regenerate', $run->id) }}" method="POST" onsubmit="return confirm('Regenerate semua data?')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">refresh</span> Regenerate
        </button>
    </form>
    @endif

    @if($run->status === 'finalized')
    <form action="{{ route('admin.payroll-runs.publish', $run->id) }}" method="POST" onsubmit="return confirm('Publish payslip ke karyawan?')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-blue-600 to-blue-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">publish</span> Publish Payslip
        </button>
    </form>
    <form action="{{ route('admin.payroll-runs.lock', $run->id) }}" method="POST" onsubmit="return confirm('Lock payroll? Data tidak bisa diubah.')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-gray-700 to-gray-600 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">lock</span> Lock Payroll
        </button>
    </form>
    <form action="{{ route('admin.payroll-runs.reopen', $run->id) }}" method="POST" onsubmit="return confirm('Reopen ke draft?')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">undo</span> Reopen
        </button>
    </form>
    @endif

    @if($run->status === 'published')
    <form action="{{ route('admin.payroll-runs.unpublish', $run->id) }}" method="POST" onsubmit="return confirm('Unpublish payslip?')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">unpublished</span> Unpublish
        </button>
    </form>
    <form action="{{ route('admin.payroll-runs.lock', $run->id) }}" method="POST" onsubmit="return confirm('Lock payroll?')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-gray-700 to-gray-600 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">lock</span> Lock
        </button>
    </form>
    @endif

    @if($run->status === 'locked')
    <form action="{{ route('admin.payroll-runs.unlock', $run->id) }}" method="POST" onsubmit="return confirm('Unlock payroll?')">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">lock_open</span> Unlock
        </button>
    </form>
    @endif
</div>

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
                            @if(in_array($run->status, ['published', 'locked']))
                            <a href="{{ route('admin.payslips.show', $detail->id) }}" class="p-1 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors" title="Lihat Payslip"><span class="material-symbols-outlined text-[14px]">receipt</span></a>
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
@endsection
