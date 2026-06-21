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
        <div class="space-y-3 mb-6">
            @foreach($shifts as $shift)
            <div class="rounded-xl border border-gray-200 hover:shadow-sm transition-all overflow-hidden">
                {{-- Header row --}}
                <div class="flex items-center justify-between p-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-[12px] font-bold" style="background-color: {{ $shift->color }}">
                            {!! $shift->is_off ? '<span class="material-symbols-outlined text-[14px]">home</span>' : '<span class="material-symbols-outlined text-[14px]">schedule</span>' !!}
                        </div>
                        <div>
                            <div class="text-[14px] font-bold text-gray-900 flex items-center gap-2">
                                {{ $shift->name }}
                                @if($shift->is_overnight)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold">
                                        <span class="material-symbols-outlined text-[12px]">nights_stay</span> Ganti Hari
                                    </span>
                                @endif
                                @if($shift->auto_overtime && $shift->work_hours)
                                    @php
                                        $otMinutes = $shift->getOvertimeMinutes();
                                        $otHours = round($otMinutes / 60, 1);
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">
                                        <span class="material-symbols-outlined text-[12px]">alarm_add</span>
                                        Auto +{{ $otHours }}j lembur
                                    </span>
                                @endif
                            </div>
                            <div class="text-[12px] text-gray-400">
                                @if($shift->is_off)
                                    Libur / Off Day
                                @else
                                    {{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}{!! $shift->is_overnight ? ' <span class="text-blue-500 font-semibold">+1</span>' : '' !!}
                                    @if($shift->work_hours)
                                        · Std {{ $shift->work_hours }} jam
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Toggle edit form --}}
                        <button type="button" onclick="toggleEditForm('edit-{{ $shift->id }}')"
                            class="px-2 py-1.5 text-[10px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span>
                        </button>
                        <form action="{{ route('admin.shifts.destroy', $shift->id) }}" method="POST" data-confirm="Hapus shift {{ $shift->name }}?">
                            @csrf @method('DELETE')
                            <button class="px-2 py-1.5 text-[10px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                        </form>
                    </div>
                </div>

                {{-- Inline Edit Form (collapsible) --}}
                <div id="edit-{{ $shift->id }}" class="hidden border-t border-gray-100 bg-gray-50 px-4 py-3">
                    <form action="{{ route('admin.shifts.update', $shift->id) }}" method="POST" class="flex flex-wrap items-end gap-3">
                        @csrf @method('PUT')
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama</label>
                            <input type="text" name="name" value="{{ $shift->name }}" class="w-[110px] px-2 py-1.5 border border-gray-300 rounded-lg text-[11px] outline-none focus:border-indigo-500">
                        </div>
                        <div class="shift-time-fields-edit-{{ $shift->id }} {{ $shift->is_off ? 'opacity-40 pointer-events-none' : '' }}">
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Jam Masuk</label>
                            <input type="time" name="start_time" value="{{ $shift->start_time ? substr($shift->start_time, 0, 5) : '' }}"
                                class="w-[95px] px-2 py-1.5 border border-gray-300 rounded-lg text-[11px] outline-none focus:border-indigo-500">
                        </div>
                        <div class="shift-time-fields-edit-{{ $shift->id }} {{ $shift->is_off ? 'opacity-40 pointer-events-none' : '' }}">
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Jam Pulang</label>
                            <input type="time" name="end_time" value="{{ $shift->end_time ? substr($shift->end_time, 0, 5) : '' }}"
                                class="w-[95px] px-2 py-1.5 border border-gray-300 rounded-lg text-[11px] outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Warna</label>
                            <input type="color" name="color" value="{{ $shift->color }}" class="w-8 h-8 rounded cursor-pointer border-0">
                        </div>
                        <div>
                            <label class="flex items-center gap-1.5 text-[11px] font-medium text-gray-600 mb-1 cursor-pointer">
                                <input type="checkbox" name="is_off" value="1" {{ $shift->is_off ? 'checked' : '' }}
                                    class="accent-indigo-500" onchange="toggleOffEdit(this, {{ $shift->id }})"> Off Day
                            </label>
                        </div>
                        <div class="shift-time-fields-edit-{{ $shift->id }} {{ $shift->is_off ? 'opacity-40 pointer-events-none' : '' }}">
                            <label class="flex items-center gap-1.5 text-[11px] font-medium text-blue-700 mb-1 cursor-pointer"
                                title="Aktifkan jika shift berakhir di hari berikutnya (misal: 22:00 - 06:00)">
                                <input type="checkbox" name="is_overnight" value="1" {{ $shift->is_overnight ? 'checked' : '' }}
                                    class="accent-blue-500">
                                <span class="material-symbols-outlined text-[12px]">nights_stay</span> Ganti Hari
                            </label>
                        </div>

                        {{-- ─── Auto Overtime Section ─── --}}
                        <div class="shift-time-fields-edit-{{ $shift->id }} {{ $shift->is_off ? 'opacity-40 pointer-events-none' : '' }}">
                            <label class="flex items-center gap-1.5 text-[11px] font-medium text-amber-700 mb-1 cursor-pointer"
                                title="Aktifkan agar sistem otomatis membuat lembur approved untuk jam di luar jam kerja standar">
                                <input type="checkbox" name="auto_overtime" value="1" {{ $shift->auto_overtime ? 'checked' : '' }}
                                    id="auto_ot_edit_{{ $shift->id }}"
                                    class="accent-amber-500"
                                    onchange="toggleWorkHoursEdit(this, {{ $shift->id }})">
                                <span class="material-symbols-outlined text-[12px]">alarm_add</span> Auto Lembur
                            </label>
                        </div>
                        <div id="work_hours_edit_{{ $shift->id }}"
                            class="shift-time-fields-edit-{{ $shift->id }} {{ (!$shift->auto_overtime || $shift->is_off) ? 'hidden' : '' }}">
                            <label class="block text-[11px] font-semibold text-amber-700 mb-1">Jam Kerja Standar</label>
                            <div class="flex items-center gap-1">
                                <input type="number" name="work_hours" value="{{ $shift->work_hours ?? 8 }}" min="1" max="23"
                                    id="work_hours_input_edit_{{ $shift->id }}"
                                    class="w-[60px] px-2 py-1.5 border border-amber-300 rounded-lg text-[11px] outline-none focus:border-amber-500 text-center"
                                    oninput="updateOTPreviewEdit({{ $shift->id }})">
                                <span class="text-[11px] text-gray-500">jam</span>
                            </div>
                            <div id="ot_preview_edit_{{ $shift->id }}" class="text-[10px] text-amber-600 mt-0.5 font-medium"></div>
                        </div>

                        <button type="submit" class="px-3 py-1.5 text-[11px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[12px] align-text-bottom">save</span> Simpan
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Add New --}}
        <div class="p-4 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
            <h4 class="text-[13px] font-bold text-gray-700 mb-3"><span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Tambah Shift Baru</h4>
            <form action="{{ route('admin.shifts.store') }}" method="POST" id="add-shift-form">
                @csrf
                <div class="flex items-end gap-3 flex-wrap">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama</label>
                        <input type="text" name="name" class="w-[120px] px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" placeholder="Security Malam" required>
                    </div>
                    <div id="add-time-fields">
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Jam Masuk</label>
                        <input type="time" name="start_time" class="w-[110px] px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" value="08:00" id="add-start-time">
                    </div>
                    <div id="add-time-fields-end">
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Jam Pulang</label>
                        <input type="time" name="end_time" class="w-[110px] px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" value="17:00" id="add-end-time">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Warna</label>
                        <input type="color" name="color" value="#3B82F6" class="w-10 h-10 rounded cursor-pointer border-0">
                    </div>
                    <div>
                        <label class="flex items-center gap-1.5 text-[12px] font-medium text-gray-600 mb-1 cursor-pointer">
                            <input type="checkbox" name="is_off" value="1" class="accent-indigo-500" id="add-is-off" onchange="toggleAddOff()"> Hari Libur
                        </label>
                    </div>
                    <div id="add-overnight-section">
                        <label class="flex items-center gap-1.5 text-[12px] font-medium text-blue-700 mb-1 cursor-pointer"
                            title="Aktifkan jika shift berakhir di hari berikutnya (misal: 22:00 - 06:00)">
                            <input type="checkbox" name="is_overnight" value="1" id="add-is-overnight" class="accent-blue-500">
                            <span class="material-symbols-outlined text-[13px]">nights_stay</span> Ganti Hari
                        </label>
                    </div>

                    {{-- ─── Auto Overtime (Add Form) ─── --}}
                    <div id="add-auto-ot-section">
                        <label class="flex items-center gap-1.5 text-[12px] font-medium text-amber-700 mb-1 cursor-pointer"
                            title="Aktifkan agar sistem otomatis generate lembur approved untuk jam di luar jam kerja standar">
                            <input type="checkbox" name="auto_overtime" value="1" id="add-auto-ot" class="accent-amber-500" onchange="toggleAddWorkHours()">
                            <span class="material-symbols-outlined text-[13px]">alarm_add</span> Auto Lembur
                        </label>
                    </div>
                    <div id="add-work-hours-section" class="hidden">
                        <label class="block text-[11px] font-semibold text-amber-700 mb-1">Jam Kerja Standar</label>
                        <div class="flex items-center gap-1.5">
                            <input type="number" name="work_hours" id="add-work-hours" value="8" min="1" max="23"
                                class="w-[65px] px-2 py-2 border border-amber-300 rounded-lg text-[13px] outline-none focus:border-amber-500 text-center"
                                oninput="updateAddOTPreview()">
                            <span class="text-[12px] text-gray-500">jam</span>
                        </div>
                        <div id="add-ot-preview" class="text-[11px] text-amber-600 mt-0.5 font-semibold"></div>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-1 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">＋ Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ─────────────────────────────────────────────
//  Edit Form helpers
// ─────────────────────────────────────────────
function toggleEditForm(id) {
    const el = document.getElementById(id);
    el.classList.toggle('hidden');
}

function toggleOffEdit(cb, shiftId) {
    const timeFields = document.querySelectorAll('.shift-time-fields-edit-' + shiftId);
    timeFields.forEach(el => {
        if (cb.checked) {
            el.classList.add('opacity-40', 'pointer-events-none');
        } else {
            el.classList.remove('opacity-40', 'pointer-events-none');
        }
    });
}

function toggleWorkHoursEdit(cb, shiftId) {
    const section = document.getElementById('work_hours_edit_' + shiftId);
    if (cb.checked) {
        section.classList.remove('hidden');
        updateOTPreviewEdit(shiftId);
    } else {
        section.classList.add('hidden');
    }
}

function updateOTPreviewEdit(shiftId) {
    // We don't easily have start/end time here without extra effort,
    // so just show a placeholder formula note
    const wh = document.getElementById('work_hours_input_edit_' + shiftId);
    const preview = document.getElementById('ot_preview_edit_' + shiftId);
    if (!wh || !preview) return;
    preview.textContent = `Std ${wh.value} jam → sisa jam = lembur otomatis`;
}

// ─────────────────────────────────────────────
//  Add Form helpers
// ─────────────────────────────────────────────
function toggleAddOff() {
    const isOff = document.getElementById('add-is-off').checked;
    ['add-time-fields', 'add-time-fields-end', 'add-overnight-section', 'add-auto-ot-section', 'add-work-hours-section'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (isOff) {
            el.classList.add('opacity-40', 'pointer-events-none');
        } else {
            el.classList.remove('opacity-40', 'pointer-events-none');
        }
    });
    if (isOff) {
        document.getElementById('add-work-hours-section').classList.add('hidden');
        const overnight = document.getElementById('add-is-overnight');
        if (overnight) overnight.checked = false;
    }
}

function toggleAddWorkHours() {
    const autoOt = document.getElementById('add-auto-ot').checked;
    const section = document.getElementById('add-work-hours-section');
    if (autoOt) {
        section.classList.remove('hidden');
        updateAddOTPreview();
    } else {
        section.classList.add('hidden');
    }
}

function updateAddOTPreview() {
    const startEl  = document.getElementById('add-start-time');
    const endEl    = document.getElementById('add-end-time');
    const whEl     = document.getElementById('add-work-hours');
    const preview  = document.getElementById('add-ot-preview');
    if (!startEl || !endEl || !whEl || !preview) return;

    const [sh, sm] = startEl.value.split(':').map(Number);
    const [eh, em] = endEl.value.split(':').map(Number);

    if (isNaN(sh) || isNaN(eh)) { preview.textContent = ''; return; }

    let startMin = sh * 60 + sm;
    let endMin   = eh * 60 + em;
    if (endMin <= startMin) endMin += 24 * 60; // overnight

    const totalMins    = endMin - startMin;
    const standardMins = Number(whEl.value) * 60;
    const otMins       = Math.max(0, totalMins - standardMins);
    const otHours      = Math.round(otMins / 60 * 10) / 10;
    const totalHours   = Math.round(totalMins / 60 * 10) / 10;

    if (otMins > 0) {
        preview.textContent = `Shift ${totalHours} jam → otomatis lembur ${otHours} jam/hari ✓`;
        preview.className = 'text-[11px] text-amber-600 mt-0.5 font-semibold';
    } else {
        preview.textContent = 'Jam kerja standar ≥ durasi shift, tidak ada lembur otomatis.';
        preview.className = 'text-[11px] text-red-500 mt-0.5 font-semibold';
    }
}

// Re-calculate preview when time changes
document.addEventListener('DOMContentLoaded', function () {
    ['add-start-time', 'add-end-time'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', updateAddOTPreview);
    });
});
</script>
@endsection
