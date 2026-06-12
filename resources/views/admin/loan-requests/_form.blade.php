@php
    $selectedEmployee = old('employee_id', $loanRequest->employee_id ?? '');
    $selectedStatus = old('status', $loanRequest->status ?? 'active');
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
        <input type="number" name="installment_count" min="1" max="120" step="1" required value="{{ old('installment_count', $loanRequest->installment_count ?? '') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @error('installment_count') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Cicilan per Bulan</label>
        <input type="number" name="monthly_installment" min="0" step="1" value="{{ old('monthly_installment', isset($loanRequest) ? (int) $loanRequest->monthly_installment : '') }}" placeholder="Kosongkan untuk hitung otomatis" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @error('monthly_installment') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="block text-[12px] font-semibold text-gray-700 mb-1.5">Sisa Pinjaman</label>
        <input type="number" name="remaining_amount" min="0" step="1" value="{{ old('remaining_amount', isset($loanRequest) ? (int) $loanRequest->remaining_amount : '') }}" placeholder="Kosongkan untuk sama dengan nominal" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        @error('remaining_amount') <div class="text-[11px] text-red-600 mt-1">{{ $message }}</div> @enderror
    </div> --}}

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
