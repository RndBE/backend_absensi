@extends('admin.layouts.app')
@section('title', 'Hari Libur')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    {{-- Left: Add form --}}
    <div class="md:col-span-1 space-y-4">
        {{-- Add Manual --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-[14px] font-bold text-gray-900"><span class="material-symbols-outlined text-[16px] align-text-bottom">add</span> Tambah Hari Libur</h3>
            </div>
            <form action="{{ route('admin.holidays.store') }}" method="POST" class="p-5 space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama</label>
                    <input type="text" name="name" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" placeholder="Hari Raya Idul Fitri" required>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Tanggal</label>
                    <input type="date" name="date" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" required>
                </div>
                <label class="flex items-center gap-2 text-[12px] text-gray-600 cursor-pointer">
                    <input type="checkbox" name="is_national" value="1" checked class="accent-indigo-500">
                    Libur Nasional
                </label>
                <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[14px] align-text-bottom">add_circle</span> Tambahkan
                </button>
            </form>
        </div>

        {{-- Import National --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-[14px] font-bold text-gray-900"><span class="material-symbols-outlined text-[16px] align-text-bottom">download</span> Import Libur Nasional</h3>
            </div>
            <form action="{{ route('admin.holidays.import-national') }}" method="POST" class="p-5">
                @csrf
                <p class="text-[11px] text-gray-500 mb-3">Import otomatis hari libur nasional Indonesia untuk tahun tertentu.</p>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Tahun</label>
                        <input type="number" name="year" value="{{ $year }}" min="2020" max="2030" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500">
                    </div>
                    <button type="submit" class="px-4 py-2.5 text-[12px] font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-all cursor-pointer whitespace-nowrap" onclick="return confirm('Import hari libur nasional tahun ini?')">
                        🇮🇩 Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Right: List --}}
    <div class="md:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">event_busy</span> Daftar Hari Libur {{ $year }}</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.holidays.index', ['year' => $year - 1]) }}" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all">
                        <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                    </a>
                    <span class="text-[13px] font-bold text-gray-800 min-w-[50px] text-center">{{ $year }}</span>
                    <a href="{{ route('admin.holidays.index', ['year' => $year + 1]) }}" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all">
                        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    </a>
                </div>
            </div>
            <div class="p-5">
                @if($holidays->isEmpty())
                    <div class="text-center py-10 text-gray-400 text-sm">
                        <span class="material-symbols-outlined text-[40px] block mb-2">event_busy</span>
                        Belum ada hari libur untuk tahun {{ $year }}.<br>
                        <span class="text-[12px]">Gunakan tombol <strong>Import</strong> untuk menambahkan libur nasional.</span>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($holidays as $h)
                        <div class="flex items-center justify-between px-4 py-3 rounded-xl border transition-all hover:shadow-sm
                            {{ $h->date->isPast() ? 'bg-gray-50 border-gray-200' : ($h->date->isToday() ? 'bg-indigo-50 border-indigo-300' : 'bg-white border-gray-200') }}">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-[14px]
                                    {{ $h->date->isPast() ? 'bg-gray-400' : 'bg-red-500' }}">
                                    {{ $h->date->format('d') }}
                                </div>
                                <div>
                                    <div class="text-[13px] font-bold text-gray-800">{{ $h->name }}</div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ $h->date->translatedFormat('l, d F Y') }}
                                        @if($h->is_national)
                                            · <span class="text-red-500 font-semibold">🇮🇩 Nasional</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <form action="{{ route('admin.holidays.destroy', $h->id) }}" method="POST" onsubmit="return confirm('Hapus hari libur ini?')">
                                @csrf @method('DELETE')
                                <button class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center text-red-400 hover:bg-red-100 hover:text-red-600 transition-all cursor-pointer border-0">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                </button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 text-[11px] text-gray-400 text-center">Total: {{ $holidays->count() }} hari libur</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
