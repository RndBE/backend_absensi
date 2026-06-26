@extends('employee.layouts.app')
@section('title', 'Slip Gaji')

@php
    $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $periodLabel = function ($period) use ($monthNames) {
        [$y, $m] = array_pad(explode('-', (string) $period), 2, null);
        return ($monthNames[(int) $m] ?? $m) . ' ' . $y;
    };
@endphp

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <div>
        <a href="{{ route('employee.profile.show') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Profil Saya
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Slip Gaji</h1>
        <p class="text-[13px] text-gray-500 mt-1">Slip gaji yang sudah dipublikasikan dapat diunduh di sini.</p>
    </div>

    @if($locked)
        {{-- Placeholder terkunci (data tidak dimuat sampai kata sandi terverifikasi) --}}
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 flex flex-col items-center text-center">
            <span class="w-14 h-14 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
                <span class="material-symbols-outlined text-[26px]">lock</span>
            </span>
            <p class="text-[14px] font-bold text-gray-800">Slip gaji terkunci</p>
            <p class="text-[13px] text-gray-500 mt-1">Masukkan kata sandi untuk membuka.</p>
        </section>
    @else
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
            @forelse($payslips as $slip)
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 bg-emerald-50 text-emerald-600">
                        <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[14px] font-bold text-gray-900">{{ $periodLabel($slip->payrollRun->period) }}</div>
                        <div class="text-[12px] text-gray-500">Gaji bersih: Rp {{ number_format((float) $slip->net_salary, 0, ',', '.') }}</div>
                    </div>
                    <a href="{{ route('employee.payslips.download', $slip->id) }}"
                       class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[12px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all shrink-0">
                        <span class="material-symbols-outlined text-[16px]">download</span>
                        Unduh
                    </a>
                </div>
            @empty
                <div class="text-center py-12 text-[13px] text-gray-400">Belum ada slip gaji yang tersedia.</div>
            @endforelse
        </section>
    @endif
</div>

@if($locked)
{{-- Modal verifikasi kata sandi --}}
<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative w-full max-w-sm bg-white rounded-xl shadow-2xl p-6">
        <div class="flex flex-col items-center text-center mb-5">
            <span class="w-14 h-14 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
                <span class="material-symbols-outlined text-[26px]">lock</span>
            </span>
            <h2 class="text-[16px] font-bold text-gray-900">Verifikasi Kata Sandi</h2>
            <p class="text-[13px] text-gray-500 mt-1">Demi keamanan, masukkan kata sandi login Anda untuk membuka slip gaji.</p>
        </div>

        <form action="{{ route('employee.payslips.unlock') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Kata Sandi</label>
                <input type="password" name="password" required autofocus
                       class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                @error('password')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('employee.profile.show') }}"
                   class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-bold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:from-indigo-700 hover:to-indigo-600 transition-all">
                    <span class="material-symbols-outlined text-[18px]">lock_open</span>
                    Buka
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
