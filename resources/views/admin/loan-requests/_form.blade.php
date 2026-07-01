@php
    $selectedEmployee = old('employee_id', $loanRequest->employee_id ?? '');
    $selectedStatus = old('status', $loanRequest->status ?? 'active');
    $hasExistingMonthlyInstallment = isset($loanRequest) && $loanRequest->monthly_installment !== null && $loanRequest->monthly_installment !== '';
    $selectedInstallmentMode = old('installment_mode', $hasExistingMonthlyInstallment ? 'manual' : 'auto');
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
        <div class="mb-2 grid grid-cols-2 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 p-1 text-[12px] font-semibold text-gray-600">
            <label class="cursor-pointer">
                <input type="radio" name="installment_mode" value="auto" class="peer sr-only" @checked($selectedInstallmentMode === 'auto') data-installment-mode>
                <span class="block rounded-md px-3 py-2 text-center peer-checked:bg-white peer-checked:text-indigo-700 peer-checked:shadow-sm">Otomatis</span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="installment_mode" value="manual" class="peer sr-only" @checked($selectedInstallmentMode === 'manual') data-installment-mode>
                <span class="block rounded-md px-3 py-2 text-center peer-checked:bg-white peer-checked:text-indigo-700 peer-checked:shadow-sm">Manual</span>
            </label>
        </div>
        <input type="number" name="monthly_installment" min="0" step="1" value="{{ $monthlyInstallmentValue }}" placeholder="Dihitung otomatis saat disimpan" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed" data-monthly-installment-input>
        <p class="text-[11px] text-gray-400 mt-1" data-auto-installment-preview>Dihitung otomatis saat disimpan.</p>
        @error('monthly_installment') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
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

    const syncMode = () => {
        const isManual = selectedMode() === 'manual';

        monthlyInput.disabled = !isManual;
        monthlyInput.required = isManual;
        monthlyInput.placeholder = isManual ? 'Masukkan cicilan manual' : 'Dihitung otomatis saat disimpan';

        if (isManual) {
            monthlyInput.value = manualValue;
        } else {
            manualValue = monthlyInput.value || manualValue;
            monthlyInput.value = '';
        }

        renderPreview();
    };

    monthlyInput?.addEventListener('input', () => {
        manualValue = monthlyInput.value;
    });
    modeInputs.forEach((input) => input.addEventListener('change', syncMode));
    [amountInput, interestInput, tenorInput].forEach((input) => input?.addEventListener('input', renderPreview));

    syncMode();
});
</script>
