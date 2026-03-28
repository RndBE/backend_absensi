@extends('admin.layouts.app')
@section('title', 'Kelola Shift')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">settings</span> Kelola Master Shift</h3>
        <a href="{{ route('admin.schedules.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">← Kembali ke Jadwal</a>
    </div>
    <div class="p-5">

        {{-- Existing Shifts --}}
        @if($shifts->isNotEmpty())
        <div class="space-y-2 mb-6">
            @foreach($shifts as $shift)
            <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 hover:shadow-sm transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-[12px] font-bold" style="background-color: {{ $shift->color }}">
                        {!! $shift->is_off ? '<span class="material-symbols-outlined text-[14px]">home</span>' : '<span class="material-symbols-outlined text-[14px]">schedule</span>' !!}
                    </div>
                    <div>
                        <div class="text-[14px] font-bold text-gray-900">{{ $shift->name }}</div>
                        <div class="text-[12px] text-gray-400">
                            @if($shift->is_off)
                                Libur / Off Day
                            @else
                                {{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Inline Edit --}}
                    <form action="{{ route('admin.shifts.update', $shift->id) }}" method="POST" class="flex items-center gap-1.5">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $shift->name }}" class="w-[100px] px-2 py-1.5 border border-gray-300 rounded-lg text-[11px] outline-none focus:border-indigo-500">
                        <input type="time" name="start_time" value="{{ $shift->start_time ? substr($shift->start_time, 0, 5) : '' }}" class="w-[90px] px-2 py-1.5 border border-gray-300 rounded-lg text-[11px] outline-none focus:border-indigo-500" {{ $shift->is_off ? 'disabled' : '' }}>
                        <input type="time" name="end_time" value="{{ $shift->end_time ? substr($shift->end_time, 0, 5) : '' }}" class="w-[90px] px-2 py-1.5 border border-gray-300 rounded-lg text-[11px] outline-none focus:border-indigo-500" {{ $shift->is_off ? 'disabled' : '' }}>
                        <input type="color" name="color" value="{{ $shift->color }}" class="w-8 h-8 rounded cursor-pointer border-0">
                        <label class="flex items-center gap-1 text-[10px] text-gray-500"><input type="checkbox" name="is_off" value="1" {{ $shift->is_off ? 'checked' : '' }} class="accent-indigo-500"> Off</label>
                        <button type="submit" class="px-2 py-1.5 text-[10px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">save</button>
                    </form>
                    <form action="{{ route('admin.shifts.destroy', $shift->id) }}" method="POST" onsubmit="return confirm('Hapus shift {{ $shift->name }}?')">
                        @csrf @method('DELETE')
                        <button class="px-2 py-1.5 text-[10px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Add New --}}
        <div class="p-4 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
            <h4 class="text-[13px] font-bold text-gray-700 mb-3"><span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Tambah Shift Baru</h4>
            <form action="{{ route('admin.shifts.store') }}" method="POST" class="flex items-end gap-3 flex-wrap">
                @csrf
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama</label>
                    <input type="text" name="name" class="w-[120px] px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" placeholder="Pagi" required>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Jam Masuk</label>
                    <input type="time" name="start_time" class="w-[110px] px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" value="08:00">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Jam Pulang</label>
                    <input type="time" name="end_time" class="w-[110px] px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" value="17:00">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Warna</label>
                    <input type="color" name="color" value="#3B82F6" class="w-10 h-10 rounded cursor-pointer border-0">
                </div>
                <div>
                    <label class="flex items-center gap-1.5 text-[12px] font-medium text-gray-600 mb-1">
                        <input type="checkbox" name="is_off" value="1" class="accent-indigo-500"> Hari Libur
                    </label>
                </div>
                <button type="submit" class="inline-flex items-center gap-1 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">＋ Tambah</button>
            </form>
        </div>
    </div>
</div>
@endsection
