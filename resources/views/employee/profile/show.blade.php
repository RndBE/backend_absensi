@extends('employee.layouts.app')
@section('title', 'Profil Saya')

@php
    $infoSaya = [
        ['label' => 'Info Personal', 'icon' => 'person', 'url' => route('employee.profile.personal'), 'icon_class' => 'bg-indigo-50 text-indigo-600'],
        ['label' => 'Info Pekerjaan', 'icon' => 'work', 'url' => route('employee.profile.employment'), 'icon_class' => 'bg-blue-50 text-blue-600'],
        ['label' => 'Slip Gaji', 'icon' => 'receipt_long', 'url' => route('employee.payslips.index'), 'icon_class' => 'bg-emerald-50 text-emerald-600'],
    ];
    $pengaturan = [
        ['label' => 'Verifikasi Wajah', 'icon' => 'photo_camera', 'url' => route('employee.face-photo.show'), 'icon_class' => 'bg-rose-50 text-rose-600'],
        ['label' => 'Ubah Kata Sandi', 'icon' => 'lock', 'url' => route('employee.profile.password'), 'icon_class' => 'bg-amber-50 text-amber-600'],
    ];
@endphp

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    {{-- Tombol Kembali --}}
    <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-indigo-600 mb-1">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        Dashboard
    </a>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-[13px] font-semibold px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @error('photo')
        <div class="rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[13px] font-semibold px-4 py-3">
            {{ $message }}
        </div>
    @enderror

    {{-- Header --}}
    <section class="rounded-xl bg-white border border-gray-200 shadow-sm p-5 flex items-center gap-4 mb-2">
        <form id="photo-form" action="{{ route('employee.profile.photo.update') }}" method="POST" enctype="multipart/form-data" class="shrink-0">
            @csrf
            <input type="file" name="photo" id="photo-input" accept="image/*" class="hidden"
                   onchange="if(this.files.length) document.getElementById('photo-form').submit()">
            <button type="button" onclick="document.getElementById('photo-input').click()"
                    class="relative w-14 h-14 rounded-xl overflow-hidden shrink-0 group focus:outline-none focus:ring-2 focus:ring-blue-500"
                    title="Ganti foto profil">
                @if($employee->photo)
                    <img src="{{ asset('storage/' . $employee->photo) }}" alt="{{ $employee->full_name }}" class="w-full h-full object-cover">
                @else
                    <span class="w-full h-full bg-gradient-to-br from-blue-600 to-emerald-500 text-white flex items-center justify-center text-[18px] font-black">
                        {{ strtoupper(substr($employee->full_name ?? 'E', 0, 1)) }}
                    </span>
                @endif
                <span class="absolute inset-x-0 bottom-0 bg-black/50 text-white flex items-center justify-center py-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <span class="material-symbols-outlined text-[14px]">photo_camera</span>
                </span>
            </button>
        </form>
        <div class="min-w-0 flex-1">
            <h1 class="text-[18px] font-black text-gray-900 truncate">{{ $employee->full_name }}</h1>
            <p class="text-[13px] text-gray-500 truncate">{{ $employee->position ?? 'Karyawan' }}</p>
            <p class="text-[12px] text-gray-400 mt-0.5">{{ $employee->employee_code }}</p>
        </div>
        @if($employee->photo)
            <form action="{{ route('employee.profile.photo.destroy') }}" method="POST" class="shrink-0"
                  onsubmit="return confirm('Hapus foto profil?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Hapus foto">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                </button>
            </form>
        @endif
    </section>

    {{-- Info Saya --}}
    <div>
        <h2 class="text-[12px] font-bold text-gray-400 uppercase tracking-wider px-1 mb-2">Info Saya</h2>
        <div class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100 mb-2">
            @foreach($infoSaya as $item)
                <a href="{{ $item['url'] }}" class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $item['icon_class'] }}">
                        <span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span>
                    </span>
                    <span class="flex-1 text-[14px] font-semibold text-gray-800">{{ $item['label'] }}</span>
                    <span class="material-symbols-outlined text-[20px] text-gray-300">chevron_right</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Pengaturan --}}
    <div>
        <h2 class="text-[12px] font-bold text-gray-400 uppercase tracking-wider px-1 mb-2">Pengaturan</h2>
        <div class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
            @foreach($pengaturan as $item)
                <a href="{{ $item['url'] }}" class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $item['icon_class'] }}">
                        <span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span>
                    </span>
                    <span class="flex-1 text-[14px] font-semibold text-gray-800">{{ $item['label'] }}</span>
                    <span class="material-symbols-outlined text-[20px] text-gray-300">chevron_right</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
