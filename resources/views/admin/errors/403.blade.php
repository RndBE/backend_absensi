@extends('admin.layouts.app')
@section('title', 'Akses Ditolak')

@section('content')
<div class="min-h-[62vh] flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center bg-white border border-gray-200 rounded-2xl p-8 shadow-sm">
        <div class="w-16 h-16 mx-auto rounded-full bg-rose-100 flex items-center justify-center mb-5">
            <span class="material-symbols-outlined text-[34px] text-rose-600">lock</span>
        </div>

        <p class="text-[12px] font-bold uppercase tracking-[2px] text-rose-500 mb-1">Error 403</p>
        <h2 class="text-[19px] font-bold text-gray-900">Akses Ditolak</h2>
        <p class="text-[13px] text-gray-500 mt-2 leading-relaxed">
            Role Anda tidak memiliki izin untuk membuka halaman ini.
            Jika menurut Anda ini keliru, hubungi admin atau HR.
        </p>

        <div class="mt-6 flex items-center justify-center gap-2.5">
            <a href="javascript:history.back()"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
            </a>
            <a href="{{ route('admin.dashboard') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
                <span class="material-symbols-outlined text-[16px]">dashboard</span> Ke Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
