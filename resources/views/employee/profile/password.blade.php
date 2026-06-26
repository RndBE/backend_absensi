@extends('employee.layouts.app')
@section('title', 'Ubah Kata Sandi')

@section('content')
<div class="max-w-md mx-auto space-y-5">
    <div>
        <a href="{{ route('employee.profile.show') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Profil Saya
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Ubah Kata Sandi</h1>
        <p class="text-[13px] text-gray-500 mt-1">Masukkan kata sandi lama dan kata sandi baru.</p>
    </div>

    <form action="{{ route('employee.profile.password.update') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Kata Sandi Saat Ini</label>
            <input type="password" name="current_password" required
                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            @error('current_password')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Kata Sandi Baru</label>
            <input type="password" name="new_password" required
                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            <p class="text-[11px] text-gray-400 mt-1">Minimal 8 karakter.</p>
            @error('new_password')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Konfirmasi Kata Sandi Baru</label>
            <input type="password" name="new_password_confirmation" required
                   class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
        </div>
        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-600 transition-all">
            <span class="material-symbols-outlined text-[18px]">key</span>
            Simpan Kata Sandi
        </button>
    </form>
</div>
@endsection
