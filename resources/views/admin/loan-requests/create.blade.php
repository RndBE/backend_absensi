@extends('admin.layouts.app')
@section('title', 'Tambah Pinjaman')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <div>
        <h1 class="text-[22px] font-bold text-gray-900">Tambah Pinjaman</h1>
        <p class="text-[12px] text-gray-400 mt-0.5">Catat pinjaman karyawan secara manual.</p>
    </div>
    <a href="{{ route('admin.loan-requests.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
</div>

<form action="{{ route('admin.loan-requests.store') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    @csrf
    @include('admin.loan-requests._form')

    <div class="mt-5 flex justify-end gap-2">
        <a href="{{ route('admin.loan-requests.index') }}" class="inline-flex items-center justify-center px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Batal</a>
        <button class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">save</span>
            Simpan
        </button>
    </div>
</form>
@endsection
