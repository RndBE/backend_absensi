@extends('employee.layouts.app')
@section('title', 'Pengajuan Anggaran')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.budget-requests.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Anggaran
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Pengajuan Anggaran</h1>
        <p class="text-[13px] text-gray-500 mt-1">Ajukan budget atau reimbursement dengan rincian item biaya.</p>
    </div>

    <form method="POST" action="{{ route('employee.budget-requests.store') }}" enctype="multipart/form-data" class="space-y-4" id="budgetRequestForm">
        @csrf

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Tipe</span>
                    <select name="type" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <option value="budget" @selected(old('type') === 'budget')>Budget</option>
                        <option value="reimbursement" @selected(old('type') === 'reimbursement')>Reimbursement</option>
                    </select>
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Judul</span>
                    <input name="title" value="{{ old('title') }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Perjalanan Batam">
                </label>
            </div>

            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Deskripsi</span>
                <textarea name="description" rows="3" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Keterangan pengajuan">{{ old('description') }}</textarea>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">No. Surat Tugas</span>
                    <input name="surat_tugas_no" value="{{ old('surat_tugas_no') }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Tanggal Surat</span>
                    <span class="employee-date-shell" data-employee-date-shell>
                        <input type="date" name="surat_tugas_date" value="{{ old('surat_tugas_date') }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <span class="employee-date-placeholder" data-date-placeholder>mm/dd/yyyy</span>
                    </span>
                </label>
            </div>

            <div
                class="space-y-2"
                data-travel-zone-estimator
                data-estimate-url="{{ route('employee.travel.estimate-zone') }}"
            >
                <label class="block">
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Kota Tujuan <span class="font-semibold text-gray-400">(zona perjalanan)</span></span>
                    <input
                        name="destination_city"
                        value="{{ old('destination_city') }}"
                        autocomplete="off"
                        class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                        placeholder="Misal: Surabaya, Jakarta..."
                        data-destination-city
                    >
                </label>
                <input type="hidden" name="distance_km" value="{{ old('distance_km') }}" data-distance-km>

                <div class="hidden items-center gap-2 text-[12px] font-semibold text-gray-500" data-zone-loading>
                    <span class="inline-block h-3.5 w-3.5 rounded-full border-2 border-indigo-200 border-t-indigo-600 animate-spin"></span>
                    Mendeteksi zona...
                </div>

                <div class="hidden rounded-xl border border-indigo-100 bg-indigo-50 p-3" data-zone-card>
                    <div class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[18px] text-indigo-600">location_on</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13px] font-black text-indigo-700" data-zone-title></div>
                            <div class="mt-1 text-[12px] font-semibold text-gray-600" data-zone-meal></div>
                            <button type="button" class="mt-3 inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-[12px] font-bold text-white" data-apply-meal>
                                <span class="material-symbols-outlined text-[15px]">restaurant</span>
                                Tambah Uang Makan
                            </button>
                        </div>
                    </div>
                </div>

                <div class="hidden rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-[12px] font-semibold text-amber-700" data-zone-error></div>
            </div>

            @include('employee.budget-requests.partials.participants', [
                'selected' => collect(old('participants', []))->map(fn ($id) => (int) $id)->all(),
            ])

            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Lampiran Utama</span>
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
                @include('employee.budget-requests.partials.item-row', ['index' => 0, 'itemTypes' => $itemTypes])
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employee.budget-requests.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-[12px] font-bold text-gray-700">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-[12px] font-bold text-white">Kirim Pengajuan</button>
        </div>
    </form>
</div>

<template id="budgetItemTemplate">
    @include('employee.budget-requests.partials.item-row', ['index' => '__INDEX__', 'itemTypes' => $itemTypes])
</template>

{{-- Modal: jumlah hari perjalanan untuk uang makan --}}
<div id="mealDaysModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-meal-close></div>
    <div class="relative w-full max-w-sm rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100">
            <span class="material-symbols-outlined text-[20px] text-indigo-500">restaurant</span>
            <h3 class="text-[15px] font-bold text-gray-900">Tambah Uang Makan</h3>
        </div>
        <div class="px-5 py-5">
            <label class="block text-[12px] font-bold text-gray-600 mb-1.5">Jumlah hari perjalanan</label>
            <input type="number" id="mealDaysInput" min="1" step="1" value="1" class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 [color-scheme:light]">
            <p id="mealDaysPreview" class="text-[11px] text-gray-400 mt-1.5"></p>
        </div>
        <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl">
            <button type="button" data-meal-close class="px-4 py-2.5 text-[13px] font-semibold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50">Batal</button>
            <button type="button" id="mealDaysConfirm" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-xl shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span> Tambahkan
            </button>
        </div>
    </div>
</div>
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

    function formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value || 0);
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

    (function () {
        const estimator = document.querySelector('[data-travel-zone-estimator]');
        if (!estimator) return;

        const cityInput = estimator.querySelector('[data-destination-city]');
        const distanceInput = estimator.querySelector('[data-distance-km]');
        const loading = estimator.querySelector('[data-zone-loading]');
        const card = estimator.querySelector('[data-zone-card]');
        const title = estimator.querySelector('[data-zone-title]');
        const meal = estimator.querySelector('[data-zone-meal]');
        const error = estimator.querySelector('[data-zone-error]');
        const applyMeal = estimator.querySelector('[data-apply-meal]');
        const estimateUrl = estimator.dataset.estimateUrl;
        let debounceTimer = null;
        let currentZone = null;

        function show(element) {
            element?.classList.remove('hidden');
            if (element === loading) element?.classList.add('flex');
        }

        function hide(element) {
            element?.classList.add('hidden');
            if (element === loading) element?.classList.remove('flex');
        }

        function resetZone() {
            currentZone = null;
            if (distanceInput) distanceInput.value = '';
            hide(card);
            hide(error);
        }

        function showError(message) {
            resetZone();
            if (!error) return;
            error.textContent = message;
            show(error);
        }

        async function estimateZone(city) {
            if (!city || city.length < 3) {
                resetZone();
                return;
            }

            hide(error);
            hide(card);
            show(loading);

            try {
                const response = await fetch(`${estimateUrl}?city=${encodeURIComponent(city)}`, {
                    headers: { Accept: 'application/json' },
                });
                const body = await response.json();
                if (!response.ok || body.success !== true || !body.data) {
                    showError(body.message || 'Kota tidak ditemukan.');
                    return;
                }

                const data = body.data;
                currentZone = data.zone || null;
                if (distanceInput) distanceInput.value = data.distance_km ?? '';

                if (!currentZone) {
                    showError('Zona perjalanan belum ditemukan untuk kota ini.');
                    return;
                }

                title.textContent = `${currentZone.name} (${currentZone.km_range})${data.distance_km !== null ? ` - ${data.distance_km} km` : ''}`;
                meal.textContent = `Uang makan: ${formatRupiah(Number(currentZone.meal_allowance || 0))}/hari`;
                show(card);
            } catch (e) {
                showError('Gagal mendeteksi zona. Coba lagi sebentar.');
            } finally {
                hide(loading);
            }
        }

        cityInput?.addEventListener('input', function () {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(() => estimateZone(this.value.trim()), 800);
        });

        const mealModal = document.getElementById('mealDaysModal');
        const mealDaysInput = document.getElementById('mealDaysInput');
        const mealDaysPreview = document.getElementById('mealDaysPreview');

        function closeMealModal() {
            mealModal?.classList.add('hidden');
            mealModal?.classList.remove('flex');
        }

        function updateMealPreview() {
            if (!currentZone || !mealDaysPreview) return;
            const days = Math.max(1, Number.parseInt(mealDaysInput?.value || '1', 10) || 1);
            const mealPerDay = Number(currentZone.meal_allowance || 0);
            mealDaysPreview.textContent = `${days} hari × ${formatRupiah(mealPerDay)} = ${formatRupiah(mealPerDay * days)}`;
        }

        function applyMealWithDays(days) {
            const mealPerDay = Number(currentZone.meal_allowance || 0);
            const amount = mealPerDay * days;
            const description = `Uang makan (${currentZone.name}) - ${days} hari x ${formatRupiah(mealPerDay)}`;
            const rows = Array.from(document.querySelectorAll('#budgetItems [data-budget-item]'));
            let row = rows.find((item) => item.querySelector('select[name$="[type]"]')?.value === 'meal');
            if (!row) row = addBudgetItemRow();
            if (!row) return;

            const typeInput = row.querySelector('select[name$="[type]"]');
            const descriptionInput = row.querySelector('input[name$="[description]"]');
            const amountInput = row.querySelector('input[name$="[amount]"]');
            if (typeInput) typeInput.value = 'meal';
            if (descriptionInput) descriptionInput.value = description;
            if (amountInput) amountInput.value = String(Math.round(amount));
        }

        function confirmMealDays() {
            const days = Number.parseInt(mealDaysInput?.value || '', 10);
            if (!Number.isInteger(days) || days <= 0) {
                mealDaysInput?.focus();
                return;
            }
            applyMealWithDays(days);
            closeMealModal();
        }

        applyMeal?.addEventListener('click', function () {
            if (!currentZone || !mealModal) return;
            mealDaysInput.value = '1';
            updateMealPreview();
            mealModal.classList.remove('hidden');
            mealModal.classList.add('flex');
            setTimeout(() => mealDaysInput?.focus(), 50);
        });

        mealDaysInput?.addEventListener('input', updateMealPreview);
        mealDaysInput?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); confirmMealDays(); }
        });
        document.getElementById('mealDaysConfirm')?.addEventListener('click', confirmMealDays);
        mealModal?.querySelectorAll('[data-meal-close]').forEach((el) => el.addEventListener('click', closeMealModal));
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mealModal && !mealModal.classList.contains('hidden')) closeMealModal();
        });
    })();
</script>
@endpush
