@extends('employee.layouts.app')
@section('title', 'Detail Anggaran')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.budget-requests.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Anggaran
        </a>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h1 class="text-[22px] font-black text-gray-900">{{ $budgetRequest->title }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">{{ $budgetRequest->type === 'budget' ? 'Budget' : 'Reimbursement' }} · Rp {{ number_format((float) $budgetRequest->total_amount, 0, ',', '.') }}</p>
            </div>
            @include('employee.partials.status-badge', ['status' => $budgetRequest->status])
        </div>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Deskripsi</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $budgetRequest->description ?: '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Surat Tugas</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $budgetRequest->surat_tugas_no ?: '-' }} @if($budgetRequest->surat_tugas_date) · {{ $budgetRequest->surat_tugas_date->format('d/m/Y') }} @endif</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Jarak / Zona</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $budgetRequest->distance_km ? $budgetRequest->distance_km.' km' : '-' }} @if($budgetRequest->travelZone) · {{ $budgetRequest->travelZone->name }} @endif</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Peserta</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $budgetRequest->participants->pluck('full_name')->join(', ') ?: '-' }}</div>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Rincian Biaya</div>
        <div class="divide-y divide-gray-100">
            @foreach($budgetRequest->items as $item)
                <div class="p-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <div class="text-[13px] font-black text-gray-900">{{ $item->type_label }}</div>
                        <div class="text-[12px] text-gray-500 mt-1">{{ $item->description ?: '-' }}</div>
                        @if($item->attachments->isNotEmpty())
                            <div class="mt-2 text-[12px] text-indigo-600">{{ $item->attachments->count() }} lampiran item</div>
                        @endif
                    </div>
                    <div class="text-[14px] font-black text-gray-900">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</div>
                </div>
            @endforeach
        </div>
    </section>

    @if($budgetRequest->approvalLogs->isNotEmpty())
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-[15px] font-black text-gray-900">Riwayat Approval</h2>
            <div class="mt-3 space-y-2">
                @foreach($budgetRequest->approvalLogs as $log)
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-[13px] text-gray-700">
                        <span class="font-bold">{{ $log->approver?->full_name ?? 'Approver' }}</span> {{ $log->action }} step {{ $log->step_order }}
                        @if($log->via_label)<span class="text-gray-500"> (via {{ $log->via_label }})</span>@endif
                        @if($log->notes)<span class="text-gray-500"> · {{ $log->notes }}</span>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
