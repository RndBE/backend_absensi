@extends('employee.layouts.app')
@section('title', 'Ajukan Cuti')

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <div>
        <a href="{{ route('employee.leaves.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Pengajuan Cuti
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Ajukan Cuti / Izin</h1>
        <p class="text-[13px] text-gray-500 mt-1">Isi periode dan alasan pengajuan.</p>
    </div>

    <form action="{{ route('employee.leaves.store') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Jenis Cuti / Izin</label>
            <select name="leave_type_id" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                <option value="">Pilih jenis</option>
                @foreach($leaveTypes as $type)
                    @php $balance = $balances->get($type->id); @endphp
                    <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                        {{ $type->name }}{{ $balance ? ' - sisa '.$balance->remaining_days.' hari' : '' }}
                    </option>
                @endforeach
            </select>
            @error('leave_type_id')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Mulai</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Selesai</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Total Hari</label>
                <input type="number" name="total_days" value="{{ old('total_days', 1) }}" min="0.5" step="0.5" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Alasan</label>
            <textarea name="reason" rows="4" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 resize-none" placeholder="Tuliskan alasan pengajuan">{{ old('reason') }}</textarea>
            @error('reason')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[18px]">send</span>
            Kirim Pengajuan
        </button>
    </form>
</div>
@endsection
