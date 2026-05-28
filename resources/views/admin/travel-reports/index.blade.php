@extends('admin.layouts.app')
@section('title', 'Laporan Hasil Perjalanan')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManageTravelReports = $adminPermission->can($currentAdmin, 'travel.reports.manage');
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
                <a href="{{ route('admin.travel-reports.index', ['status' => $key]) }}"
                   class="px-4 py-2 text-[13px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                          {{ $status === $key ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="p-5 pt-0">
        @if($reports->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">flight_takeoff</span></div>
            <p class="text-sm font-medium mb-1">Belum ada LHP</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Kota Tujuan</th>
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
