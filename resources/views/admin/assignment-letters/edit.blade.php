@extends('admin.layouts.app')
@section('title', 'Ubah Draft Surat Tugas')

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.assignment-letters.show', $letter->id) }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
</div>

@include('admin.assignment-letters._form', [
    'action' => route('admin.assignment-letters.update', $letter->id),
    'method' => 'PUT',
    'cancelUrl' => route('admin.assignment-letters.show', $letter->id),
])
@endsection
