@extends('employee.layouts.app')
@section('title', 'Edit Pengajuan Anggaran')

@section('content')
@php
    $formItems = collect(old('items', $budgetRequest->items->map(fn ($item) => [
        'type' => $item->type,
        'description' => $item->description,
        'amount' => $item->amount,
    ])->all()));
    if ($formItems->isEmpty()) {
        $formItems = collect([['type' => 'transport', 'description' => '', 'amount' => 0]]);
    }
@endphp

<div class="space-y-4">
    <div>
        <a href="{{ route('employee.budget-requests.show', $budgetRequest->id) }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Detail Anggaran
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Edit Pengajuan Anggaran</h1>
        <p class="text-[13px] text-gray-500 mt-1">Perubahan hanya bisa dilakukan saat pengajuan masih pending.</p>
    </div>

    <form method="POST" action="{{ route('employee.budget-requests.update', $budgetRequest->id) }}" enctype="multipart/form-data" class="space-y-4" id="budgetRequestForm">
        @csrf
        @method('PUT')

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Tipe</span>
                    <select name="type" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <option value="budget" @selected(old('type', $budgetRequest->type) === 'budget')>Budget</option>
                        <option value="reimbursement" @selected(old('type', $budgetRequest->type) === 'reimbursement')>Reimbursement</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Judul</span>
                    <input name="title" value="{{ old('title', $budgetRequest->title) }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Perjalanan Batam">
                </label>
            </div>

            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Deskripsi</span>
                <textarea name="description" rows="3" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Keterangan pengajuan">{{ old('description', $budgetRequest->description) }}</textarea>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">No. Surat Tugas</span>
                    <input name="surat_tugas_no" value="{{ old('surat_tugas_no', $budgetRequest->surat_tugas_no) }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Tanggal Surat</span>
                    <span class="employee-date-shell" data-employee-date-shell>
                        <input type="date" name="surat_tugas_date" value="{{ old('surat_tugas_date', $budgetRequest->surat_tugas_date?->format('Y-m-d')) }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <span class="employee-date-placeholder" data-date-placeholder>mm/dd/yyyy</span>
                    </span>
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Jarak KM</span>
                    <input type="number" min="0" name="distance_km" value="{{ old('distance_km', $budgetRequest->distance_km) }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="0">
                </label>
            </div>

            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Tambah Lampiran Utama</span>
                <input type="file" name="attachments[]" multiple class="block w-full text-[13px] text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-[12px] file:font-bold file:text-indigo-700">
            </label>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-black text-gray-900">Item Biaya</h2>
                <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-gray-900 px-3 py-2 text-[12px] font-bold text-white" data-add-budget-item>
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Tambah Item
                </button>
            </div>

            <div class="space-y-3" id="budgetItems">
                @foreach($formItems as $index => $item)
                    @include('employee.budget-requests.partials.item-row', ['index' => $index, 'itemTypes' => $itemTypes, 'item' => $item])
                @endforeach
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employee.budget-requests.show', $budgetRequest->id) }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-[12px] font-bold text-gray-700">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-[12px] font-bold text-white">Simpan Perubahan</button>
        </div>
    </form>
</div>

<template id="budgetItemTemplate">
    @include('employee.budget-requests.partials.item-row', ['index' => '__INDEX__', 'itemTypes' => $itemTypes])
</template>
@endsection

@push('scripts')
<script>
    function addBudgetItemRow() {
        const list = document.getElementById('budgetItems');
        const template = document.getElementById('budgetItemTemplate');
        if (!list || !template) return null;

        const index = list.querySelectorAll('[data-budget-item]').length;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index);
        const row = wrapper.firstElementChild;
        if (!row) return null;

        list.append(row);
        return row;
    }

    document.querySelector('[data-add-budget-item]')?.addEventListener('click', function () {
        addBudgetItemRow();
    });

    document.getElementById('budgetItems')?.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-budget-item]');
        if (!button) return;
        const rows = this.querySelectorAll('[data-budget-item]');
        if (rows.length <= 1) return;
        button.closest('[data-budget-item]').remove();
    });
</script>
@endpush
