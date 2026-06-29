@extends('admin.layouts.app')
@section('title', 'Detail Pengajuan Anggaran')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManageBudget = $adminPermission->can($currentAdmin, 'budget.manage');

    $typeStyles = [
        'budget' => 'bg-blue-50 text-blue-700 border-blue-200',
        'reimbursement' => 'bg-purple-50 text-purple-700 border-purple-200',
    ];
    $statusColors = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'in_review' => 'bg-blue-50 text-blue-700 border-blue-200',
        'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-red-50 text-red-700 border-red-200',
        'paid' => 'bg-teal-50 text-teal-700 border-teal-200',
    ];
    $statusLabels = [
        'pending' => 'Pending',
        'in_review' => 'Diproses',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'paid' => 'Dibayar',
    ];
@endphp

<div class="space-y-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $typeStyles[$budgetRequest->type] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">
                        {{ $budgetRequest->type === 'budget' ? 'Budget / Uang Muka' : 'Reimbursement' }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusColors[$budgetRequest->status] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">
                        {{ $statusLabels[$budgetRequest->status] ?? $budgetRequest->status }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">
                        Step {{ $budgetRequest->current_step }}
                    </span>
                </div>
                <h2 class="text-[20px] leading-tight font-bold text-gray-900 break-words">{{ $budgetRequest->title }}</h2>
                <p class="mt-1 text-[12px] text-gray-500">
                    Diajukan {{ $budgetRequest->created_at->format('d M Y H:i') }}
                </p>
            </div>
            <a href="{{ route('admin.budget-requests.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Kembali
            </a>
        </div>

        <div class="p-5">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_280px] gap-5">
                <div class="min-w-0">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white text-[13px] font-bold shrink-0">
                            {{ substr($budgetRequest->employee->full_name ?? '?', 0, 1) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold text-gray-400 uppercase">Pemohon</div>
                            <div class="text-[14px] font-bold text-gray-900 break-words">{{ $budgetRequest->employee->full_name ?? '-' }}</div>
                            <div class="text-[12px] text-gray-500 break-words">
                                {{ $budgetRequest->employee->department->name ?? '-' }} - {{ $budgetRequest->employee->position ?? '-' }}
                            </div>
                        </div>
                    </div>

                    @if($budgetRequest->description)
                    <div class="mt-5 rounded-lg bg-gray-50 border border-gray-100 px-4 py-3">
                        <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Keterangan</div>
                        <p class="text-[13px] leading-relaxed text-gray-700 break-words">{{ $budgetRequest->description }}</p>
                    </div>
                    @endif

                    @if($budgetRequest->surat_tugas_no)
                    <div class="mt-3 rounded-lg bg-gray-50 border border-gray-100 px-4 py-3">
                        <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Surat Tugas</div>
                        <p class="text-[13px] text-gray-700 break-words">
                            {{ $budgetRequest->surat_tugas_no }} - {{ $budgetRequest->surat_tugas_date?->format('d M Y') }}
                        </p>
                    </div>
                    @endif
                </div>

                <div class="rounded-xl bg-gray-900 px-5 py-4 text-white">
                    <div class="text-[11px] font-bold uppercase text-gray-400">Total Pengajuan</div>
                    <div class="mt-2 text-[26px] leading-tight font-bold">Rp {{ number_format($budgetRequest->total_amount, 0, ',', '.') }}</div>
                    <div class="mt-3 text-[12px] text-gray-300">{{ $budgetRequest->items->count() }} item rincian</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-5 items-start">
        <div class="space-y-5 min-w-0">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <h3 class="text-[14px] font-bold text-gray-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[17px] text-indigo-500">receipt_long</span>
                        Rincian Item
                    </h3>
                    <span class="text-[12px] font-bold text-gray-900">Rp {{ number_format($budgetRequest->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($budgetRequest->items as $i => $item)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-[11px] font-bold shrink-0">{{ $i + 1 }}</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-white border border-gray-200 text-gray-700">{{ $item->type_label }}</span>
                                    </div>
                                    <p class="mt-2 text-[13px] leading-relaxed text-gray-700 break-words">{{ $item->description ?: '-' }}</p>
                                </div>
                                <div class="text-right text-[14px] font-bold text-gray-900 whitespace-nowrap">Rp {{ number_format($item->amount, 0, ',', '.') }}</div>
                            </div>

                            @if($item->attachments->isNotEmpty())
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="text-[10px] font-bold text-gray-400 uppercase mb-2">Lampiran Item</div>
                                <div class="space-y-1.5">
                                    @foreach($item->attachments as $att)
                                    <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="flex items-center gap-2 rounded-lg bg-white px-2.5 py-2 text-[12px] text-indigo-600 hover:bg-indigo-50 transition min-w-0">
                                        <span class="material-symbols-outlined text-[15px] shrink-0">description</span>
                                        <span class="truncate">{{ $att->file_name }}</span>
                                        <span class="ml-auto text-[10px] text-gray-400 shrink-0">{{ round($att->file_size / 1024) }} KB</span>
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($budgetRequest->participants->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[17px] text-blue-500">group</span>
                        Peserta Perjalanan
                    </h3>
                </div>
                <div class="p-5 flex flex-wrap gap-2">
                    @foreach($budgetRequest->participants as $p)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-full text-[12px] font-medium text-gray-700">
                        <span class="w-5 h-5 rounded-full bg-indigo-400 text-white text-[9px] font-bold flex items-center justify-center">{{ substr($p->full_name, 0, 1) }}</span>
                        {{ $p->full_name }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            @if($budgetRequest->attachments->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[17px] text-gray-500">attach_file</span>
                        Lampiran Utama
                    </h3>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($budgetRequest->attachments as $att)
                    <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-100 rounded-lg hover:bg-gray-100 transition-all min-w-0">
                        <span class="material-symbols-outlined text-[18px] text-gray-500 shrink-0">description</span>
                        <span class="text-[13px] text-gray-700 font-medium truncate">{{ $att->file_name }}</span>
                        <span class="text-[11px] text-gray-400 ml-auto shrink-0">{{ round($att->file_size / 1024) }} KB</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="space-y-5 min-w-0">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-[14px] font-bold text-gray-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[17px] text-emerald-500">timeline</span>
                        Riwayat Approval
                    </h3>
                </div>
                <div class="p-5">
                    @if($budgetRequest->approvalLogs->isEmpty())
                        <p class="text-[13px] text-gray-400">Belum ada riwayat approval.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($budgetRequest->approvalLogs->sortBy('created_at') as $log)
                            <div class="pl-3 border-l-2 {{ $log->action === 'approved' ? 'border-emerald-400' : 'border-red-400' }}">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[13px] font-semibold text-gray-800">{{ $log->approver->full_name ?? 'Unknown' }}</span>
                                    @if($log->via_label)<span class="text-[11px] text-gray-500">(via {{ $log->via_label }})</span>@endif
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold {{ $log->action === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $log->action === 'approved' ? 'Disetujui' : 'Ditolak' }}
                                    </span>
                                    <span class="text-[11px] text-gray-400">Step {{ $log->step_order }}</span>
                                </div>
                                @if($log->notes)
                                <p class="text-[12px] leading-relaxed text-gray-500 mt-1 break-words">{{ $log->notes }}</p>
                                @endif
                                <div class="text-[11px] text-gray-400 mt-1">{{ $log->created_at->format('d M Y H:i') }}</div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <h3 class="text-[14px] font-bold text-gray-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[17px] text-teal-600">payments</span>
                        Pembayaran
                    </h3>
                    @if(in_array($budgetRequest->status, ['approved', 'paid']) && $canManageBudget)
                    <button onclick="document.getElementById('paymentModal').classList.remove('hidden')"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-all cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">add</span>
                        Proses
                    </button>
                    @endif
                </div>
                <div class="p-5">
                    @if($budgetRequest->payments && $budgetRequest->payments->count())
                        <div class="space-y-3">
                            @foreach($budgetRequest->payments as $payment)
                            <div class="rounded-xl border border-teal-100 bg-teal-50/60 p-4">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[14px] font-bold text-gray-900">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-teal-100 text-teal-700">{{ strtoupper($payment->status) }}</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-white text-gray-600 border border-gray-200">{{ $payment->method_label }}</span>
                                </div>
                                @if($payment->reference_no)
                                <div class="text-[12px] text-gray-600 mt-1 break-words">Ref: {{ $payment->reference_no }}</div>
                                @endif
                                @if($payment->notes)
                                <div class="text-[12px] text-gray-600 mt-1 break-words">{{ $payment->notes }}</div>
                                @endif
                                <div class="text-[11px] text-gray-400 mt-1">
                                    {{ $payment->paid_at?->format('d M Y H:i') }} oleh {{ $payment->processor->full_name ?? '-' }}
                                </div>
                                @if($payment->payment_proof)
                                <a href="{{ asset('storage/' . $payment->payment_proof) }}" target="_blank" class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-indigo-600 hover:underline">
                                    <span class="material-symbols-outlined text-[14px]">description</span>
                                    Bukti Pembayaran
                                </a>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-[13px] text-gray-400">Belum ada pembayaran.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if(in_array($budgetRequest->status, ['approved', 'paid']) && $canManageBudget)
<div id="paymentModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900">Proses Pembayaran</h3>
            <button onclick="document.getElementById('paymentModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.budget-payments.store', $budgetRequest->id) }}" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Jumlah (Rp) *</label>
                <input type="number" name="amount" required min="1" value="{{ $budgetRequest->total_amount }}" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Metode Bayar *</label>
                <select name="payment_method" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
                    <option value="transfer">Transfer Bank</option>
                    <option value="cash">Tunai</option>
                    <option value="check">Cek / Giro</option>
                </select>
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">No. Referensi</label>
                <input type="text" name="reference_no" placeholder="Nomor transfer / cek" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Bukti Bayar</label>
                <input type="file" name="payment_proof" accept="image/*,.pdf" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px]">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Catatan</label>
                <textarea name="notes" rows="2" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400"></textarea>
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-all cursor-pointer">Konfirmasi Pembayaran</button>
        </form>
    </div>
</div>
@endif
@endsection
