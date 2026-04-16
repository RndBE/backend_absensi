@extends('admin.layouts.app')
@section('title', 'Detail Pengajuan Anggaran')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.budget-requests.index') }}" class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">
            <span class="material-symbols-outlined text-[16px] mr-1">arrow_back</span> Kembali
        </a>
        <h2 class="text-lg font-bold text-gray-900">{{ $budgetRequest->title }}</h2>
    </div>

    {{-- Info Card --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase">Karyawan</div>
                <div class="text-[13px] font-semibold text-gray-900 mt-1">{{ $budgetRequest->employee->full_name ?? '-' }}</div>
                <div class="text-[11px] text-gray-500">{{ $budgetRequest->employee->department->name ?? '-' }} · {{ $budgetRequest->employee->position ?? '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase">Tipe</div>
                @php
                    $typeStyles = [
                        'budget' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'reimbursement' => 'bg-purple-50 text-purple-700 border-purple-200',
                    ];
                @endphp
                <span class="inline-flex items-center mt-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $typeStyles[$budgetRequest->type] ?? '' }}">
                    {{ $budgetRequest->type === 'budget' ? 'Budget / Uang Muka' : 'Reimbursement' }}
                </span>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase">Status</div>
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
                <span class="inline-flex items-center mt-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusColors[$budgetRequest->status] ?? '' }}">
                    {{ $statusLabels[$budgetRequest->status] ?? $budgetRequest->status }}
                </span>
                <div class="text-[11px] text-gray-400 mt-0.5">Step {{ $budgetRequest->current_step }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase">Total</div>
                <div class="text-[16px] font-bold text-gray-900 mt-1">Rp {{ number_format($budgetRequest->total_amount, 0, ',', '.') }}</div>
            </div>
        </div>

        @if($budgetRequest->description)
        <div class="mt-3 pt-3 border-t border-gray-100">
            <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Keterangan</div>
            <p class="text-[13px] text-gray-700">{{ $budgetRequest->description }}</p>
        </div>
        @endif

        @if($budgetRequest->surat_tugas_no)
        <div class="mt-3 pt-3 border-t border-gray-100">
            <div class="text-[11px] font-bold text-gray-400 uppercase mb-1">Surat Tugas</div>
            <p class="text-[13px] text-gray-700">{{ $budgetRequest->surat_tugas_no }} — {{ $budgetRequest->surat_tugas_date?->format('d M Y') }}</p>
        </div>
        @endif

        <div class="mt-3 pt-3 border-t border-gray-100 text-[11px] text-gray-400">
            Diajukan: {{ $budgetRequest->created_at->format('d M Y H:i') }}
        </div>
    </div>

    {{-- Items --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[14px] font-bold text-gray-900"><span class="material-symbols-outlined text-[16px] align-text-bottom">list_alt</span> Rincian Item</h3>
        </div>
        <div class="p-5 pt-0">
            <table class="w-full mt-3">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-2 px-3">#</th>
                        <th class="py-2 px-3">Tipe</th>
                        <th class="py-2 px-3">Deskripsi</th>
                        <th class="py-2 px-3 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($budgetRequest->items as $i => $item)
                    <tr class="border-b border-gray-50">
                        <td class="py-2.5 px-3 text-[12px] text-gray-400">{{ $i + 1 }}</td>
                        <td class="py-2.5 px-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-700">{{ $item->type_label }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-[13px] text-gray-700">{{ $item->description ?: '-' }}</td>
                        <td class="py-2.5 px-3 text-right text-[13px] font-semibold text-gray-900">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200">
                        <td colspan="3" class="py-3 px-3 text-right text-[12px] font-bold text-gray-500 uppercase">Total</td>
                        <td class="py-3 px-3 text-right text-[15px] font-bold text-gray-900">Rp {{ number_format($budgetRequest->total_amount, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Participants --}}
    @if($budgetRequest->participants->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-[14px] font-bold text-gray-900 mb-3"><span class="material-symbols-outlined text-[16px] align-text-bottom">group</span> Peserta Perjalanan</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($budgetRequest->participants as $p)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 rounded-full text-[12px] font-medium text-gray-700">
                <span class="w-5 h-5 rounded-full bg-indigo-400 text-white text-[9px] font-bold flex items-center justify-center">{{ substr($p->full_name, 0, 1) }}</span>
                {{ $p->full_name }}
            </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Attachments --}}
    @if($budgetRequest->attachments->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-[14px] font-bold text-gray-900 mb-3"><span class="material-symbols-outlined text-[16px] align-text-bottom">attach_file</span> Lampiran</h3>
        <div class="space-y-2">
            @foreach($budgetRequest->attachments as $att)
            <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition-all">
                <span class="material-symbols-outlined text-[18px] text-gray-500">description</span>
                <span class="text-[13px] text-gray-700 font-medium">{{ $att->file_name }}</span>
                <span class="text-[11px] text-gray-400 ml-auto">{{ round($att->file_size / 1024) }} KB</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Approval Log --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-[14px] font-bold text-gray-900 mb-3"><span class="material-symbols-outlined text-[16px] align-text-bottom">timeline</span> Riwayat Approval</h3>
        @if($budgetRequest->approvalLogs->isEmpty())
            <p class="text-[13px] text-gray-400">Belum ada riwayat approval.</p>
        @else
            <div class="space-y-3">
                @foreach($budgetRequest->approvalLogs->sortBy('created_at') as $log)
                <div class="flex items-start gap-3 pl-3 border-l-2 {{ $log->action === 'approved' ? 'border-emerald-400' : 'border-red-400' }}">
                    <div>
                        <div class="text-[13px] font-semibold text-gray-800">
                            {{ $log->approver->full_name ?? 'Unknown' }}
                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold {{ $log->action === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $log->action === 'approved' ? 'Disetujui' : 'Ditolak' }}
                            </span>
                            <span class="text-[11px] text-gray-400 font-normal ml-1">Step {{ $log->step_order }}</span>
                        </div>
                        @if($log->notes)
                        <p class="text-[12px] text-gray-500 mt-0.5">{{ $log->notes }}</p>
                        @endif
                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $log->created_at->format('d M Y H:i') }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Payment Tracking --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-[14px] font-bold text-gray-900 mb-3"><span class="material-symbols-outlined text-[16px] align-text-bottom text-teal-600">payments</span> Pembayaran</h3>

        @if($budgetRequest->payments && $budgetRequest->payments->count())
            <div class="space-y-3 mb-4">
                @foreach($budgetRequest->payments as $payment)
                <div class="flex items-start gap-3 pl-3 border-l-2 border-teal-400">
                    <div class="flex-1">
                        <div class="text-[13px] font-semibold text-gray-800">
                            Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-teal-100 text-teal-700">{{ strtoupper($payment->status) }}</span>
                            <span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-600">{{ $payment->method_label }}</span>
                        </div>
                        @if($payment->reference_no)
                        <div class="text-[12px] text-gray-500 mt-0.5">Ref: {{ $payment->reference_no }}</div>
                        @endif
                        @if($payment->notes)
                        <div class="text-[12px] text-gray-500 mt-0.5">{{ $payment->notes }}</div>
                        @endif
                        <div class="text-[11px] text-gray-400 mt-0.5">
                            {{ $payment->paid_at?->format('d M Y H:i') }} · oleh {{ $payment->processor->full_name ?? '-' }}
                        </div>
                        @if($payment->payment_proof)
                        <a href="{{ asset('storage/' . $payment->payment_proof) }}" target="_blank" class="inline-flex items-center gap-1 mt-1 text-[11px] text-indigo-600 hover:underline">
                            <span class="material-symbols-outlined text-[14px]">description</span> Bukti Pembayaran
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-[13px] text-gray-400 mb-4">Belum ada pembayaran.</p>
        @endif

        @if(in_array($budgetRequest->status, ['approved', 'paid']))
        <div class="pt-4 border-t border-gray-100">
            <button onclick="document.getElementById('paymentModal').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-teal-600 to-teal-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                <span class="material-symbols-outlined text-[14px]">add</span> Proses Pembayaran
            </button>
        </div>
        @endif
    </div>
</div>

{{-- Payment Modal --}}
@if(in_array($budgetRequest->status, ['approved', 'paid']))
<div id="paymentModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
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
