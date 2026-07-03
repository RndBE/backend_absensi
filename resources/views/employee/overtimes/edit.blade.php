@extends('employee.layouts.app')
@section('title', 'Edit Pengajuan Lembur')

@section('content')
@php
    $isHoliday = old('overtime_type', $overtime->overtime_type) === 'holiday';
@endphp

<div class="max-w-2xl mx-auto space-y-4">
    <div>
        <a href="{{ route('employee.overtimes.show', $overtime->id) }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Detail Lembur
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Edit Pengajuan Lembur</h1>
        <p class="text-[13px] text-gray-500 mt-1">Pengajuan masih pending, jadi detailnya masih bisa disesuaikan.</p>
    </div>

    <form action="{{ route('employee.overtimes.update', $overtime->id) }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Tanggal</label>
                <input type="date" name="date" value="{{ old('date', $overtime->date?->format('Y-m-d')) }}" required class="employee-native-field h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Tipe Lembur</label>
                <select name="overtime_type" id="overtimeType" class="employee-native-field h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                    <option value="workday" @selected(! $isHoliday)>Hari Kerja</option>
                    <option value="holiday" @selected($isHoliday)>Hari Libur / Off</option>
                </select>
            </div>
        </div>

        <section id="workdayFields" class="rounded-xl border border-gray-100 bg-gray-50/60 p-4 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-[13px] font-black text-gray-900">Durasi Hari Kerja</h2>
                    <p class="text-[12px] text-gray-500 mt-0.5">Isi dalam menit untuk pre-shift dan post-shift.</p>
                </div>
                <span class="material-symbols-outlined text-[20px] text-indigo-500">schedule</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Lembur Pre-Shift</label>
                    <input type="number" name="pre_shift_duration" min="0" value="{{ old('pre_shift_duration', $overtime->pre_shift_duration ?? 0) }}" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Istirahat Pre-Shift</label>
                    <input type="number" name="pre_shift_break" min="0" value="{{ old('pre_shift_break', $overtime->pre_shift_break ?? 0) }}" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Lembur Post-Shift</label>
                    <input type="number" name="post_shift_duration" min="0" value="{{ old('post_shift_duration', $overtime->post_shift_duration ?? 0) }}" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Istirahat Post-Shift</label>
                    <input type="number" name="post_shift_break" min="0" value="{{ old('post_shift_break', $overtime->post_shift_break ?? 0) }}" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </div>
            </div>
        </section>

        <section id="holidayFields" class="rounded-xl border border-gray-100 bg-gray-50/60 p-4 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-[13px] font-black text-gray-900">Durasi Hari Libur</h2>
                    <p class="text-[12px] text-gray-500 mt-0.5">Isi jam mulai, jam selesai, dan istirahat.</p>
                </div>
                <span class="material-symbols-outlined text-[20px] text-indigo-500">event</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Mulai</label>
                    <input type="time" name="planned_start" value="{{ old('planned_start', $overtime->planned_start ? substr($overtime->planned_start, 0, 5) : null) }}" class="employee-native-field h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Selesai</label>
                    <input type="time" name="planned_end" value="{{ old('planned_end', $overtime->planned_end ? substr($overtime->planned_end, 0, 5) : null) }}" class="employee-native-field h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Istirahat</label>
                    <input type="number" name="break_duration" min="0" value="{{ old('break_duration', $overtime->break_duration ?? 0) }}" class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </div>
            </div>
        </section>

        <div>
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Alasan</label>
            <textarea name="reason" rows="4" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-[13px] text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 resize-none">{{ old('reason', $overtime->reason) }}</textarea>
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm">
            <span class="material-symbols-outlined text-[18px]">save</span>
            Simpan Perubahan
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
function syncOvertimeEditFields() {
    const isHoliday = document.getElementById('overtimeType')?.value === 'holiday';
    document.getElementById('workdayFields')?.classList.toggle('hidden', isHoliday);
    document.getElementById('holidayFields')?.classList.toggle('hidden', !isHoliday);
}

document.getElementById('overtimeType')?.addEventListener('change', syncOvertimeEditFields);
syncOvertimeEditFields();
</script>
@endpush
