@extends('employee.layouts.app')
@section('title', 'Info Personal')

@php
    $gender = ['male' => 'Laki-laki', 'female' => 'Perempuan', 'L' => 'Laki-laki', 'P' => 'Perempuan'][$employee->gender] ?? $employee->gender;
    $marital = ['single' => 'Belum Menikah', 'married' => 'Menikah', 'divorced' => 'Cerai'][$employee->marital_status] ?? $employee->marital_status;
    $ttl = trim(($employee->birth_place ?? '') . ($employee->birth_date ? ', ' . $employee->birth_date->format('d/m/Y') : ''), ', ');

    $personal = [
        'Nama Lengkap' => $employee->full_name,
        'Email' => $employee->email,
        'No. Telepon' => $employee->phone,
        'NIK' => $employee->nik,
        'Tempat, Tgl Lahir' => $ttl ?: null,
        'Jenis Kelamin' => $gender,
        'Status Pernikahan' => $marital,
        'Golongan Darah' => $employee->blood_type,
        'Agama' => $employee->religion,
        'Kode Pos' => $employee->postal_code,
        'Alamat KTP' => $employee->ktp_address,
        'Alamat Domisili' => $employee->residential_address,
    ];
@endphp

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.profile.show') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Profil Saya
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Info Personal</h1>
        </div>
        <a href="{{ route('employee.profile.data-change') }}"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-all shrink-0">
            <span class="material-symbols-outlined text-[17px]">edit_note</span>
            Ajukan Perubahan
        </a>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
        @foreach($personal as $label => $value)
            <div class="px-5 py-3.5">
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ $label }}</div>
                <div class="mt-1 text-[14px] font-semibold text-gray-900 break-words">{{ $value ?: '-' }}</div>
            </div>
        @endforeach
    </section>
</div>
@endsection
