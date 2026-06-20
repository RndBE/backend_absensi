@extends('employee.layouts.app')
@section('title', 'Anggaran')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Anggaran</h1>
            <p class="text-[13px] text-gray-500 mt-1">Budget dan reimbursement perjalanan/operasional.</p>
        </div>
        <a href="{{ route('employee.budget-requests.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[17px]">add</span>
            Ajukan
        </a>
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex flex-col sm:flex-row gap-2 sm:items-end">
        <label class="block">
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Periode</span>
            <input type="month" name="period" value="{{ $period->format('Y-m') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
        </label>
        <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-[12px] font-bold text-white">
            <span class="material-symbols-outlined text-[16px]">filter_alt</span>
            Filter
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
                            </td>
                            <td class="px-4 py-3.5 text-[13px] border-b border-gray-100 whitespace-nowrap">{{ $budgetRequest->type === 'budget' ? 'Budget' : 'Reimbursement' }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-bold text-gray-900 border-b border-gray-100 whitespace-nowrap">Rp {{ number_format((float) $budgetRequest->total_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100 whitespace-nowrap">@include('employee.partials.status-badge', ['status' => $budgetRequest->status])</td>
                            <td class="px-4 py-3.5 text-right border-b border-gray-100 whitespace-nowrap">
                                <a href="{{ route('employee.budget-requests.show', $budgetRequest->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-10 text-[13px] text-gray-400">Belum ada pengajuan anggaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
