@extends('admin.layouts.app')
@section('title', 'Detail Surat Tugas')

@section('content')
@php
    $statusColors = [
        'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
        'issued' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
    ];
    $card = 'bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5';
    $heading = 'text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-3';
    $btn = 'inline-flex items-center gap-1.5 px-3.5 py-2 text-[12px] font-semibold rounded-lg transition-all cursor-pointer';
    $devices = $letter->facilities->where('type', 'device');
    $estimatedNumber = $letter->estimatedNumber();
    $transports = $letter->facilities->where('type', 'transport');
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <a href="{{ route('admin.assignment-letters.index') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.assignment-letters.print', $letter->id) }}" target="_blank" class="{{ $btn }} text-teal-700 bg-teal-50 border border-teal-200 hover:bg-teal-100">
            <span class="material-symbols-outlined text-[16px]">print</span> {{ $letter->isDraft() ? 'Pratinjau Cetak' : 'Cetak' }}
        </a>
        @if($letter->isDraft())
            @if($canManage)
                <a href="{{ route('admin.assignment-letters.edit', $letter->id) }}" class="{{ $btn }} text-indigo-600 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100">
                    <span class="material-symbols-outlined text-[16px]">edit</span> Ubah
                </a>
                <form method="POST" action="{{ route('admin.assignment-letters.destroy', $letter->id) }}" data-confirm="Hapus draft surat tugas ini?">
                    @csrf @method('DELETE')
                    <button type="submit" class="{{ $btn }} text-red-600 bg-red-50 border border-red-200 hover:bg-red-100">
                        <span class="material-symbols-outlined text-[16px]">delete</span> Hapus
                    </button>
                </form>
            @endif
            @if($canIssue)
                <form method="POST" action="{{ route('admin.assignment-letters.issue', $letter->id) }}"
                      data-confirm="Surat akan terbit dengan nomor {{ $estimatedNumber }} dan tidak bisa diubah lagi. Terbitkan sekarang?" data-confirm-variant="primary" data-confirm-text="Terbitkan">
                    @csrf
                    <button type="submit" class="{{ $btn }} text-white bg-gradient-to-br from-indigo-600 to-indigo-400 shadow-sm hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-[16px]">verified</span> Terbitkan
                    </button>
                </form>
            @endif
        @else
            @if($canManage)
                <form method="POST" action="{{ route('admin.assignment-letters.duplicate', $letter->id) }}"
                      data-confirm="Buat draft baru berisi salinan surat ini?" data-confirm-variant="primary">
                    @csrf
                    <button type="submit" class="{{ $btn }} text-indigo-600 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100">
                        <span class="material-symbols-outlined text-[16px]">content_copy</span> Salin ke Draft Baru
                    </button>
                </form>
            @endif
            @if($canIssue && $letter->isIssued())
                <button type="button" onclick="document.getElementById('cancelModal').classList.remove('hidden')" class="{{ $btn }} text-red-600 bg-red-50 border border-red-200 hover:bg-red-100">
                    <span class="material-symbols-outlined text-[16px]">block</span> Batalkan
                </button>
            @endif
        @endif
    </div>
</div>

<div class="{{ $card }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Surat Tugas</div>
            @if($letter->number)
                <div class="text-[20px] font-bold text-gray-900 font-mono">{{ $letter->number }}</div>
            @elseif($estimatedNumber)
                <div class="flex flex-wrap items-baseline gap-2">
                    <span class="text-[20px] font-bold text-gray-400 font-mono">{{ $estimatedNumber }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200">Perkiraan</span>
                </div>
                <div class="text-[11.5px] text-gray-400">Nomor pasti diberikan saat diterbitkan; bisa bergeser kalau ada surat lain yang terbit lebih dulu.</div>
            @else
                <div class="text-[20px] font-bold text-gray-900 font-mono">Belum bernomor (draft)</div>
            @endif
            <div class="text-[13px] text-gray-500 mt-0.5">{{ $letter->city }}, {{ \App\Models\AssignmentLetter::formatDate($letter->letter_date) }}</div>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusColors[$letter->status] ?? '' }}">{{ $letter->statusLabel() }}</span>
    </div>
    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-[13px]">
        <div><span class="text-gray-400">Penanda tangan:</span> <strong>{{ $letter->signer_name }}</strong> ({{ $letter->signer_position }})</div>
        <div><span class="text-gray-400">Jadwal:</span> {{ $letter->departure_date->format('d M Y') }} – {{ $letter->return_date->format('d M Y') }} ({{ $letter->workingDays() }} hari kerja)</div>
        <div><span class="text-gray-400">Peserta:</span> {{ $letter->employees->count() }} orang</div>
    </div>
    @if($letter->isCancelled())
        <div class="mt-4 px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 text-[13px] text-gray-700">
            Dibatalkan {{ $letter->cancelled_at?->format('d M Y H:i') }} oleh {{ $letter->canceller?->full_name ?? '-' }}.
            <div class="mt-1"><span class="text-gray-400">Alasan:</span> {{ $letter->cancel_reason }}</div>
        </div>
    @endif
</div>

{{-- Hasil pindai surat yang sudah ditandatangani & diparaf basah; peserta melihatnya dari halaman cetak. --}}
@if(! $letter->isDraft())
<div class="{{ $card }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="{{ $heading }} !mb-1"><span class="material-symbols-outlined text-[16px] text-indigo-500">verified</span> Surat Bertanda Tangan</h3>
            @if($letter->hasSignedFile())
                <div class="flex items-center gap-1.5 text-[13px] font-semibold text-gray-800">
                    <span class="material-symbols-outlined text-emerald-600" style="font-size: 18px">check_circle</span>
                    <span class="truncate">{{ $letter->signed_file_name }}</span>
                </div>
                <div class="text-[11.5px] text-gray-400 mt-0.5">Diunggah {{ $letter->signed_uploaded_at?->format('d M Y H:i') }} oleh {{ $letter->signedUploader?->full_name ?? '-' }}</div>
            @elseif($letter->isIssued())
                <p class="text-[12.5px] text-gray-500">Belum diunggah. Cetak surat, minta tanda tangan dan paraf basah, lalu unggah hasil pindainya di sini. Peserta bisa melihatnya dari halaman cetak surat.</p>
            @else
                <p class="text-[12.5px] text-gray-500">Tidak ada surat bertanda tangan yang diunggah.</p>
            @endif
        </div>
        @if($letter->hasSignedFile())
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.assignment-letters.signed.show', $letter->id) }}" target="_blank" class="{{ $btn }} text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100">
                    <span class="material-symbols-outlined text-[16px]">visibility</span> Lihat
                </a>
                @if($canManage && $letter->isIssued())
                    <form method="POST" action="{{ route('admin.assignment-letters.signed.destroy', $letter->id) }}" data-confirm="Hapus file surat bertanda tangan ini?">
                        @csrf @method('DELETE')
                        <button type="submit" class="{{ $btn }} text-red-600 bg-red-50 border border-red-200 hover:bg-red-100">
                            <span class="material-symbols-outlined text-[16px]">delete</span> Hapus
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if($canManage && $letter->isIssued())
        <form method="POST" action="{{ route('admin.assignment-letters.signed.store', $letter->id) }}" enctype="multipart/form-data"
              class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
            @csrf
            <input type="file" name="signed_file" accept=".pdf,.jpg,.jpeg,.png" required
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-[13px]">
            <button type="submit" class="{{ $btn }} text-white bg-indigo-600 hover:bg-indigo-700 shrink-0 justify-center">
                <span class="material-symbols-outlined text-[16px]">upload</span> {{ $letter->hasSignedFile() ? 'Ganti File' : 'Unggah' }}
            </button>
        </form>
        <p class="text-[11px] text-gray-400 mt-2">PDF, JPG, atau PNG, maksimal 10 MB.</p>
    @endif
</div>
@endif

@if($conflicts)
<div class="mb-5 px-4 py-3.5 rounded-xl bg-amber-50 border border-amber-200 text-[13px] text-amber-900">
    <div class="font-semibold flex items-center gap-1.5 mb-1"><span class="material-symbols-outlined text-[18px]">warning</span> Periksa jadwal yang bentrok</div>
    <ul class="list-disc pl-6 space-y-0.5">
        @foreach($conflicts as $conflict)
            <li>{{ $conflict }}</li>
        @endforeach
    </ul>
    <p class="text-[11.5px] text-amber-700 mt-2">Ini hanya peringatan. Surat tetap bisa disimpan dan diterbitkan.</p>
</div>
@endif

<div class="{{ $card }}">
    <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">group</span> Karyawan yang Ditugaskan</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                    <th class="py-2 px-3 w-10">No</th>
                    <th class="py-2 px-3">Nama</th>
                    <th class="py-2 px-3">Nomor Karyawan</th>
                    <th class="py-2 px-3">Jabatan</th>
                    <th class="py-2 px-3">Divisi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($letter->employees as $i => $row)
                <tr class="border-b border-gray-50 text-[13px]">
                    <td class="py-2 px-3 text-gray-400">{{ $i + 1 }}.</td>
                    <td class="py-2 px-3 font-semibold text-gray-900">{{ $row->name }}</td>
                    <td class="py-2 px-3 font-mono text-[12px]">{{ $row->employee_code ?: '-' }}</td>
                    <td class="py-2 px-3">{{ $row->position ?: '-' }}</td>
                    <td class="py-2 px-3 text-gray-500">{{ $row->department_name ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($letter->isDraft())
        <p class="text-[11px] text-gray-400 mt-2">Nama, nomor karyawan, dan divisi diambil ulang dari data karyawan saat surat diterbitkan.</p>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="{{ $card }}">
        <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">flag</span> Tujuan & Jadwal</h3>
        <div class="text-[13px] leading-relaxed text-gray-800 space-y-1.5">
            @if($purposeHeading = $letter->purposeHeading())
                <p>{{ $purposeHeading }}</p>
            @endif
            @foreach($letter->destinationSentences() as $sentence)
                <p>{{ $sentence }}</p>
            @endforeach
            <p class="pt-2">{{ $letter->scheduleSentence() }}</p>
        </div>
    </div>

    <div class="{{ $card }}">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-3"><span class="material-symbols-outlined text-[16px] text-indigo-500">directions_car</span> Perangkat & Transportasi</h3>
        <dl class="text-[13px] space-y-3">
            @foreach(['Perangkat Komunikasi' => $devices, 'Transportasi' => $transports] as $title => $items)
                @forelse($items as $item)
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $title }}</dt>
                        <dd class="text-gray-900 font-medium">{{ $item->description }}</dd>
                        <dd class="text-gray-600">Hasil pemeriksaan: {{ $item->inspectionLabel() }}</dd>
                        @if($item->inspector)
                            <dd class="text-[11px] text-gray-400">Dicatat oleh {{ $item->inspector->full_name }}</dd>
                        @endif
                    </div>
                @empty
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $title }}</dt>
                        <dd class="text-gray-400">-</dd>
                    </div>
                @endforelse
            @endforeach
        </dl>
    </div>
</div>

@if(! $letter->isDraft())
@php
    $budgetStatus = [
        'pending' => ['Pending', 'bg-amber-50 text-amber-700 border-amber-200'],
        'in_review' => ['Diproses', 'bg-blue-50 text-blue-700 border-blue-200'],
        'approved' => ['Disetujui', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'rejected' => ['Ditolak', 'bg-red-50 text-red-700 border-red-200'],
        'paid' => ['Dibayar', 'bg-teal-50 text-teal-700 border-teal-200'],
    ];
@endphp
<div class="{{ $card }}">
    <h3 class="{{ $heading }}"><span class="material-symbols-outlined text-[16px] text-indigo-500">request_quote</span> Anggaran Terkait</h3>
    @if($budgets->isEmpty())
        <p class="text-[13px] text-gray-500">Belum ada pengajuan anggaran yang memakai surat tugas ini. Peserta memilih surat tugas saat membuat pengajuan anggaran di portal karyawan.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-2 px-3">Pengajuan</th>
                        <th class="py-2 px-3">Pengaju</th>
                        <th class="py-2 px-3 text-right">Total</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3">LHP</th>
                        <th class="py-2 px-3">LPJ</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($budgets as $budget)
                    @php
                        [$statusLabel, $statusClass] = $budgetStatus[$budget->status] ?? [$budget->status, 'bg-gray-50 text-gray-600 border-gray-200'];
                        $reports = $travelReports[$budget->id] ?? collect();
                    @endphp
                    <tr class="border-b border-gray-50 text-[13px] align-top {{ $budget->status === 'rejected' ? 'opacity-60' : '' }}">
                        <td class="py-2 px-3">
                            @if($canViewBudgets)
                                <a href="{{ route('admin.budget-requests.show', ['id' => $budget->id] + \App\Support\AdminBackLink::here()) }}" class="font-semibold text-indigo-600 hover:underline">{{ $budget->title }}</a>
                            @else
                                <span class="font-semibold text-gray-900">{{ $budget->title }}</span>
                            @endif
                            <div class="text-[11px] text-gray-400">{{ $budget->created_at->format('d M Y') }}</div>
                        </td>
                        <td class="py-2 px-3">{{ $budget->employee?->full_name ?? '-' }}</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">Rp {{ number_format((float) $budget->total_amount, 0, ',', '.') }}</td>
                        <td class="py-2 px-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td class="py-2 px-3 text-[12px] text-gray-600">
                            {{ $reports->count() }} / {{ $letter->employees->count() }} peserta
                            @if($reports->isNotEmpty())
                                <div class="text-[11px] text-gray-400">{{ $reports->map(fn ($r) => $r->employee?->full_name)->filter()->implode(', ') }}</div>
                            @endif
                        </td>
                        <td class="py-2 px-3 text-[12px] text-gray-600">{{ $budget->lpj ? ucfirst(str_replace('_', ' ', $budget->lpj->status)) : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-3"><span class="material-symbols-outlined text-[16px] text-indigo-500">draw</span> Kolom Paraf</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($letter->initialBoxes() as $box)
                <span class="px-2.5 py-1 rounded-md border border-gray-300 text-[12px] font-semibold text-gray-700">{{ $box }}</span>
            @endforeach
        </div>
        @if($letter->notes)
            <div class="mt-4 text-[13px]"><span class="text-gray-400">Catatan internal:</span> {{ $letter->notes }}</div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-3"><span class="material-symbols-outlined text-[16px] text-indigo-500">history</span> Riwayat</h3>
        <ul class="text-[12.5px] text-gray-600 space-y-1">
            <li>Dibuat {{ $letter->created_at?->format('d M Y H:i') }} oleh {{ $letter->creator?->full_name ?? '-' }}</li>
            @if($letter->updated_at && $letter->updated_at->ne($letter->created_at))
                <li>Terakhir diubah {{ $letter->updated_at->format('d M Y H:i') }} oleh {{ $letter->updater?->full_name ?? '-' }}</li>
            @endif
            @if($letter->issued_at)
                <li>Diterbitkan {{ $letter->issued_at->format('d M Y H:i') }} oleh {{ $letter->issuer?->full_name ?? '-' }}</li>
            @endif
            @if($letter->cancelled_at)
                <li>Dibatalkan {{ $letter->cancelled_at->format('d M Y H:i') }} oleh {{ $letter->canceller?->full_name ?? '-' }}</li>
            @endif
        </ul>
    </div>
</div>

@if($canIssue && $letter->isIssued())
<div id="cancelModal" class="hidden fixed inset-0 z-[80] overflow-y-auto bg-slate-900/45 px-4 py-6">
    <div class="flex min-h-[calc(100vh-3rem)] items-start justify-center sm:items-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold text-gray-900">Batalkan {{ $letter->number }}</h3>
                <button type="button" onclick="document.getElementById('cancelModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.assignment-letters.cancel', $letter->id) }}" class="p-5 space-y-4">
                @csrf
                <p class="text-[12.5px] text-gray-600">Nomor surat ini tidak akan dipakai ulang. Untuk memperbaiki isi surat, batalkan lalu gunakan "Salin ke Draft Baru".</p>
                @php $activeBudgets = $budgets->where('status', '!=', 'rejected'); @endphp
                @if($activeBudgets->isNotEmpty())
                    <div class="px-3 py-2.5 rounded-lg bg-amber-50 border border-amber-200 text-[12px] text-amber-800">
                        Surat ini sudah dipakai pengajuan anggaran: <strong>{{ $activeBudgets->pluck('title')->implode(', ') }}</strong>.
                        Pengajuan itu tidak ikut dibatalkan; tindak lanjuti dengan tim keuangan.
                    </div>
                @endif
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Alasan pembatalan <span class="text-red-500">*</span></label>
                    <textarea name="cancel_reason" rows="3" required maxlength="500" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-red-200">{{ old('cancel_reason') }}</textarea>
                </div>
                <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-all cursor-pointer">Batalkan Surat</button>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
