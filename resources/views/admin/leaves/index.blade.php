@extends('admin.layouts.app')
@section('title', 'Pengajuan Cuti')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">event_busy</span> Pengajuan Cuti</h3>
        <a href="{{ route('admin.leaves.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">＋ Ajukan Cuti</a>
    </div>

    {{-- Status Tabs --}}
    <div class="px-5 pt-3">
        <div class="flex gap-0 border-b-2 border-gray-200 mb-4">
            @foreach(['all' => 'Semua', 'pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
                <a href="{{ route('admin.leaves.index', ['status' => $key]) }}"
                   class="px-4 py-2 text-[13px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                          {{ $status === $key ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="p-5 pt-0">
        @if($leaves->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">event_busy</span></div>
            <p class="text-sm font-medium mb-1">Belum ada pengajuan cuti</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Jenis Cuti</th>
                        <th class="py-3 px-3">Tanggal</th>
                        <th class="py-3 px-3 text-center">Hari</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Step</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($leaves as $leave)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-all">
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($leave->employee->full_name ?? '', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-900">{{ $leave->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $leave->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3 text-[13px] text-gray-700">{{ $leave->leaveType->name ?? '-' }}</td>
                        <td class="py-3 px-3">
                            <div class="text-[12px] text-gray-700">{{ $leave->start_date->format('d M Y') }}</div>
                            <div class="text-[11px] text-gray-400">s/d {{ $leave->end_date->format('d M Y') }}</div>
                        </td>
                        <td class="py-3 px-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-600">{{ (int)$leave->total_days }} hari</span>
                        </td>
                        <td class="py-3 px-3">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'in_review' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                ];
                                $statusLabels = ['pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusColors[$leave->status] ?? '' }}">
                                {{ $statusLabels[$leave->status] ?? $leave->status }}
                            </span>
                        </td>
                        <td class="py-3 px-3 text-[12px] text-gray-500">
                            Step {{ $leave->current_step ?? 1 }}
                        </td>
                        <td class="py-3 px-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.leaves.show', $leave->id) }}" class="inline-flex items-center px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">👁️ Detail</a>
                                @if($leave->status === 'pending')
                                <form action="{{ route('admin.leaves.destroy', $leave->id) }}" method="POST" onsubmit="return confirm('Hapus pengajuan ini?')">
                                    @csrf @method('DELETE')
                                    <button class="inline-flex items-center px-2 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $leaves->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
