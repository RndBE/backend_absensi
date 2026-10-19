@extends('admin.layouts.app')
@section('title', $letter->isIssued() ? 'Ubah Surat Tugas' : 'Ubah Draft Surat Tugas')

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.assignment-letters.show', $letter->id) }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
</div>

@if($letter->isIssued())
    <div class="mb-5 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-[13px] text-amber-800">
        Surat <strong>{{ $letter->number }}</strong> sudah terbit tetapi belum ada hasil pindai bertanda tangan, jadi masih bisa diubah:
        nomor tetap, persetujuan tidak diulang, dan peserta diberi notifikasi. Cetak ulang sebelum ditandatangani Direktur.
        Setelah pindaian diunggah, surat final dan koreksinya lewat "Buat Perbaikan".
    </div>
@endif

@include('admin.assignment-letters._form', [
    'action' => route('admin.assignment-letters.update', $letter->id),
    'method' => 'PUT',
    'cancelUrl' => route('admin.assignment-letters.show', $letter->id),
])
@endsection
