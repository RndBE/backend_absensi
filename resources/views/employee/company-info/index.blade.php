@extends('employee.layouts.app')
@section('title', 'Info Perusahaan')

@section('content')
<style>
    .company-info-text-wrap {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
</style>

<div class="space-y-5">
    <div>
        <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Dashboard
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Info Perusahaan</h1>
        <p class="text-[13px] text-gray-500 mt-1">Informasi resmi dan peraturan perusahaan yang sedang berlaku.</p>
    </div>

    <section class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-14 h-14 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-center overflow-hidden shrink-0">
                        <span class="material-symbols-outlined text-[28px] text-gray-400 {{ $company?->logo ? 'hidden' : '' }}" id="company-logo-placeholder">domain</span>
                        @if($company?->logo)
                            <img src="{{ asset('storage/' . $company->logo) }}"
                                    alt=""
                                    class="w-full h-full object-contain"
                                    onerror="this.classList.add('hidden'); document.getElementById('company-logo-placeholder')?.classList.remove('hidden');">
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h1 class="company-info-text-wrap text-[20px] sm:text-[26px] font-black text-gray-900 tracking-tight leading-tight">{{ $company?->name ?: 'Perusahaan' }}</h1>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2 text-[11px] font-black uppercase text-gray-400">
                        <span class="material-symbols-outlined text-[15px]">location_on</span>
                        Alamat
                    </div>
                    <div class="mt-1 text-[13px] leading-5 text-gray-700">{{ $company?->address ?: '-' }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2 text-[11px] font-black uppercase text-gray-400">
                        <span class="material-symbols-outlined text-[15px]">call</span>
                        Telepon
                    </div>
                    <div class="mt-1 text-[13px] leading-5 text-gray-700">{{ $company?->phone ?: '-' }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center gap-2 text-[11px] font-black uppercase text-gray-400">
                        <span class="material-symbols-outlined text-[15px]">mail</span>
                        Email
                    </div>
                    <div class="mt-1 text-[13px] leading-5 text-gray-700 break-words">{{ $company?->email ?: '-' }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-black text-gray-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-indigo-500">folder_open</span>
                    Peraturan Perusahaan
                </h2>
            </div>
        </div>

        @if($regulations->isEmpty())
            <div class="p-10 text-center">
                <span class="material-symbols-outlined text-[34px] text-gray-300">rule_folder</span>
                <p class="mt-2 text-[13px] text-gray-400">Belum ada peraturan perusahaan yang aktif.</p>
            </div>
        @else
            <div class="p-5">
                <ol class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                    @foreach($regulations as $regulation)
                        @php
                            $points = collect(preg_split('/\r\n|\r|\n/', trim((string) $regulation->content)))
                                ->map(fn ($point) => trim($point))
                                ->filter()
                                ->values();
                        @endphp
                        <li class="p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center text-[12px] font-black shrink-0">{{ $loop->iteration }}</span>
                                    <h4 class="text-[14px] font-black text-gray-900 company-info-text-wrap min-w-0">{{ $regulation->title }}</h4>
                                </div>
                                @if($regulation->file_path)
                                    <a href="{{ route('employee.company-info.regulations.download', $regulation) }}"
                                        class="inline-flex items-center justify-center gap-2 px-3 py-2 text-[12px] font-bold text-indigo-700 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 transition sm:shrink-0">
                                        <span class="material-symbols-outlined text-[16px]">download</span>
                                        Lampiran
                                    </a>
                                @endif
                            </div>
                            @if($points->isNotEmpty())
                                <ul class="mt-3 ml-10 space-y-2">
                                    @foreach($points as $point)
                                        <li class="flex items-start gap-2 text-[13px] leading-6 text-gray-700 break-words">
                                            <span class="mt-2 w-1.5 h-1.5 rounded-full bg-indigo-400 shrink-0"></span>
                                            <span class="company-info-text-wrap min-w-0">{{ $point }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>
</div>
@endsection
