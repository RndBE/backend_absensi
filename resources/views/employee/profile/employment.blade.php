@extends('employee.layouts.app')
@section('title', 'Info Pekerjaan')

@php
    $employmentStatus = [
        'permanent' => 'Tetap', 'contract' => 'Kontrak', 'probation' => 'Percobaan',
        'intern' => 'Magang', 'outsourcing' => 'Outsourcing',
    ][$employee->employment_status] ?? $employee->employment_status;

    $employment = [
        'Kode Karyawan' => $employee->employee_code,
        'Departemen' => $employee->department->name ?? null,
        'Jabatan' => $employee->position,
        'Level' => $employee->job_level,
        'Status Kepegawaian' => $employmentStatus,
        'Tanggal Bergabung' => $employee->join_date?->format('d/m/Y'),
    ];
@endphp

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <div>
        <a href="{{ route('employee.profile.show') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Profil Saya
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Info Pekerjaan</h1>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
        @foreach($employment as $label => $value)
            <div class="px-5 py-3.5">
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ $label }}</div>
                <div class="mt-1 text-[14px] font-semibold text-gray-900 break-words">{{ $value ?: '-' }}</div>
            </div>
        @endforeach
        <div class="px-5 py-3.5">
            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Foto Wajah</div>
            <div class="mt-1.5">
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
</div>
@endsection
