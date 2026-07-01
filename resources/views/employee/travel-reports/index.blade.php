@extends('employee.layouts.app')
@section('title', 'LHP')

@section('content')
<div class="space-y-4">
    <div class="employee-mobile-page-header flex items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">LHP</h1>
            <p class="text-[13px] text-gray-500 mt-1">Laporan hasil perjalanan dinas.</p>
        </div>
        <a href="{{ route('employee.travel-reports.create') }}" class="employee-mobile-action inline-flex h-10 shrink-0 items-center justify-center gap-2 px-4 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[17px]">add</span>
            Buat LHP
        </a>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Riwayat LHP</div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tujuan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Budget</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3.5 border-b border-gray-100 min-w-[220px]">
                                <div class="text-[13px] font-bold text-gray-900">{{ $report->destination_city }}</div>
                                <div class="text-[12px] text-gray-500 mt-0.5">{{ $report->purpose }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100 whitespace-nowrap">{{ $report->departure_date?->format('d/m/Y') }} - {{ $report->return_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100 whitespace-nowrap">{{ $report->budgetRequest?->title ?? '-' }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100 whitespace-nowrap">
                                @include('employee.partials.status-badge', ['status' => $report->status])
                                @if($report->is_late)
                                    <span class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-red-50 px-2 py-0.5 text-[10.5px] font-bold text-red-600" title="Dikumpulkan melewati batas">
                                        <span class="material-symbols-outlined text-[12px]">schedule</span> Terlambat
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right border-b border-gray-100 whitespace-nowrap">
                                <a href="{{ route('employee.travel-reports.show', $report->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-10 text-[13px] text-gray-400">Belum ada LHP.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $reports->links() }}</div>
        @endif
    </section>
</div>
@endsection
