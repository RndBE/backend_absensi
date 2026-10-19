@extends('admin.layouts.app')
@section('title', 'Buat Surat Tugas')

@section('content')
@php
    $tabClass = fn (bool $active) => 'px-4 py-2 text-[13px] font-semibold border-b-2 -mb-[2px] whitespace-nowrap transition-all duration-200 '
        .($active ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700');
    $revisionActive = $tab === 'revision';
@endphp

<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.assignment-letters.index') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
    <span class="text-[12px] text-gray-400">Nomor surat keluar saat diterbitkan, bukan saat draft disimpan.</span>
</div>

<div class="flex gap-0 border-b-2 border-gray-200 mb-5">
    <a href="{{ route('admin.assignment-letters.create') }}" class="{{ $tabClass(! $revisionActive) }}">Surat Baru</a>
    <a href="{{ route('admin.assignment-letters.create', ['tab' => 'revision']) }}" class="{{ $tabClass($revisionActive) }}">Perbaikan Surat Tugas</a>
</div>

@if($revisionActive)
    {{-- Pilih surat yang diperbaiki. Select ini otomatis memakai pemilih berpencarian dan daftar bergulir
         yang sama dengan select lain di aplikasi; memilih surat memuat formulir yang sudah berisi
         data surat itu supaya isinya bisa langsung diubah sebelum draft perbaikan disimpan. --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-1">
            <span class="material-symbols-outlined text-[16px] text-indigo-500">edit_note</span> Surat yang diperbaiki
        </h3>
        <p class="text-[12.5px] text-gray-500 mb-3">
            Pilih surat tugas yang sudah terbit. Formulir di bawah terisi data surat itu; ubah yang perlu diperbaiki lalu simpan.
            Surat perbaikan mendapat nomor baru saat diterbitkan dan cetakannya menyebut surat lama. Surat lama tidak berubah dan tidak otomatis dibatalkan.
        </p>

        <label for="revise_source" class="block text-[12px] font-semibold text-gray-600 mb-1">Nomor surat tugas</label>
        <select id="revise_source" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <option value="">— Pilih surat tugas yang sudah terbit —</option>
            @foreach($issuedLetters as $item)
                @php
                    $names = \Illuminate\Support\Str::limit($item->employees->pluck('name')->implode(', '), 60);
                    $activity = \Illuminate\Support\Str::limit((string) $item->destinations->first()?->activity, 50);
                    $open = $item->revisions->first(fn ($r) => $r->status === 'draft');
                @endphp
                <option value="{{ $item->id }}" @selected($revisesLetter && $revisesLetter->id === $item->id)>
                    {{ $item->number }} — {{ $names }} — {{ $activity }} ({{ $item->departure_date->format('d M Y') }}){{ $open ? ' · sudah ada draft perbaikan' : '' }}
                </option>
            @endforeach
        </select>

        @if($revisesLetter && ! $revisesLetter->hasSignedFile())
            <div class="mt-3 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-[12.5px] text-amber-800">
                Surat {{ $revisesLetter->number }} belum ada hasil pindai bertanda tangan. Biasanya cukup diubah langsung (nomor tetap, paraf yang sudah masuk tidak diulang),
                bukan dibuatkan surat perbaikan.
                <a href="{{ route('admin.assignment-letters.edit', $revisesLetter->id) }}" class="font-semibold underline">Ubah surat ini</a>
                · atau lanjutkan di bawah bila memang perlu surat baru.
            </div>
        @endif
    </div>
@endif

@if(! $revisionActive || $revisesLetter)
    @include('admin.assignment-letters._form', [
        'action' => route('admin.assignment-letters.store'),
        'method' => 'POST',
        'cancelUrl' => route('admin.assignment-letters.index'),
        'revisesLetter' => $revisesLetter,
    ])
@endif
@endsection

@if($revisionActive)
@push('scripts')
<script>
    // Memilih surat memuat ulang halaman dengan formulir terisi data surat itu.
    document.getElementById('revise_source').addEventListener('change', function () {
        const base = @json(route('admin.assignment-letters.create', ['tab' => 'revision']));
        window.location.href = this.value ? base + '&source=' + encodeURIComponent(this.value) : base;
    });
</script>
@endpush
@endif
