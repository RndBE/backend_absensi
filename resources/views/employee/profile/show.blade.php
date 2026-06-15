@extends('employee.layouts.app')
@section('title', 'Profil Saya')

@section('content')
<div class="space-y-5">
    <section class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-600 to-emerald-500 text-white flex items-center justify-center text-[18px] font-black shrink-0">
                    {{ strtoupper(substr($employee->full_name ?? 'E', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-gray-800 mb-1">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        Dashboard
                    </a>
                    <h1 class="text-[22px] sm:text-[26px] font-black text-gray-900 tracking-tight">Profil Saya</h1>
                    <p class="text-[13px] text-gray-500 mt-1">{{ $employee->position ?? 'Karyawan' }}</p>
                </div>
            </div>
            <a href="{{ route('employee.face-photo.show') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                <span class="material-symbols-outlined text-[17px]">photo_camera</span>
                Verifikasi Wajah
            </a>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-[12px] font-bold text-gray-400 uppercase tracking-wide">Kode Karyawan</div>
            <div class="mt-2 text-[18px] font-black text-gray-900">{{ $employee->employee_code }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-[12px] font-bold text-gray-400 uppercase tracking-wide">Email</div>
            <div class="mt-2 text-[14px] font-bold text-gray-900 break-words">{{ $employee->email }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-[12px] font-bold text-gray-400 uppercase tracking-wide">Jabatan</div>
            <div class="mt-2 text-[16px] font-black text-gray-900">{{ $employee->position ?? '-' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-[12px] font-bold text-gray-400 uppercase tracking-wide">Foto Wajah</div>
            <div class="mt-2">
                @if($employee->face_photo)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                        <span class="material-symbols-outlined text-[15px]">verified_user</span>
                        Sudah Terdaftar
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">
                        <span class="material-symbols-outlined text-[15px]">face</span>
                        Belum Terdaftar
                    </span>
                @endif
            </div>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-[15px] font-black text-gray-900">Butuh bantuan presensi?</h2>
                <p class="text-[12px] text-gray-500 mt-1">Cek panduan izin lokasi, kamera, GPS, dan akses local/ngrok.</p>
            </div>
            <a href="{{ route('employee.help.attendance') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-all">
                <span class="material-symbols-outlined text-[17px]">help</span>
                Bantuan Presensi
            </a>
        </div>
    </section>
</div>
@endsection
