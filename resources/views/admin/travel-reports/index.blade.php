@extends('admin.layouts.app')
@section('title', 'Laporan Hasil Perjalanan')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManageTravelReports = $adminPermission->can($currentAdmin, 'travel.reports.manage');
    $hasFilter = request()->anyFilled(['employee_id', 'date_from', 'date_to', 'surat_tugas_no']);
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">flight_takeoff</span> Laporan Hasil Perjalanan (LHP)</h3>
        @if($canManageTravelReports)
        <a href="{{ route('admin.travel-reports.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">＋ Buat LHP</a>
        @endif
    </div>

    {{-- Status Tabs --}}
    <div class="px-5 pt-3">
        <div class="flex gap-0 border-b-2 border-gray-200 mb-4">
            @foreach(['all' => 'Semua', 'pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
                <a href="{{ route('admin.travel-reports.index', array_merge(request()->except(['status', 'page']), ['status' => $key])) }}"
                   class="px-4 py-2 text-[13px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                          {{ $status === $key ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Filter --}}
    <div class="px-5 pb-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            {{-- Lebar diatur pembungkus: searchable-select selalu melebar penuh mengikuti induknya. --}}
            <div class="w-[220px] shrink-0">
                <select name="employee_id" class="w-full text-[13px]">
                    <option value="">Semua karyawan</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <input type="date" name="date_from" value="{{ request('date_from') }}" title="Dari tanggal"
                       class="h-10 px-3 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none">
                <span class="text-[12px] text-gray-400">s/d</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" title="Sampai tanggal"
                       class="h-10 px-3 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none">
            </div>
            {{-- Kolom nomor dan tombol dibungkus bersama supaya tombol tidak turun sendirian saat layar sempit. --}}
            <div class="flex flex-1 min-w-[300px] items-center gap-2">
                <input type="text" name="surat_tugas_no" value="{{ request('surat_tugas_no') }}" placeholder="No. surat tugas..."
                       class="flex-1 min-w-0 h-10 px-3 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none">
                <button type="submit" class="h-10 shrink-0 px-4 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 cursor-pointer">Cari</button>
                @if($hasFilter)
                <a href="{{ route('admin.travel-reports.index', ['status' => $status]) }}" class="h-10 shrink-0 inline-flex items-center px-4 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="p-5 pt-0">
        @if($reports->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">flight_takeoff</span></div>
            <p class="text-sm font-medium mb-1">{{ $hasFilter ? 'Tidak ada LHP yang cocok dengan filter' : 'Belum ada LHP' }}</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Kota Tujuan</th>
                        <th class="py-3 px-3">No. Surat Tugas</th>
                        <th class="py-3 px-3">Tanggal</th>
                        <th class="py-3 px-3 text-center">Durasi</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($reports as $report)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-all">
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-teal-400 to-teal-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($report->employee->full_name ?? '', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">{{ $report->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $report->employee->department->name ?? '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3 text-[13px] font-medium text-gray-800">{{ $report->destination_city }}</td>
                        <td class="py-3 px-3 text-[12px] text-gray-700 whitespace-nowrap">
                            @if($report->surat_tugas_no)
                                {{ $report->surat_tugas_no }}
                            @else
                                <span class="text-[11px] text-gray-400 italic">Belum diisi</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-[12px] text-gray-500 whitespace-nowrap">
                            {{ $report->departure_date->format('d M') }} — {{ $report->return_date->format('d M Y') }}
                        </td>
                        <td class="py-3 px-3 text-center text-[12px] text-gray-500">{{ $report->duration_days }} hari</td>
                        <td class="py-3 px-3">
                            @php
                                $statusBg = match($report->status) {
                                    'approved' => 'bg-emerald-100 text-emerald-700',
                                    'in_review' => 'bg-blue-100 text-blue-700',
                                    'rejected' => 'bg-red-100 text-red-700',
                                    default => 'bg-amber-100 text-amber-700',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $statusBg }}">{{ strtoupper($report->status) }}</span>
                            {{-- Pengajuan ulang = baris LHP baru; badge menautkan pasangan LHP lama dan penggantinya. --}}
                            @if($report->resubmission_of_id)
                                <div class="mt-1">
                                    <a href="{{ route('admin.travel-reports.show', $report->resubmission_of_id) }}" title="Pengganti LHP yang ditolak. Klik untuk membuka LHP lama."
                                       class="inline-flex items-center gap-0.5 whitespace-nowrap px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-all">
                                        PENGAJUAN ULANG <span class="material-symbols-outlined text-[12px]">history</span>
                                    </a>
                                </div>
                            @elseif($report->status === 'rejected' && $report->resubmission)
                                @php
                                    $replacementStatus = ['pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$report->resubmission->status] ?? $report->resubmission->status;
                                @endphp
                                <div class="mt-1">
                                    <a href="{{ route('admin.travel-reports.show', $report->resubmission->id) }}" title="LHP pengganti berstatus {{ $replacementStatus }}. Klik untuk membukanya."
                                       class="inline-flex items-center gap-0.5 whitespace-nowrap px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all">
                                        DIAJUKAN ULANG <span class="material-symbols-outlined text-[12px]">arrow_outward</span>
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.travel-reports.show', $report) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-all">Detail</a>
                                <a href="{{ route('admin.travel-reports.print', $report) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">🖨️</a>
                                @if($canManageTravelReports)
                                <form method="POST" action="{{ route('admin.travel-reports.destroy', $report) }}" class="inline" data-confirm="Yakin ingin menghapus LHP ini?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all cursor-pointer">Hapus</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="mt-4">{{ $reports->withQueryString()->links() }}</div>
        @endif
        @endif
    </div>
</div>
@endsection
