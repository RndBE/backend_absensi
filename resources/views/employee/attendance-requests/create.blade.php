@extends('employee.layouts.app')
@section('title', 'Ajukan Absensi')

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <div>
        <a href="{{ route('employee.attendance-requests.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Pengajuan Absensi
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Ajukan Absensi</h1>
        <p class="text-[13px] text-gray-500 mt-1">Isi tanggal, jam yang perlu dikoreksi, dan alasan.</p>
    </div>

    <form action="{{ route('employee.attendance-requests.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Tanggal</label>
            <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
            @error('date')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Clock In</label>
                <input type="time" name="clock_in" value="{{ old('clock_in') }}" class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
                @error('clock_in')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Clock Out</label>
                <input type="time" name="clock_out" value="{{ old('clock_out') }}" class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
                @error('clock_out')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
            </div>
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Alasan</label>
            <textarea name="reason" rows="4" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light] resize-none" placeholder="Tuliskan alasan pengajuan absensi">{{ old('reason') }}</textarea>
            @error('reason')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Lampiran</label>
            <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-bold hover:file:bg-indigo-100">
            <p class="text-[11px] text-gray-400 mt-1">Opsional. Format jpg, png, atau pdf.</p>
            @error('attachments.*')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[18px]">send</span>
            Kirim Pengajuan
        </button>
    </form>
</div>
@endsection
