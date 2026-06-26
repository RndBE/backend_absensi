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

    {{-- Header --}}
    <section class="rounded-xl bg-white border border-gray-200 shadow-sm p-5 flex items-center gap-4 mb-2">
        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-600 to-emerald-500 text-white flex items-center justify-center text-[18px] font-black shrink-0">
            {{ strtoupper(substr($employee->full_name ?? 'E', 0, 1)) }}
        </div>
        <div class="min-w-0">
            <h1 class="text-[18px] font-black text-gray-900 truncate">{{ $employee->full_name }}</h1>
            <p class="text-[13px] text-gray-500 truncate">{{ $employee->position ?? 'Karyawan' }}</p>
            <p class="text-[12px] text-gray-400 mt-0.5">{{ $employee->employee_code }}</p>
        </div>
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
