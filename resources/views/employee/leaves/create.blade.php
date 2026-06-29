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

    <form action="{{ route('employee.leaves.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Jenis Cuti / Izin</label>
            <select name="leave_type_id" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
                <option value="">Pilih jenis</option>
                @foreach($leaveTypes as $type)
                    @php $balance = $balances->get($type->id); @endphp
                    <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                        {{ $type->name }}{{ $balance && $type->name === 'Cuti Tahunan' ? ' - sisa '.$balance->remaining_days.' hari' : '' }}
                    </option>
                @endforeach
            </select>
            @error('leave_type_id')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Mulai</label>
                <input type="date" id="leaveStartDate" name="start_date" value="{{ old('start_date') }}" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Selesai</label>
                <input type="date" id="leaveEndDate" name="end_date" value="{{ old('end_date') }}" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Total Hari</label>
                <input type="number" id="leaveTotalDays" name="total_days" value="{{ old('total_days', 1) }}" min="1" step="1" required readonly class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg bg-gray-50 text-gray-700 outline-none focus:border-indigo-500 cursor-not-allowed [color-scheme:light]">
            </div>
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Alasan</label>
            <textarea name="reason" rows="4" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 resize-none [color-scheme:light]" placeholder="Tuliskan alasan pengajuan">{{ old('reason') }}</textarea>
            @error('reason')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Lampiran <span class="font-semibold text-gray-400">(opsional)</span></label>
            <input type="file" name="attachment" accept="image/*,.pdf" class="w-full text-[12px] text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-600 file:text-[12px] file:font-semibold file:cursor-pointer">
            <p class="text-[11px] text-gray-400 mt-1">Mis. surat dokter untuk izin sakit. Format JPG/PNG/PDF, maks 5MB.</p>
            @error('attachment')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[18px]">send</span>
            Kirim Pengajuan
        </button>
    </form>
</div>

<script>
    function calculateLeaveTotalDays() {
        const startInput = document.getElementById('leaveStartDate');
        const endInput = document.getElementById('leaveEndDate');
        const totalInput = document.getElementById('leaveTotalDays');

        if (!startInput || !endInput || !totalInput || !startInput.value || !endInput.value) {
            return;
        }

        const startDate = new Date(`${startInput.value}T00:00:00`);
        const endDate = new Date(`${endInput.value}T00:00:00`);
        const dayInMilliseconds = 24 * 60 * 60 * 1000;
        const totalDays = Math.floor((endDate - startDate) / dayInMilliseconds) + 1;

        totalInput.value = totalDays > 0 ? totalDays : 1;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const startInput = document.getElementById('leaveStartDate');
        const endInput = document.getElementById('leaveEndDate');

        startInput?.addEventListener('change', calculateLeaveTotalDays);
        endInput?.addEventListener('change', calculateLeaveTotalDays);
        calculateLeaveTotalDays();
    });
</script>
@endsection
