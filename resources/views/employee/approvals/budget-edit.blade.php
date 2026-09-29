@extends('employee.layouts.app')
@section('title', 'Sesuaikan Item Anggaran')

@section('content')
@php
    $formItems = collect(old('items', $budgetRequest->items->sortBy('id')->map(fn ($item) => [
        'id' => $item->id,
        'type' => $item->type,
        'description' => $item->description,
        'amount' => $item->amount,
    ])->values()->all()));
    $attachmentsById = $budgetRequest->items->mapWithKeys(fn ($item) => [$item->id => $item->attachments]);

    // Bantuan hitung tunjangan: uang makan zona × jumlah hari perjalanan.
    $zone = $budgetRequest->travelZone;
    $tripDays = $budgetRequest->departure_date && $budgetRequest->return_date
        ? $budgetRequest->departure_date->diffInDays($budgetRequest->return_date) + 1
        : null;
@endphp

<div class="space-y-4">
    <div>
        <a href="{{ route('employee.approvals.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Persetujuan Tim
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Sesuaikan Item Anggaran</h1>
        <p class="text-[13px] text-gray-500 mt-1">Perubahan dicatat atas nama Anda dan dikirim ke pengaju. Setelah disimpan, lanjutkan dengan menyetujui atau menolak.</p>
    </div>

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Pengaju</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">{{ $budgetRequest->employee?->full_name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Judul</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">{{ $budgetRequest->title }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Tanggal Perjalanan</div>
            <div class="mt-1 text-[13px] text-gray-700">
                @if($budgetRequest->departure_date)
                    {{ $budgetRequest->departure_date->format('d/m/Y') }} - {{ $budgetRequest->return_date?->format('d/m/Y') ?? '-' }}
                    @if($tripDays) · {{ $tripDays }} hari @endif
                @else
                    -
                @endif
            </div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Jarak / Zona</div>
            <div class="mt-1 text-[13px] text-gray-700">
                {{ $budgetRequest->distance_km ? $budgetRequest->distance_km.' km' : '-' }}
                @if($zone) · {{ $zone->name }} @endif
            </div>
            @if($zone && (float) $zone->meal_allowance > 0)
                <div class="mt-1 text-[11.5px] text-indigo-600">
                    Uang makan zona Rp {{ number_format((float) $zone->meal_allowance, 0, ',', '.') }}/hari
                    @if($tripDays) × {{ $tripDays }} hari = Rp {{ number_format((float) $zone->meal_allowance * $tripDays, 0, ',', '.') }} @endif
                </div>
            @endif
        </div>
    </section>

    @include('budget-requests.partials.revisions', ['revisions' => $budgetRequest->revisions])

    <form method="POST" action="{{ route('employee.approvals.budget.update', $budgetRequest->id) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-black text-gray-900">Item Biaya</h2>
                <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-gray-900 px-3 py-2 text-[12px] font-bold text-white" data-add-budget-item>
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Tambah Item
                </button>
            </div>

            <div class="space-y-3" id="budgetItems" data-next-index="{{ $formItems->keys()->map(fn ($key) => (int) $key)->max() + 1 }}">
                @foreach($formItems as $index => $item)
                    @include('employee.approvals.partials.budget-item-row', [
                        'index' => $index,
                        'itemTypes' => $itemTypes,
                        'item' => $item,
                        'attachments' => $attachmentsById->get((int) ($item['id'] ?? 0), collect()),
                    ])
                @endforeach
            </div>

            <div class="flex items-center justify-between border-t border-gray-100 pt-3 text-[13px]">
                <span class="font-bold text-gray-500">Total</span>
                <span class="font-black text-gray-900" data-budget-total>Rp {{ number_format((float) $budgetRequest->total_amount, 0, ',', '.') }}</span>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Catatan perubahan <span class="font-normal text-gray-400">(opsional, ikut dikirim ke pengaju)</span></span>
                <textarea name="notes" rows="2" maxlength="1000" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Tunjangan luar kota ditambahkan sesuai zona">{{ old('notes') }}</textarea>
            </label>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employee.approvals.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-[12px] font-bold text-gray-700">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-[12px] font-bold text-white">Simpan Perubahan</button>
        </div>
    </form>
</div>

<template id="budgetItemTemplate">
    @include('employee.approvals.partials.budget-item-row', ['index' => '__INDEX__', 'itemTypes' => $itemTypes, 'item' => null])
</template>
@endsection

@push('scripts')
<script>
    (function () {
        const list = document.getElementById('budgetItems');
        const template = document.getElementById('budgetItemTemplate');
        const totalEl = document.querySelector('[data-budget-total]');
        let nextIndex = list ? parseInt(list.dataset.nextIndex, 10) || 0 : 0;

        function refreshTotal() {
            if (!list || !totalEl) return;
            let total = 0;
            list.querySelectorAll('input[name$="[amount]"]').forEach(function (input) {
                total += parseFloat(input.value) || 0;
            });
            totalEl.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
        }

        document.querySelector('[data-add-budget-item]')?.addEventListener('click', function () {
            if (!list || !template) return;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
            const row = wrapper.firstElementChild;
            if (row) list.append(row);
        });

        list?.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-budget-item]');
            if (!button) return;
            if (list.querySelectorAll('[data-budget-item]').length <= 1) return;
            button.closest('[data-budget-item]').remove();
            refreshTotal();
        });

        list?.addEventListener('input', refreshTotal);
    })();
</script>
@endpush
