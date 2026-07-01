@php
    $selectedEmployee = old('employee_id', $loanRequest->employee_id ?? '');
    $selectedStatus = old('status', $loanRequest->status ?? 'active');
    $hasExistingMonthlyInstallment = isset($loanRequest) && $loanRequest->monthly_installment !== null && $loanRequest->monthly_installment !== '';

    // Baris jadwal cicilan per bulan: dari old input (validasi gagal) atau dari data pinjaman.
    $oldSchedulePeriods = old('schedule_period');
    if (is_array($oldSchedulePeriods)) {
        $oldScheduleAmounts = old('schedule_amount', []);
        $scheduleRows = [];
        foreach ($oldSchedulePeriods as $i => $p) {
            $scheduleRows[] = ['period' => $p, 'amount' => $oldScheduleAmounts[$i] ?? ''];
        }
    } elseif (! empty($loanRequest->installment_schedule) && is_array($loanRequest->installment_schedule)) {
        $scheduleRows = [];
        foreach ($loanRequest->installment_schedule as $p => $a) {
            $scheduleRows[] = ['period' => $p, 'amount' => (int) $a];
        }
    } else {
        $scheduleRows = [];
    }
    $hasSchedule = count($scheduleRows) > 0;

    $selectedInstallmentMode = old('installment_mode', $hasSchedule ? 'scheduled' : ($hasExistingMonthlyInstallment ? 'manual' : 'auto'));
    $monthlyInstallmentValue = old('monthly_installment', $hasExistingMonthlyInstallment ? (int) $loanRequest->monthly_installment : '');
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4" data-loan-installment-form>
    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Karyawan</label>
        <select name="employee_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">Pilih karyawan</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" @selected((string) $selectedEmployee === (string) $employee->id)>
                    {{ $employee->full_name }} - {{ $employee->employee_code }}
                </option>
            @endforeach
        </select>
        @error('employee_id') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Status</label>
        <select name="status" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            @foreach(['active' => 'Aktif', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'] as $value => $label)
                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Nominal Pinjaman</label>
        <input type="number" name="amount" min="1" step="1" required value="{{ old('amount', isset($loanRequest) ? (int) $loanRequest->amount : '') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @error('amount') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Bunga (%)</label>
        <input type="number" name="interest_rate" min="0" max="100" step="0.01" value="{{ old('interest_rate', $loanRequest->interest_rate ?? 0) }}" placeholder="0" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        <p class="text-[11px] text-gray-400 mt-1">Kosongkan atau isi 0 untuk tanpa bunga.</p>
        @error('interest_rate') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Tenor</label>
        <input type="number" name="installment_count" min="1" max="1080" step="1" required value="{{ old('installment_count', $loanRequest->installment_count ?? '') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @error('installment_count') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Cicilan per Bulan</label>
        <div class="mb-2 grid grid-cols-3 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 p-1 text-[12px] font-semibold text-gray-600">
            <label class="cursor-pointer">
                <input type="radio" name="installment_mode" value="auto" class="peer sr-only" @checked($selectedInstallmentMode === 'auto') data-installment-mode>
                <span class="block rounded-md px-3 py-2 text-center peer-checked:bg-white peer-checked:text-indigo-700 peer-checked:shadow-sm">Otomatis</span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="installment_mode" value="manual" class="peer sr-only" @checked($selectedInstallmentMode === 'manual') data-installment-mode>
                <span class="block rounded-md px-3 py-2 text-center peer-checked:bg-white peer-checked:text-indigo-700 peer-checked:shadow-sm">Manual</span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="installment_mode" value="scheduled" class="peer sr-only" @checked($selectedInstallmentMode === 'scheduled') data-installment-mode>
                <span class="block rounded-md px-3 py-2 text-center peer-checked:bg-white peer-checked:text-indigo-700 peer-checked:shadow-sm">Terjadwal</span>
            </label>
        </div>
        <input type="number" name="monthly_installment" min="0" step="1" value="{{ $monthlyInstallmentValue }}" placeholder="Dihitung otomatis saat disimpan" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed" data-monthly-installment-input>
        <p class="text-[11px] text-gray-400 mt-1" data-auto-installment-preview>Dihitung otomatis saat disimpan.</p>
        @error('monthly_installment') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror

        {{-- Editor jadwal cicilan per bulan (mode Terjadwal) --}}
        <div data-schedule-editor class="mt-3 {{ $selectedInstallmentMode === 'scheduled' ? '' : 'hidden' }}">
            <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-3">
                <p class="text-[11px] text-gray-500 mb-2">Atur nominal untuk bulan tertentu. Bulan yang tidak diisi memakai cicilan default di atas.</p>
                <div data-schedule-rows class="space-y-2">
                    @foreach($scheduleRows as $row)
                        <div class="flex items-center gap-2" data-schedule-row>
                            <input type="month" name="schedule_period[]" value="{{ $row['period'] }}" class="w-1/2 px-2.5 py-2 border border-gray-300 rounded-lg text-[12px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <input type="number" name="schedule_amount[]" min="0" step="1" value="{{ $row['amount'] }}" placeholder="Nominal" class="w-1/2 px-2.5 py-2 border border-gray-300 rounded-lg text-[12px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" data-schedule-amount>
                            <button type="button" data-remove-row class="shrink-0 rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                            </button>
                        </div>
                    @endforeach
                </div>
                <button type="button" data-add-row class="mt-2 inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50 transition-colors">
                    <span class="material-symbols-outlined text-[14px]">add</span> Tambah Bulan
                </button>
                <p class="mt-2 text-[11px] font-semibold text-gray-600" data-schedule-total></p>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Periode Mulai Potong</label>
        <input type="month" name="start_period" value="{{ old('start_period', $loanRequest->start_period ?? '') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @error('start_period') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Catatan</label>
        <textarea name="purpose" rows="4" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('purpose', $loanRequest->purpose ?? '') }}</textarea>
        @error('purpose') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-loan-installment-form]');

    if (!form) {
        return;
    }

    const amountInput = form.querySelector('[name="amount"]');
    const interestInput = form.querySelector('[name="interest_rate"]');
    const tenorInput = form.querySelector('[name="installment_count"]');
    const monthlyInput = form.querySelector('[data-monthly-installment-input]');
    const modeInputs = form.querySelectorAll('[data-installment-mode]');
    const preview = form.querySelector('[data-auto-installment-preview]');
    const scheduleEditor = form.querySelector('[data-schedule-editor]');
    const scheduleRows = form.querySelector('[data-schedule-rows]');
    const scheduleTotal = form.querySelector('[data-schedule-total]');
    let manualValue = monthlyInput?.value || '';

    const rupiah = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    const selectedMode = () => Array.from(modeInputs).find((input) => input.checked)?.value || 'auto';

    const calculateMonthlyInstallment = () => {
        const amount = Number(amountInput?.value || 0);
        const interestRate = Number(interestInput?.value || 0);
        const tenor = Math.max(Number(tenorInput?.value || 0), 0);

        if (!amount || !tenor) {
            return null;
        }

        return Math.round((amount + (amount * (interestRate / 100))) / tenor);
    };

    const renderPreview = () => {
        const monthly = calculateMonthlyInstallment();
        preview.textContent = monthly === null
            ? 'Dihitung otomatis saat disimpan.'
            : `Otomatis: ${rupiah.format(monthly)} per bulan.`;
    };

    const renderScheduleTotal = () => {
        if (!scheduleTotal) return;
        const amounts = scheduleRows?.querySelectorAll('[data-schedule-amount]') || [];
        let total = 0;
        amounts.forEach((el) => { total += Number(el.value || 0); });
        scheduleTotal.textContent = total > 0 ? `Total jadwal: ${rupiah.format(total)}` : '';
    };

    const addScheduleRow = (period = '', amount = '') => {
        if (!scheduleRows) return;
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2';
        row.setAttribute('data-schedule-row', '');
        row.innerHTML = `
            <input type="month" name="schedule_period[]" value="${period}" class="w-1/2 px-2.5 py-2 border border-gray-300 rounded-lg text-[12px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <input type="number" name="schedule_amount[]" min="0" step="1" value="${amount}" placeholder="Nominal" class="w-1/2 px-2.5 py-2 border border-gray-300 rounded-lg text-[12px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" data-schedule-amount>
            <button type="button" data-remove-row class="shrink-0 rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>`;
        scheduleRows.appendChild(row);
        renderScheduleTotal();
    };

    const syncMode = () => {
        const mode = selectedMode();
        const isManual = mode === 'manual';
        const isScheduled = mode === 'scheduled';
        const allowsManualBase = isManual || isScheduled;

        monthlyInput.disabled = !allowsManualBase;
        monthlyInput.required = isManual; // scheduled: default boleh kosong (= otomatis)
        monthlyInput.placeholder = isManual
            ? 'Masukkan cicilan manual'
            : (isScheduled ? 'Cicilan default (kosongkan untuk otomatis)' : 'Dihitung otomatis saat disimpan');

        if (allowsManualBase) {
            monthlyInput.value = manualValue;
        } else {
            manualValue = monthlyInput.value || manualValue;
            monthlyInput.value = '';
        }

        scheduleEditor?.classList.toggle('hidden', !isScheduled);
        if (isScheduled && scheduleRows && scheduleRows.children.length === 0) {
            addScheduleRow();
        }

        renderPreview();
    };

    monthlyInput?.addEventListener('input', () => {
        manualValue = monthlyInput.value;
    });
    modeInputs.forEach((input) => input.addEventListener('change', syncMode));
    [amountInput, interestInput, tenorInput].forEach((input) => input?.addEventListener('input', renderPreview));

    form.querySelector('[data-add-row]')?.addEventListener('click', () => addScheduleRow());
    scheduleRows?.addEventListener('click', (e) => {
        const remove = e.target.closest('[data-remove-row]');
        if (!remove) return;
        remove.closest('[data-schedule-row]')?.remove();
        renderScheduleTotal();
    });
    scheduleRows?.addEventListener('input', (e) => {
        if (e.target.matches('[data-schedule-amount]')) renderScheduleTotal();
    });

    syncMode();
    renderScheduleTotal();
});
</script>
