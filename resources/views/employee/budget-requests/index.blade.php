@extends('employee.layouts.app')
@section('title', 'Anggaran')

@section('content')
<div class="space-y-4">
    <div class="employee-mobile-page-header flex items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Anggaran</h1>
            <p class="text-[13px] text-gray-500 mt-1">Budget dan reimbursement perjalanan/operasional.</p>
        </div>
        <a href="{{ route('employee.budget-requests.create') }}" class="employee-mobile-action inline-flex h-10 shrink-0 items-center justify-center gap-2 px-4 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[17px]">add</span>
            Ajukan
        </a>
    </div>

    @php
        $periodMonths = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $periodYears = range($period->copy()->subYears(2)->year, now()->addYear()->year);
    @endphp

    <form method="GET" class="employee-period-filter-card bg-white rounded-xl border border-gray-200 shadow-sm p-3.5 sm:p-4 flex items-end gap-2">
        <div class="employee-period-input min-w-0 flex-1">
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Periode</span>
            <div class="grid grid-cols-[minmax(0,1fr)_84px] gap-2 sm:grid-cols-[150px_92px]">
                <label class="relative min-w-0">
                    <select name="period_month" class="employee-period-select h-10 w-full appearance-none rounded-lg border border-gray-200 bg-white px-3 pr-8 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        @foreach($periodMonths as $monthNumber => $monthLabel)
                            <option value="{{ str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT) }}" @selected((int) $period->month === $monthNumber)>{{ $monthLabel }}</option>
                        @endforeach
                    </select>
                    <span class="material-symbols-outlined pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[16px] text-gray-400">expand_more</span>
                </label>
                <label class="relative">
                    <select name="period_year" class="employee-period-select h-10 w-full appearance-none rounded-lg border border-gray-200 bg-white px-3 pr-7 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        @foreach($periodYears as $year)
                            <option value="{{ $year }}" @selected((int) $period->year === (int) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    <span class="material-symbols-outlined pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[16px] text-gray-400">expand_more</span>
                </label>
            </div>
        </div>
        <button type="submit" aria-label="Filter periode" title="Filter" class="employee-filter-submit inline-flex h-10 w-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-gray-900 text-[12px] font-bold text-white shadow-sm sm:w-auto sm:px-4">
            <span class="material-symbols-outlined text-[16px]">filter_alt</span>
            <span class="hidden sm:inline">Filter</span>
        </button>
    </form>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Riwayat Anggaran</div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Judul</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Tipe</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Nominal</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $budgetRequest)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3.5 border-b border-gray-100 min-w-[220px]">
                                <div class="text-[13px] font-bold text-gray-900">{{ $budgetRequest->title }}</div>
                                <div class="text-[12px] text-gray-500 mt-0.5">{{ $budgetRequest->description ?: '-' }}</div>
                                @if(in_array($budgetRequest->status, ['approved', 'paid']) && $budgetRequest->return_date && ! ($budgetRequest->has_lhp ?? false))
                                    @php
                                        $lhpDeadline = $budgetRequest->lhpDeadlineDate();
                                        $lhpLate = $lhpDeadline && \Illuminate\Support\Carbon::today()->gt($lhpDeadline);
                                    @endphp
                                    <a href="{{ route('employee.travel-reports.create', ['budget_request_id' => $budgetRequest->id]) }}"
                                       class="mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-bold {{ $lhpLate ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700' }} hover:opacity-80">
                                        <span class="material-symbols-outlined text-[12px]">assignment_late</span>
                                        {{ $lhpLate ? 'LHP terlambat' : 'Buat LHP' }}@if($lhpDeadline) · batas {{ $lhpDeadline->format('d/m') }}@endif
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100 whitespace-nowrap">{{ $budgetRequest->type === 'budget' ? 'Budget' : 'Reimbursement' }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-bold text-gray-900 border-b border-gray-100 whitespace-nowrap">Rp {{ number_format((float) $budgetRequest->total_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100 whitespace-nowrap">@include('employee.partials.status-badge', ['status' => $budgetRequest->status])</td>
                            <td class="px-4 py-3.5 text-right border-b border-gray-100 whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <a href="{{ route('employee.budget-requests.show', $budgetRequest->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        Detail
                                    </a>
                                    @if($budgetRequest->status === 'pending')
                                        <a href="{{ route('employee.budget-requests.edit', $budgetRequest->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-gray-900 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-gray-800">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                            Edit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-10 text-[13px] text-gray-400">Belum ada pengajuan anggaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $requests->links() }}</div>
        @endif
    </section>
</div>
@endsection
