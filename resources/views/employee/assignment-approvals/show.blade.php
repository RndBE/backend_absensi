@extends('employee.layouts.app')
@section('title', 'Persetujuan Surat Tugas')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.approvals.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Persetujuan Tim
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Surat Tugas</h1>
        <p class="text-[13px] text-gray-500 mt-1">
            {{ $letter->number }}
            · <span class="font-semibold">{{ $letter->statusLabel() }}</span>
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] font-semibold text-emerald-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Peserta</div>
            <ul class="mt-1 space-y-0.5">
                @foreach($letter->employees as $row)
                    <li class="text-[13px] text-gray-800"><span class="font-semibold">{{ $row->name }}</span> <span class="text-gray-400">· {{ $row->position ?: '-' }}</span></li>
                @endforeach
            </ul>
        </div>

        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Tugas</div>
            @if($heading = $letter->purposeHeading())
                <p class="mt-1 text-[13px] text-gray-800">{{ $heading }}</p>
            @endif
            <ul class="mt-1 list-disc pl-5 space-y-0.5">
                @foreach($letter->destinationSentences() as $sentence)
                    <li class="text-[13px] text-gray-800">{{ $sentence }}</li>
                @endforeach
            </ul>
        </div>

        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Jadwal</div>
            <p class="mt-1 text-[13px] text-gray-800">{{ $letter->scheduleSentence() }}</p>
        </div>

        <div class="text-[12px] text-gray-500">
            Ditandatangani: {{ $letter->signer_name }} ({{ $letter->signer_position }}) · dibuat oleh {{ $letter->creator?->full_name ?? '-' }}
        </div>

        <a href="{{ route('employee.assignment-approvals.print', $letter->id) }}" target="_blank" rel="noopener"
           class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-3 text-[12px] font-bold text-teal-700 hover:bg-teal-100">
            <span class="material-symbols-outlined text-[16px]">description</span>
            Lihat tampilan surat
        </a>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Status persetujuan</div>
        <ul class="space-y-1.5">
            @foreach($letter->approvals as $approval)
                @php
                    $tone = match($approval->status) {
                        'approved' => 'bg-emerald-50 text-emerald-700',
                        'rejected' => 'bg-red-50 text-red-700',
                        'skipped' => 'bg-amber-50 text-amber-700',
                        default => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <li class="flex flex-wrap items-center gap-2 text-[13px]">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $tone }}">{{ $approval->statusLabel() }}</span>
                    <span class="font-semibold text-gray-800">{{ $approval->label }}</span>
                    <span class="text-gray-400">{{ $approval->approver?->full_name ?? ($approval->actor?->full_name ?? 'pemegang peran') }}</span>
                    @if($approval->acted_at)
                        <span class="text-[11px] text-gray-400">{{ $approval->acted_at->format('d/m/Y H:i') }}</span>
                    @endif
                    @if($approval->notes)
                        <span class="w-full text-[12px] text-gray-500">Catatan: {{ $approval->notes }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @if($letter->rejection_reason && $letter->isDraft())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-700">
            Pernah ditolak oleh {{ $letter->rejecter?->full_name ?? '-' }}: {{ $letter->rejection_reason }}
        </div>
    @endif

    @if($mine)
        <form method="POST" action="{{ route('employee.assignment-approvals.approve', $letter->id) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3">
            @csrf
            <div class="text-[13px] font-bold text-gray-800">Keputusan Anda sebagai {{ $mine->label }}</div>
            <textarea name="notes" rows="2" maxlength="1000"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                placeholder="Catatan (opsional saat menyetujui, wajib saat menolak)">{{ old('notes') }}</textarea>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button type="submit" formaction="{{ route('employee.assignment-approvals.reject', $letter->id) }}"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-red-700 bg-white border border-red-200 rounded-lg hover:bg-red-50">
                    <span class="material-symbols-outlined text-[17px]">close</span> Tolak
                </button>
                <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700">
                    <span class="material-symbols-outlined text-[17px]">check</span> Setujui
                </button>
            </div>
            @unless($hasSignature)
                <p class="text-[12px] text-amber-600">
                    Tanda tangan digital Anda belum ada, jadi kotak paraf hanya memuat nama Anda.
                    <a href="{{ route('employee.profile.show') }}#tanda-tangan" class="font-semibold underline">Unggah tanda tangan Anda di Profil</a> sebelum menyetujui.
                </p>
            @endunless
        </form>
    @else
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-[13px] text-gray-500">
            Tidak ada keputusan yang menunggu Anda untuk surat ini.
        </div>
    @endif
</div>
@endsection
