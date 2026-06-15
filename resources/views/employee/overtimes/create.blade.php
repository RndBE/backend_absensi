@extends('employee.layouts.app')
@section('title', 'Ajukan Lembur')

@section('content')
@php
    $durationValue = function (string $field, int $default = 0): string {
        $value = old($field, $default);

        if (is_numeric($value)) {
            return sprintf('%02d:%02d', intdiv((int) $value, 60), (int) $value % 60);
        }

        return $value ?: '00:00';
    };
@endphp

<div class="max-w-2xl mx-auto space-y-4">
    <div>
        <a href="{{ route('employee.overtimes.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Pengajuan Lembur
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Ajukan Lembur</h1>
        <p class="text-[13px] text-gray-500 mt-1">Pilih tipe lembur dan isi durasi.</p>
    </div>

    <form action="{{ route('employee.overtimes.store') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Tanggal</label>
                <input type="date" name="date" value="{{ old('date') }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Tipe Lembur</label>
                <select name="overtime_type" id="overtimeType" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500" onchange="toggleOvertimeType()">
                    <option value="workday" @selected(old('overtime_type') !== 'holiday')>Hari Kerja</option>
                    <option value="holiday" @selected(old('overtime_type') === 'holiday')>Hari Libur / Off</option>
                </select>
            </div>
        </div>

        <div id="workdayFields" class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-[13px] font-black text-gray-900">Durasi Lembur Hari Kerja</div>
                    <div class="text-[12px] text-gray-500 mt-0.5">Isi durasi sebelum atau setelah shift.</div>
                </div>
                <span class="material-symbols-outlined text-[20px] text-indigo-500">schedule</span>
            </div>

            <div class="space-y-3">
                <section data-overtime-section="before-shift" class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-emerald-600">first_page</span>
                        <h2 class="text-[13px] font-black text-gray-900">Sebelum Shift</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Jam Lembur</label>
                            <input type="time" name="pre_shift_duration" value="{{ $durationValue('pre_shift_duration') }}" step="60" class="w-full px-3 py-3 text-[15px] border border-gray-300 rounded-lg bg-white outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Waktu Istirahat</label>
                            <input type="time" name="pre_shift_break" value="{{ $durationValue('pre_shift_break') }}" step="60" class="w-full px-3 py-3 text-[15px] border border-gray-300 rounded-lg bg-white outline-none focus:border-indigo-500">
                        </div>
                    </div>
                </section>

                <section data-overtime-section="after-shift" class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-indigo-600">last_page</span>
                        <h2 class="text-[13px] font-black text-gray-900">Setelah Shift</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Jam Lembur</label>
                            <input type="time" name="post_shift_duration" value="{{ $durationValue('post_shift_duration') }}" step="60" class="w-full px-3 py-3 text-[15px] border border-gray-300 rounded-lg bg-white outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Waktu Istirahat</label>
                            <input type="time" name="post_shift_break" value="{{ $durationValue('post_shift_break') }}" step="60" class="w-full px-3 py-3 text-[15px] border border-gray-300 rounded-lg bg-white outline-none focus:border-indigo-500">
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div id="holidayFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Mulai</label>
                <input type="time" name="planned_start" value="{{ old('planned_start') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Selesai</label>
                <input type="time" name="planned_end" value="{{ old('planned_end') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Istirahat</label>
                <input type="time" name="break_duration" value="{{ $durationValue('break_duration') }}" step="60" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Alasan</label>
            <textarea name="reason" rows="4" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 resize-none" placeholder="Tuliskan alasan lembur">{{ old('reason') }}</textarea>
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[18px]">send</span>
            Kirim Pengajuan
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
function toggleOvertimeType() {
    const isHoliday = document.getElementById('overtimeType').value === 'holiday';
    document.getElementById('workdayFields').classList.toggle('hidden', isHoliday);
    document.getElementById('holidayFields').classList.toggle('hidden', !isHoliday);
}
toggleOvertimeType();
</script>
@endpush
