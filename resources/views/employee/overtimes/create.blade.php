@extends('employee.layouts.app')
@section('title', 'Ajukan Lembur')

@push('head')
<style>
    .overtime-stepper-row {
        display: grid;
        grid-template-columns: 18px minmax(78px, 1fr) 8px minmax(78px, 1fr) 46px;
        align-items: center;
        gap: 6px;
        min-height: 48px;
    }

    .overtime-stepper-group {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-width: 0;
    }

    .overtime-stepper-button {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #0f172a;
        font-size: 14px;
        font-weight: 900;
        line-height: 1;
    }

    .overtime-stepper-value {
        width: 28px;
        background: transparent;
        text-align: center;
        font-size: 14px;
        font-weight: 900;
        color: #0f172a;
        outline: none;
        -moz-appearance: textfield;
    }

    .overtime-stepper-value::-webkit-outer-spin-button,
    .overtime-stepper-value::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .overtime-stepper-label {
        margin-top: 1px;
        color: #64748b;
        font-size: 9px;
        line-height: 1;
    }

    .overtime-mobile-native-field,
    .overtime-mobile-select-field {
        -webkit-appearance: none;
        appearance: none;
        background-color: #ffffff;
        color: #0f172a;
        color-scheme: light;
    }

    .overtime-mobile-native-field::-webkit-date-and-time-value {
        text-align: left;
    }

    .overtime-mobile-native-field::-webkit-calendar-picker-indicator {
        opacity: 0.75;
    }

    @media (max-width: 380px) {
        .overtime-stepper-row {
            grid-template-columns: 16px minmax(66px, 1fr) 6px minmax(66px, 1fr) 40px;
            gap: 4px;
        }

        .overtime-stepper-button {
            width: 22px;
            height: 22px;
        }

        .overtime-stepper-value {
            width: 24px;
        }
    }
</style>
@endpush

@section('content')
@php
    $durationMinutes = function (string $field): int {
        $value = old($field, 0);

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return 0;
    };

    $durationParts = function (string $field) use ($durationMinutes): array {
        $total = $durationMinutes($field);

        return [
            'total' => $total,
            'hours' => intdiv($total, 60),
            'minutes' => $total % 60,
        ];
    };

    $preShiftDuration = $durationParts('pre_shift_duration');
    $preShiftBreak = $durationParts('pre_shift_break');
    $postShiftDuration = $durationParts('post_shift_duration');
    $postShiftBreak = $durationParts('post_shift_break');
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
                <input type="date" name="date" value="{{ old('date') }}" required class="overtime-mobile-native-field h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Tipe Lembur</label>
                <div class="overtime-select-wrapper relative">
                    <select name="overtime_type" id="overtimeType" class="overtime-mobile-select-field h-10 w-full rounded-lg border border-gray-200 bg-white px-3 pr-9 text-[13px] font-semibold text-gray-900 shadow-sm outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" onchange="toggleOvertimeType()">
                        <option value="workday" @selected(old('overtime_type') !== 'holiday')>Hari Kerja</option>
                        <option value="holiday" @selected(old('overtime_type') === 'holiday')>Hari Libur / Off</option>
                    </select>
                    <span class="material-symbols-outlined pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[16px] text-gray-400">expand_more</span>
                </div>
            </div>
        </div>

        <div id="workdayFields" class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-[13px] font-black text-gray-900">Durasi Lembur Hari Kerja</div>
                    <div class="text-[12px] text-gray-500 mt-0.5">Atur jam dan menit dengan tombol plus-minus.</div>
                </div>
                <span class="material-symbols-outlined text-[20px] text-indigo-500">schedule</span>
            </div>

            <div class="space-y-3">
                <section data-overtime-section="before-shift" class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3 space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="material-symbols-outlined text-[18px] text-emerald-600">arrow_upward</span>
                            <h2 class="text-[13px] font-black text-gray-900 truncate">Lembur Pre-Shift</h2>
                        </div>
                        <span class="text-[11px] font-semibold text-gray-500 whitespace-nowrap">Sebelum Shift</span>
                    </div>

                    <div class="space-y-2">
                        <div data-duration-control class="rounded-lg border border-gray-200 bg-white px-2.5 py-2">
                            <input type="hidden" name="pre_shift_duration" value="{{ $preShiftDuration['total'] }}" data-duration-total>
                            <div class="overtime-stepper-row">
                                <span class="material-symbols-outlined text-[16px] text-gray-500">timer</span>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="pre_shift_duration_hours" data-step="-1" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="pre_shift_duration_hours" type="number" min="0" value="{{ $preShiftDuration['hours'] }}" data-duration-hours class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Jam</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="pre_shift_duration_hours" data-step="1" class="overtime-stepper-button">+</button>
                                </div>
                                <div class="text-center text-[12px] font-black text-gray-400">-</div>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="pre_shift_duration_minutes" data-step="-5" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="pre_shift_duration_minutes" type="number" min="0" max="59" value="{{ $preShiftDuration['minutes'] }}" data-duration-minutes class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Menit</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="pre_shift_duration_minutes" data-step="5" class="overtime-stepper-button">+</button>
                                </div>
                                <div data-duration-summary class="text-right text-[11px] font-black text-gray-900">{{ $preShiftDuration['hours'] }}j {{ $preShiftDuration['minutes'] }}m</div>
                            </div>
                        </div>

                        <div data-duration-control class="rounded-lg border border-amber-100 bg-white px-2.5 py-2">
                            <input type="hidden" name="pre_shift_break" value="{{ $preShiftBreak['total'] }}" data-duration-total>
                            <div class="overtime-stepper-row">
                                <span class="material-symbols-outlined text-[16px] text-gray-500">free_breakfast</span>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="pre_shift_break_hours" data-step="-1" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="pre_shift_break_hours" type="number" min="0" value="{{ $preShiftBreak['hours'] }}" data-duration-hours class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Jam</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="pre_shift_break_hours" data-step="1" class="overtime-stepper-button">+</button>
                                </div>
                                <div class="text-center text-[12px] font-black text-gray-400">-</div>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="pre_shift_break_minutes" data-step="-5" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="pre_shift_break_minutes" type="number" min="0" max="59" value="{{ $preShiftBreak['minutes'] }}" data-duration-minutes class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Menit</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="pre_shift_break_minutes" data-step="5" class="overtime-stepper-button">+</button>
                                </div>
                                <div data-duration-summary class="text-right text-[11px] font-black text-amber-600">{{ $preShiftBreak['hours'] }}j {{ $preShiftBreak['minutes'] }}m</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section data-overtime-section="after-shift" class="rounded-xl border border-rose-100 bg-rose-50/40 p-3 space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="material-symbols-outlined text-[18px] text-rose-600">arrow_downward</span>
                            <h2 class="text-[13px] font-black text-gray-900 truncate">Lembur Post-Shift</h2>
                        </div>
                        <span class="text-[11px] font-semibold text-gray-500 whitespace-nowrap">Setelah Shift</span>
                    </div>

                    <div class="space-y-2">
                        <div data-duration-control class="rounded-lg border border-gray-200 bg-white px-2.5 py-2">
                            <input type="hidden" name="post_shift_duration" value="{{ $postShiftDuration['total'] }}" data-duration-total>
                            <div class="overtime-stepper-row">
                                <span class="material-symbols-outlined text-[16px] text-gray-500">timer</span>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="post_shift_duration_hours" data-step="-1" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="post_shift_duration_hours" type="number" min="0" value="{{ $postShiftDuration['hours'] }}" data-duration-hours class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Jam</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="post_shift_duration_hours" data-step="1" class="overtime-stepper-button">+</button>
                                </div>
                                <div class="text-center text-[12px] font-black text-gray-400">-</div>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="post_shift_duration_minutes" data-step="-5" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="post_shift_duration_minutes" type="number" min="0" max="59" value="{{ $postShiftDuration['minutes'] }}" data-duration-minutes class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Menit</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="post_shift_duration_minutes" data-step="5" class="overtime-stepper-button">+</button>
                                </div>
                                <div data-duration-summary class="text-right text-[11px] font-black text-gray-900">{{ $postShiftDuration['hours'] }}j {{ $postShiftDuration['minutes'] }}m</div>
                            </div>
                        </div>

                        <div data-duration-control class="rounded-lg border border-amber-100 bg-white px-2.5 py-2">
                            <input type="hidden" name="post_shift_break" value="{{ $postShiftBreak['total'] }}" data-duration-total>
                            <div class="overtime-stepper-row">
                                <span class="material-symbols-outlined text-[16px] text-gray-500">free_breakfast</span>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="post_shift_break_hours" data-step="-1" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="post_shift_break_hours" type="number" min="0" value="{{ $postShiftBreak['hours'] }}" data-duration-hours class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Jam</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="post_shift_break_hours" data-step="1" class="overtime-stepper-button">+</button>
                                </div>
                                <div class="text-center text-[12px] font-black text-gray-400">-</div>
                                <div class="overtime-stepper-group">
                                    <button type="button" data-step-action data-step-target="post_shift_break_minutes" data-step="-5" class="overtime-stepper-button">-</button>
                                    <div class="text-center">
                                        <input id="post_shift_break_minutes" type="number" min="0" max="59" value="{{ $postShiftBreak['minutes'] }}" data-duration-minutes class="overtime-stepper-value">
                                        <div class="overtime-stepper-label">Menit</div>
                                    </div>
                                    <button type="button" data-step-action data-step-target="post_shift_break_minutes" data-step="5" class="overtime-stepper-button">+</button>
                                </div>
                                <div data-duration-summary class="text-right text-[11px] font-black text-amber-600">{{ $postShiftBreak['hours'] }}j {{ $postShiftBreak['minutes'] }}m</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div id="holidayFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3"
             data-attendance-times-url="{{ route('employee.overtimes.attendance-times') }}">
            <p class="col-span-1 sm:col-span-3 text-[11px] text-gray-400 flex items-center gap-1 -mb-1">
                <span class="material-symbols-outlined text-[14px]">info</span>
                Jam terisi otomatis dari clock-in/out Anda di tanggal tersebut. Bisa diubah manual.
            </p>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Mulai</label>
                <input type="time" name="planned_start" value="{{ old('planned_start') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Selesai</label>
                <input type="time" name="planned_end" value="{{ old('planned_end') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Istirahat (menit)</label>
                <input type="number" name="break_duration" value="{{ old('break_duration', 0) }}" min="0" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
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
function normalizeDurationInput(input, min, max = null) {
    let value = parseInt(input.value || '0', 10);
    value = Number.isNaN(value) ? 0 : Math.max(min, value);
    if (max !== null) {
        value = Math.min(max, value);
    }
    input.value = value;
    return value;
}

function syncDurationControl(control) {
    const hoursInput = control.querySelector('[data-duration-hours]');
    const minutesInput = control.querySelector('[data-duration-minutes]');
    const totalInput = control.querySelector('[data-duration-total]');
    const summary = control.querySelector('[data-duration-summary]');
    const hours = normalizeDurationInput(hoursInput, 0);
    const minutes = normalizeDurationInput(minutesInput, 0, 59);

    totalInput.value = (hours * 60) + minutes;
    summary.textContent = `${hours}j ${minutes}m`;
}

document.querySelectorAll('[data-step-action]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.stepTarget);
        if (!input) {
            return;
        }

        const nextValue = parseInt(input.value || '0', 10) + parseInt(button.dataset.step || '0', 10);
        input.value = Number.isNaN(nextValue) ? 0 : nextValue;
        syncDurationControl(input.closest('[data-duration-control]'));
    });
});

document.querySelectorAll('[data-duration-control]').forEach((control) => {
    control.querySelectorAll('[data-duration-hours], [data-duration-minutes]').forEach((input) => {
        input.addEventListener('input', () => syncDurationControl(control));
    });
    syncDurationControl(control);
});

const holidayFields = document.getElementById('holidayFields');
const dateInput = document.querySelector('input[name="date"]');
const startInput = document.querySelector('input[name="planned_start"]');
const endInput = document.querySelector('input[name="planned_end"]');
const attendanceTimesUrl = holidayFields?.dataset.attendanceTimesUrl;

async function prefillHolidayTimes({ force = false } = {}) {
    const isHoliday = document.getElementById('overtimeType').value === 'holiday';
    if (!isHoliday || !attendanceTimesUrl || !dateInput?.value) return;
    // Jangan timpa input yang sudah diisi manual, kecuali tanggal baru diganti.
    if (!force && (startInput?.value || endInput?.value)) return;

    try {
        const resp = await fetch(`${attendanceTimesUrl}?date=${encodeURIComponent(dateInput.value)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) return;
        const data = await resp.json();
        if (startInput && data.clock_in) startInput.value = data.clock_in;
        if (endInput && data.clock_out) endInput.value = data.clock_out;
    } catch (e) {
        // Abaikan — biarkan diisi manual.
    }
}

function toggleOvertimeType() {
    const isHoliday = document.getElementById('overtimeType').value === 'holiday';
    document.getElementById('workdayFields').classList.toggle('hidden', isHoliday);
    holidayFields.classList.toggle('hidden', !isHoliday);
    if (isHoliday) prefillHolidayTimes();
}

dateInput?.addEventListener('change', () => prefillHolidayTimes({ force: true }));
toggleOvertimeType();
</script>
@endpush
