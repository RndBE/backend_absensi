@extends('admin.layouts.app')
@section('title', 'Pengajuan Anggaran')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManageBudget = $adminPermission->can($currentAdmin, 'budget.manage');
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">request_quote</span> Pengajuan Anggaran</h3>
        <div class="flex items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / judul..." class="px-3 py-1.5 text-[12px] border border-gray-200 rounded-lg focus:ring-1 focus:ring-indigo-300 focus:border-indigo-300 w-40">
                <input type="month" name="month" value="{{ request('month') }}" class="px-3 py-1.5 text-[12px] border border-gray-200 rounded-lg focus:ring-1 focus:ring-indigo-300">
                <button type="submit" class="px-3 py-1.5 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer">Filter</button>
                @if(request('search') || request('month') || request('status'))
                <a href="{{ route('admin.budget-requests.index') }}" class="px-3 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">Reset</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Status Tabs --}}
    <div class="px-5 pt-3">
        <div class="flex gap-0 border-b-2 border-gray-200 mb-4">
            @foreach(['all' => 'Semua', 'pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'paid' => 'Dibayar'] as $key => $label)
                <a href="{{ route('admin.budget-requests.index', array_merge(request()->except('status'), ['status' => $key])) }}"
                   class="px-4 py-2 text-[13px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                          {{ (request('status', 'all') === $key) ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="p-5 pt-0">
        @if($requests->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-3"><span class="material-symbols-outlined text-[36px]">request_quote</span></div>
            <p class="text-sm font-medium mb-1">Belum ada pengajuan anggaran</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Judul</th>
                        <th class="py-3 px-3">Tipe</th>
                        <th class="py-3 px-3 text-right">Total</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Step</th>
                        <th class="py-3 px-3">Tanggal</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($requests as $req)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-all">
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-teal-400 to-teal-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($req->employee->full_name ?? '', 0, 1) }}</div>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-900">{{ $req->employee->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $req->employee->department->name ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3">
                            <div class="text-[13px] font-medium text-gray-800 max-w-[200px] truncate">{{ $req->title }}</div>
                            <div class="text-[11px] text-gray-400">{{ $req->items->count() }} item</div>
                        </td>
                        <td class="py-3 px-3">
                            @php
                                $typeStyles = [
                                    'budget' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'reimbursement' => 'bg-purple-50 text-purple-700 border-purple-200',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $typeStyles[$req->type] ?? '' }}">
                                {{ $req->type === 'budget' ? 'Budget' : 'Reimburse' }}
                            </span>
                        </td>
                        <td class="py-3 px-3 text-right">
                            <span class="text-[13px] font-semibold text-gray-900">Rp {{ number_format($req->total_amount, 0, ',', '.') }}</span>
                        </td>
                        <td class="py-3 px-3">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'in_review' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                    'paid' => 'bg-teal-50 text-teal-700 border-teal-200',
                                ];
                                $statusLabels = ['pending' => 'Pending', 'in_review' => 'Diproses', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'paid' => 'Dibayar'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusColors[$req->status] ?? '' }}">
                                {{ $statusLabels[$req->status] ?? $req->status }}
                            </span>
                        </td>
                        <td class="py-3 px-3 text-[12px] text-gray-500">
                            Step {{ $req->current_step ?? 1 }}
                        </td>
                        <td class="py-3 px-3 text-[12px] text-gray-500">
                            {{ $req->created_at->format('d M Y') }}
                        </td>
                        <td class="py-3 px-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.budget-requests.show', $req->id) }}" class="inline-flex items-center px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">👁️ Detail</a>
                                @if(in_array($req->status, ['pending', 'rejected']) && $canManageBudget)
                                <form action="{{ route('admin.budget-requests.destroy', $req->id) }}" method="POST" data-confirm="Hapus pengajuan ini?">
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
            {{ $requests->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
