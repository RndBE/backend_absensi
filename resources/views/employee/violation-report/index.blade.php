@extends('employee.layouts.app')
@section('title', 'Aduan Pelanggaran')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Dashboard
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Aduan Pelanggaran</h1>
        <p class="text-[13px] text-gray-500 mt-1">Laporkan kejadian yang perlu ditindaklanjuti perusahaan.</p>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[23px]">report</span>
            </div>
            <div class="min-w-0">
                <h2 class="text-[16px] font-black text-gray-900">Form Pelaporan Pelanggaran</h2>
                <p class="mt-2 text-[13px] leading-6 text-gray-600">Pelaporan dilakukan melalui form resmi perusahaan. Pastikan informasi yang disampaikan jelas dan dapat dipertanggungjawabkan.</p>
            </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-[12px] text-gray-500">Form akan terbuka di tab baru.</p>
            <a href="https://tinyurl.com/PELAPORAN-PELANGGARAN-ATC" target="_blank" rel="noopener noreferrer"
               onclick="this.querySelector('[data-default-label]').classList.add('hidden'); this.querySelector('[data-loading-label]').classList.remove('hidden'); setTimeout(() => { this.querySelector('[data-loading-label]').classList.add('hidden'); this.querySelector('[data-default-label]').classList.remove('hidden'); }, 1600);"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-[12px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
                <span class="material-symbols-outlined text-[17px]">open_in_new</span>
                <span data-default-label>Buka Form Pelaporan</span>
                <span data-loading-label class="hidden">Membuka form...</span>
            </a>
        </div>
    </section>
</div>
@endsection
