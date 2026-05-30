@extends('admin.layouts.app')
@section('title', 'Detail LHP')

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.travel-reports.index') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
    <div class="flex gap-2">
        @if(in_array($report->status, ['pending', 'in_review']))
        <a href="{{ route('admin.travel-reports.edit', $report->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-amber-500 to-amber-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
            <span class="material-symbols-outlined text-[14px]">edit</span> Edit LHP
        </a>
        @endif
        <a href="{{ route('admin.travel-reports.print', $report) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">🖨️ Cetak LHP</a>
    </div>
</div>

{{-- Header Card --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="space-y-3">
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Status</div>
                @php
                    $statusBg = match($report->status) {
                        'approved' => 'bg-emerald-100 text-emerald-700',
                        'in_review' => 'bg-blue-100 text-blue-700',
                        'rejected' => 'bg-red-100 text-red-700',
                        default => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10.5px] font-bold {{ $statusBg }}">{{ strtoupper($report->status) }}</span>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Pembuat LHP</div>
                <div class="text-[13px] font-medium text-gray-800">{{ $report->employee->full_name ?? '-' }}</div>
                <div class="text-[11px] text-gray-400">{{ $report->employee->department->name ?? '-' }} · {{ $report->employee->position ?? '-' }}</div>
            </div>
        </div>
        <div class="space-y-3">
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Kota Tujuan</div>
                <div class="text-[13.5px] font-semibold text-gray-800">{{ $report->destination_city }}</div>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Tanggal</div>
                <div class="text-[13px] text-gray-800">{{ $report->departure_date->format('d M Y') }} — {{ $report->return_date->format('d M Y') }}</div>
                <div class="text-[11px] text-gray-400">{{ $report->duration_days }} hari</div>
            </div>
        </div>
        <div class="space-y-3">
            @if($report->surat_tugas_no)
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Surat Tugas</div>
                <div class="text-[13px] font-medium text-gray-800">{{ $report->surat_tugas_no }}</div>
                @if($report->surat_tugas_date)
                    <div class="text-[11px] text-gray-400">{{ $report->surat_tugas_date->format('d M Y') }}</div>
                @endif
            </div>
            @endif
            @if($report->budgetRequest)
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Request Terkait</div>
                <a href="{{ route('admin.budget-requests.show', $report->budget_request_id) }}" class="text-[13px] text-indigo-600 hover:underline">{{ $report->budgetRequest->title }}</a>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Maksud & Tujuan --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-3"><span class="material-symbols-outlined text-[16px] text-indigo-500">flag</span> Maksud dan Tujuan</h3>
    <p class="text-[13px] text-gray-700 leading-relaxed">{{ $report->purpose }}</p>
</div>

{{-- Kegiatan --}}
@foreach($report->activities as $activity)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
    <div class="px-5 py-3 bg-gradient-to-r from-indigo-50 to-indigo-100/50 border-b border-gray-200 flex items-center gap-3">
        <span class="w-7 h-7 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[11px] font-bold shrink-0">{{ $loop->iteration }}</span>
        <div>
            <div class="text-[13px] font-bold text-gray-800">{{ $activity->activity_date->format('d F Y') }}</div>
            <div class="text-[11px] text-gray-400">Kegiatan {{ $loop->iteration }}</div>
        </div>
    </div>
    <div class="p-5 space-y-4">
        <div>
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">📋 Pelaksanaan</div>
            <p class="text-[13px] text-gray-700">{{ $activity->description }}</p>
        </div>

        @if($activity->results && count($activity->results))
        <div>
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">✅ Hasil</div>
            <ol class="list-decimal list-inside space-y-0.5">
                @foreach($activity->results as $result)
                    <li class="text-[13px] text-gray-700">{{ $result }}</li>
                @endforeach
            </ol>
        </div>
        @endif

        @if($activity->issues)
        <div>
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">⚠️ Permasalahan</div>
            <p class="text-[13px] text-gray-700">{{ $activity->issues }}</p>
        </div>
        @endif

        @if($activity->conclusion)
        <div>
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">📝 Kesimpulan</div>
            <p class="text-[13px] text-gray-700">{{ $activity->conclusion }}</p>
        </div>
        @endif

        @if($activity->documents->isNotEmpty())
        <div>
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">📸 Dokumentasi</div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($activity->documents as $doc)
                <div class="border border-gray-200 rounded-lg overflow-hidden group">
                    @if(in_array(pathinfo($doc->file_path, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png']))
                        <img src="{{ asset('storage/' . $doc->file_path) }}" alt="{{ $doc->caption }}" class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-200">
                    @else
                        <div class="w-full h-32 bg-gray-50 flex items-center justify-center">
                            <span class="text-[11px] text-gray-400">📄 {{ basename($doc->file_path) }}</span>
                        </div>
                    @endif
                    @if($doc->caption)
                        <div class="p-2"><p class="text-[11px] text-gray-600">{{ $doc->caption }}</p></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endforeach

{{-- Kesimpulan & Rekomendasi --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-3"><span class="material-symbols-outlined text-[16px] text-indigo-500">description</span> Kesimpulan & Rekomendasi</h3>
    <div class="space-y-3">
        <div>
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Kesimpulan</div>
            <p class="text-[13px] text-gray-700">{{ $report->conclusion }}</p>
        </div>
        @if($report->recommendations && count($report->recommendations))
        <div>
            <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Rekomendasi Tindak Lanjut</div>
            <ol class="list-decimal list-inside space-y-0.5">
                @foreach($report->recommendations as $rec)
                    <li class="text-[13px] text-gray-700">{{ $rec }}</li>
                @endforeach
            </ol>
        </div>
        @endif
    </div>
</div>

{{-- Approval Logs --}}
@if($report->approvalLogs->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-3"><span class="material-symbols-outlined text-[16px] text-indigo-500">approval</span> Riwayat Approval</h3>
    <div class="space-y-2">
        @foreach($report->approvalLogs as $log)
        <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
            <div class="w-7 h-7 rounded-full {{ $log->action === 'approved' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' }} flex items-center justify-center text-[11px] font-bold shrink-0">
                {{ $log->action === 'approved' ? '✓' : '✗' }}
            </div>
            <div class="flex-1 min-w-0">
                <span class="text-[13px] font-medium text-gray-800">{{ $log->action === 'approved' ? 'Disetujui' : 'Ditolak' }} oleh {{ $log->approver->full_name ?? '-' }}</span>
                <div class="text-[11px] text-gray-400">{{ $log->created_at->format('d M Y, H:i') }}</div>
                @if($log->notes)
                    <div class="text-[11px] text-gray-500 italic mt-0.5">"{{ $log->notes }}"</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection
