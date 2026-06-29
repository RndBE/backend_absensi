@extends('employee.layouts.app')
@section('title', 'Edit Pengajuan Cuti')

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <div>
        <a href="{{ route('employee.leaves.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Pengajuan Cuti
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Edit Pengajuan Cuti / Izin</h1>
        <p class="text-[13px] text-gray-500 mt-1">Hanya pengajuan berstatus pending yang bisa diubah.</p>
    </div>

    <form action="{{ route('employee.leaves.update', $leave->id) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Jenis Cuti / Izin</label>
            <select name="leave_type_id" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
                <option value="">Pilih jenis</option>
                @foreach($leaveTypes as $type)
                    @php $balance = $balances->get($type->id); @endphp
                    <option value="{{ $type->id }}" @selected(old('leave_type_id', $leave->leave_type_id) == $type->id)>
                        {{ $type->name }}{{ $balance && $type->name === 'Cuti Tahunan' ? ' - sisa '.$balance->remaining_days.' hari' : '' }}
                    </option>
                @endforeach
            </select>
            @error('leave_type_id')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Mulai</label>
                <input type="date" id="leaveStartDate" name="start_date" value="{{ old('start_date', $leave->start_date?->format('Y-m-d')) }}" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Selesai</label>
                <input type="date" id="leaveEndDate" name="end_date" value="{{ old('end_date', $leave->end_date?->format('Y-m-d')) }}" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Total Hari</label>
                <input type="number" id="leaveTotalDays" name="total_days" value="{{ old('total_days', $leave->total_days_label) }}" min="1" step="1" required readonly class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg bg-gray-50 text-gray-700 outline-none focus:border-indigo-500 cursor-not-allowed [color-scheme:light]">
            </div>
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Alasan</label>
            <textarea name="reason" rows="4" required class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 resize-none [color-scheme:light]" placeholder="Tuliskan alasan pengajuan">{{ old('reason', $leave->reason) }}</textarea>
            @error('reason')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Delegasi ke <span class="font-semibold text-gray-400">(opsional)</span></label>
            <select name="delegate_to" class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
                <option value="">— Tidak ada —</option>
                @foreach($colleagues as $colleague)
                    <option value="{{ $colleague->id }}" @selected(old('delegate_to', $leave->delegate_to) == $colleague->id)>
                        {{ $colleague->full_name }}{{ $colleague->position ? ' — '.$colleague->position : '' }}
                    </option>
                @endforeach
            </select>
            <p class="text-[11px] text-gray-400 mt-1">Rekan yang menggantikan tugas Anda selama cuti/izin.</p>
            @error('delegate_to')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Lampiran <span class="font-semibold text-gray-400">(opsional)</span></label>
            @if($leave->attachments->isNotEmpty())
                <div class="mb-1.5 flex flex-wrap gap-2">
                    @foreach($leave->attachments as $att)
                        <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:underline">
                            <span class="material-symbols-outlined text-[14px]">attach_file</span> {{ $att->file_name ?: 'Lampiran saat ini' }}
                        </a>
                    @endforeach
                </div>
            @endif
            <input type="file" name="attachment" accept="image/*,.pdf" class="w-full text-[12px] text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-600 file:text-[12px] file:font-semibold file:cursor-pointer">
            <p class="text-[11px] text-gray-400 mt-1">{{ $leave->attachments->isNotEmpty() ? 'Unggah file baru untuk mengganti lampiran lama.' : 'Mis. surat dokter untuk izin sakit.' }} Format JPG/PNG/PDF, maks 5MB.</p>
            @error('attachment')<div class="text-red-600 text-[11px] mt-1">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[18px]">save</span>
            Simpan Perubahan
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
    });
</script>
@endsection
