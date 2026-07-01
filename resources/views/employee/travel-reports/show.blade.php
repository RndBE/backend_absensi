@extends('employee.layouts.app')
@section('title', 'Detail LHP')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.travel-reports.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            LHP
        </a>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h1 class="text-[22px] font-black text-gray-900">{{ $report->destination_city }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">{{ $report->departure_date?->format('d/m/Y') }} - {{ $report->return_date?->format('d/m/Y') }} · {{ $report->duration_days }} hari</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if(in_array($report->status, ['pending', 'in_review'], true))
                    <a href="{{ route('employee.travel-reports.edit', $report->id) }}" class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-3 py-2 text-[12px] font-bold text-amber-700 hover:bg-amber-100">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                        Edit
                    </a>
                @endif
                @if($report->is_late)
                    <span class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1 text-[12px] font-bold text-red-600" title="LHP dikumpulkan melewati batas">
                        <span class="material-symbols-outlined text-[15px]">schedule</span> Terlambat
                    </span>
                @endif
                @include('employee.partials.status-badge', ['status' => $report->status])
            </div>
        </div>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Budget Terkait</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $report->budgetRequest?->title ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Surat Tugas</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $report->surat_tugas_no ?: '-' }} @if($report->surat_tugas_date) · {{ $report->surat_tugas_date->format('d/m/Y') }} @endif</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Tujuan Perjalanan</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $report->purpose }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Jarak / Zona</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $report->distance_km ? $report->distance_km.' km' : '-' }} @if($report->travelZone) · {{ $report->travelZone->name }} @endif</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Dibuat</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $report->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Terakhir Diedit</div>
            <div class="mt-1 text-[13px] text-gray-700">{{ $report->updated_at?->format('d/m/Y H:i') ?? '-' }}</div>
        </div>
        @if($report->submission_deadline)
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Batas Pengumpulan LHP</div>
            <div class="mt-1 text-[13px] {{ $report->is_late ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                {{ $report->submission_deadline->format('d/m/Y') }}
                @if($report->is_late) · Terlambat @endif
            </div>
        </div>
        @endif
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-[15px] font-black text-gray-900">Kesimpulan</h2>
        <p class="mt-2 text-[13px] text-gray-700">{{ $report->conclusion }}</p>
        @if($report->recommendations)
            <div class="mt-4">
                <div class="text-[11px] font-bold uppercase text-gray-400">Rekomendasi</div>
                <ul class="mt-2 space-y-1 text-[13px] text-gray-700">
                    @foreach($report->recommendations as $recommendation)
                        <li>• {{ $recommendation }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 text-[15px] font-bold text-gray-900">Aktivitas</div>
        <div class="divide-y divide-gray-100">
            @foreach($report->activities as $activity)
                <div class="p-5">
                    <div class="text-[12px] font-bold text-gray-400">{{ $activity->activity_date?->format('d/m/Y') }}</div>
                    <div class="mt-1 text-[14px] font-black text-gray-900">{{ $activity->description }}</div>
                    @if($activity->results)
                        <div class="mt-3 text-[12px] font-bold uppercase text-gray-400">Hasil</div>
                        <ul class="mt-1 space-y-1 text-[13px] text-gray-700">
                            @foreach($activity->results as $result)
                                <li>• {{ $result }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if($activity->issues)
                        <div class="mt-3 text-[12px] font-bold uppercase text-gray-400">Kendala</div>
                        <div class="mt-1 text-[13px] text-gray-700">{{ $activity->issues }}</div>
                    @endif
                    @if($activity->conclusion)
                        <div class="mt-3 text-[12px] font-bold uppercase text-gray-400">Kesimpulan Aktivitas</div>
                        <div class="mt-1 text-[13px] text-gray-700">{{ $activity->conclusion }}</div>
                    @endif
                    @if($activity->documents->isNotEmpty())
                        <div class="mt-3 text-[12px] text-indigo-600">{{ $activity->documents->count() }} dokumen aktivitas</div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    @if($report->approvalLogs->isNotEmpty())
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-[15px] font-black text-gray-900">Riwayat Approval</h2>
            <div class="mt-3 space-y-2">
                @foreach($report->approvalLogs as $log)
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
