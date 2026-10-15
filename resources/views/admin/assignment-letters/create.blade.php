@extends('admin.layouts.app')
@section('title', 'Buat Surat Tugas')

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.assignment-letters.index') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
    <span class="text-[12px] text-gray-400">Nomor surat keluar saat diterbitkan, bukan saat draft disimpan.</span>
</div>

@include('admin.assignment-letters._form', [
    'action' => route('admin.assignment-letters.store'),
    'method' => 'POST',
    'cancelUrl' => route('admin.assignment-letters.index'),
])
@endsection
