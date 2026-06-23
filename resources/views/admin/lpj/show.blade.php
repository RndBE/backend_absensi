@extends('admin.layouts.app')
@section('title', 'Detail LPJ')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManage = $adminPermission->can($currentAdmin, 'budget.manage');
@endphp

<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.lpj.index') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
    <div class="admin-lpj-actions flex flex-wrap justify-end gap-2">
        <a href="{{ route('admin.lpj.export-excel', $lpj->id) }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-sky-600 to-blue-600 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
            <span class="material-symbols-outlined text-[14px]">download</span> Export Excel
        </a>
        @if($canManage && in_array($lpj->status, ['pending', 'in_review']))
        <form action="{{ route('admin.approvals.approve', ['type' => 'lpj', 'id' => $lpj->id]) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
                <span class="material-symbols-outlined text-[14px]">check_circle</span> Setujui
            </button>
        </form>
        <button onclick="document.getElementById('modal-reject').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-red-600 to-red-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
            <span class="material-symbols-outlined text-[14px]">cancel</span> Tolak
        </button>
        @endif
        @if($canManage && $currentAdmin->role === 'superadmin')
        <form action="{{ route('admin.lpj.destroy', $lpj->id) }}" method="POST" onsubmit="return confirm('Yakin hapus LPJ ini?')">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-slate-600 to-slate-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
                <span class="material-symbols-outlined text-[14px]">delete</span> Hapus
            </button>
        </form>
        @endif
    </div>
</div>

{{-- Flash message --}}
@if(session('success'))
<div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-[13px] font-medium">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-[13px] font-medium">{{ session('error') }}</div>
@endif

{{-- Header Info --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="space-y-3">
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Status</div>
                @php
                    $statusBg = match($lpj->status) {
                        'approved'  => 'bg-emerald-100 text-emerald-700',
                        'in_review' => 'bg-blue-100 text-blue-700',
                        'rejected'  => 'bg-red-100 text-red-700',
                        'draft'     => 'bg-gray-100 text-gray-600',
                        default     => 'bg-amber-100 text-amber-700',
                    };
                    $statusLabel = match($lpj->status) {
                        'approved'  => 'Disetujui',
                        'in_review' => 'Diproses',
                        'rejected'  => 'Ditolak',
                        'draft'     => 'Draft',
                        default     => 'Pending',
                    };
                @endphp
                <span class="inline-flex px-2.5 py-1 rounded-full text-[12px] font-bold {{ $statusBg }}">{{ $statusLabel }}</span>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Nomor LPJ</div>
                <div class="text-[14px] font-semibold text-gray-800">{{ $lpj->nomor_lpj ?? '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Karyawan</div>
                <div class="flex items-center gap-2 mt-1">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-400 to-violet-500 flex items-center justify-center text-white text-[12px] font-bold shrink-0">{{ substr($lpj->employee->full_name ?? '', 0, 1) }}</div>
                    <div>
                        <div class="text-[13px] font-semibold text-gray-800">{{ $lpj->employee->full_name ?? '-' }}</div>
                        <div class="text-[11px] text-gray-400">{{ $lpj->employee->position ?? '-' }} · {{ $lpj->employee->department?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="space-y-3">
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Kegiatan / Anggaran</div>
                <div class="text-[13px] font-semibold text-gray-800">{{ $lpj->budgetRequest?->title ?? '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Nomor Surat Tugas</div>
                <div class="text-[13px] text-gray-700">{{ $lpj->budgetRequest?->surat_tugas_no ?? '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Tujuan</div>
                <div class="text-[13px] text-gray-700">{{ $lpj->travelReport?->destination_city ?? '-' }}</div>
            </div>
        </div>
        <div class="space-y-3">
            <div class="bg-indigo-50 rounded-lg p-4 text-center">
                <div class="text-[11px] font-semibold text-indigo-400 uppercase tracking-wider mb-1">Total Anggaran</div>
                <div class="text-[20px] font-black text-indigo-700">Rp {{ number_format($lpj->total_anggaran, 0, ',', '.') }}</div>
            </div>
            <div class="bg-emerald-50 rounded-lg p-4 text-center">
                <div class="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider mb-1">Total Realisasi</div>
                <div class="text-[20px] font-black text-emerald-700">Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}</div>
            </div>
            @php $sisa = (float) $lpj->sisa; @endphp
            <div class="{{ $sisa < 0 ? 'bg-red-50' : 'bg-gray-50' }} rounded-lg p-4 text-center">
                <div class="text-[11px] font-semibold {{ $sisa < 0 ? 'text-red-400' : 'text-gray-400' }} uppercase tracking-wider mb-1">
                    {{ $sisa < 0 ? 'Kelebihan' : 'Sisa' }}
                </div>
                <div class="text-[20px] font-black {{ $sisa < 0 ? 'text-red-700' : 'text-gray-700' }}">Rp {{ number_format(abs($sisa), 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    @if($lpj->catatan)
    <div class="mt-4 pt-4 border-t border-gray-100">
        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Catatan</div>
        <div class="text-[13px] text-gray-700">{{ $lpj->catatan }}</div>
    </div>
    @endif
    @if($lpj->rejection_reason && $lpj->status === 'rejected')
    <div class="mt-4 pt-4 border-t border-gray-100">
        <div class="text-[11px] font-semibold text-red-400 uppercase tracking-wider mb-1">Alasan Penolakan</div>
        <div class="text-[13px] text-red-700">{{ $lpj->rejection_reason }}</div>
    </div>
    @endif
</div>

{{-- Tabel Rincian Item (Excel-like) --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h4 class="text-[14px] font-bold text-gray-900">Rincian Realisasi</h4>
        <div class="text-[12px] text-gray-500">{{ $lpj->items->count() }} item</div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead>
                <tr class="bg-indigo-700 text-white text-[11px] font-bold uppercase tracking-wider">
                    <th class="py-3 px-3 text-center w-8">No</th>
                    <th class="py-3 px-3 text-left min-w-[200px]">Uraian</th>
                    <th class="py-3 px-3 text-center">Sat</th>
                    <th class="py-3 px-3 text-center">Vol</th>
                    <th class="py-3 px-3 text-right">Harga Satuan</th>
                    <th class="py-3 px-3 text-right">Anggaran</th>
                    <th class="py-3 px-3 text-right">Realisasi</th>
                    <th class="py-3 px-3 text-right">Selisih</th>
                    <th class="py-3 px-3 text-left min-w-[150px]">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lpj->items as $i => $item)
                @php $selisih = (float)$item->anggaran - (float)$item->realisasi; @endphp
                <tr class="border-b border-gray-100 hover:bg-gray-50/50 {{ $i % 2 === 0 ? '' : 'bg-gray-50/30' }}">
                    <td class="py-2.5 px-3 text-center text-gray-500">{{ $i + 1 }}</td>
                    <td class="py-2.5 px-3 font-medium text-gray-800">{{ $item->uraian }}</td>
                    <td class="py-2.5 px-3 text-center text-gray-600">{{ $item->satuan ?? '-' }}</td>
                    <td class="py-2.5 px-3 text-center text-gray-600">{{ number_format($item->volume, 0) }}</td>
                    <td class="py-2.5 px-3 text-right text-gray-700">{{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                    <td class="py-2.5 px-3 text-right text-gray-700 font-medium">{{ number_format($item->anggaran, 0, ',', '.') }}</td>
                    <td class="py-2.5 px-3 text-right text-gray-700 font-medium">{{ number_format($item->realisasi, 0, ',', '.') }}</td>
                    <td class="py-2.5 px-3 text-right font-bold {{ $selisih < 0 ? 'text-red-600 bg-red-50' : 'text-emerald-600 bg-emerald-50' }} rounded">
                        {{ $selisih < 0 ? '-' : '' }}{{ number_format(abs($selisih), 0, ',', '.') }}
                    </td>
                    <td class="admin-lpj-note-cell py-2.5 px-3 text-gray-500 text-[11px] align-top min-w-[180px] max-w-[260px]">
                        <div class="flex items-start justify-between gap-3">
                        <span class="block min-w-0 flex-1 break-words leading-relaxed text-gray-600">{{ $item->keterangan ?: '-' }}</span>
                        @if($item->bukti_file)
                        <a href="{{ asset('storage/' . $item->bukti_file) }}" target="_blank" class="order-last inline-flex shrink-0 items-center gap-0.5 text-indigo-600 hover:underline text-[11px] font-semibold">
                            <span class="material-symbols-outlined text-[13px]">attach_file</span> Bukti
                        </a>
                        @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-indigo-700 text-white font-bold">
                    <td class="py-3 px-3 text-center" colspan="5">TOTAL</td>
                    <td class="py-3 px-3 text-right">{{ number_format($lpj->total_anggaran, 0, ',', '.') }}</td>
                    <td class="py-3 px-3 text-right">{{ number_format($lpj->total_realisasi, 0, ',', '.') }}</td>
                    <td class="py-3 px-3 text-right {{ $sisa < 0 ? 'text-red-300' : 'text-emerald-300' }}">
                        {{ $sisa < 0 ? '-' : '' }}{{ number_format(abs($sisa), 0, ',', '.') }}
                    </td>
                    <td class="py-3 px-3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Approval Log --}}
@if($lpj->approvalLogs->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h4 class="text-[14px] font-bold text-gray-900 mb-4">Riwayat Persetujuan</h4>
    <div class="space-y-3">
        @foreach($lpj->approvalLogs as $log)
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br {{ $log->action === 'approved' ? 'from-emerald-400 to-emerald-500' : 'from-red-400 to-red-500' }} flex items-center justify-center text-white text-[11px] font-bold shrink-0">
                {{ substr($log->approver->full_name ?? '', 0, 1) }}
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-[13px] font-semibold text-gray-800">{{ $log->approver->full_name ?? '-' }}</span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $log->action === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $log->action === 'approved' ? 'Disetujui' : 'Ditolak' }}
                    </span>
                    <span class="text-[11px] text-gray-400">Step {{ $log->step_order }}</span>
                </div>
                @if($log->notes)
                <div class="text-[12px] text-gray-500 mt-0.5">{{ $log->notes }}</div>
                @endif
                <div class="text-[11px] text-gray-400 mt-0.5">{{ $log->created_at->format('d M Y, H:i') }}</div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Modal Tolak --}}
<div id="modal-reject" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-[16px] font-bold text-gray-900 mb-4">Tolak LPJ</h3>
        <form action="{{ route('admin.approvals.reject', ['type' => 'lpj', 'id' => $lpj->id]) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Alasan Penolakan</label>
                <textarea name="notes" rows="3" required
                    class="w-full px-3 py-2 text-[13px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-red-300 outline-none"
                    placeholder="Tuliskan alasan penolakan..."></textarea>
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" onclick="document.getElementById('modal-reject').classList.add('hidden')"
                    class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Batal</button>
                <button type="submit"
                    class="px-4 py-2 text-[12px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">Tolak LPJ</button>
            </div>
        </form>
    </div>
</div>
@endsection
