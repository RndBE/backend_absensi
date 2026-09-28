@php
    $selectedBudgetId = old('budget_request_id', $report?->budget_request_id ?? request('budget_request_id'));
    // Dokumen lama per aktivitas; baris aktivitas merujuknya lewat existing_documents.
    $existingDocuments = $report ? $report->activities->flatMap->documents->keyBy('id') : collect();
    $activities = old('activities');
    if (! is_array($activities)) {
        $activities = $report
            ? $report->activities->map(fn ($activity) => [
                'date' => optional($activity->activity_date)->format('Y-m-d'),
                'description' => $activity->description,
                'results' => $activity->results ?: [''],
                'issues' => $activity->issues,
                'conclusion' => $activity->conclusion,
                'existing_documents' => $activity->documents->pluck('id')->all(),
            ])->values()->all()
            : [[
                'date' => '',
                'description' => '',
                'results' => [''],
                'issues' => '',
                'conclusion' => '',
            ]];
    }
    $recommendations = old('recommendations', $report?->recommendations ?: ['']);
@endphp

<div class="space-y-4">
    <div>
        <a href="{{ route('employee.travel-reports.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            LHP
        </a>
        <h1 class="text-[22px] font-black text-gray-900">{{ $title }}</h1>
        <p class="text-[13px] text-gray-500 mt-1">{{ $subtitle }}</p>
    </div>

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4" id="travelReportForm">
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif

        @isset($resubmissionOf)
            @php $rejection = $resubmissionOf->latestRejection; @endphp
            <input type="hidden" name="resubmission_of_id" value="{{ $resubmissionOf->id }}">
            @isset($existingDocuments)
                {{-- Baris aktivitas menampilkan foto lama beserta centang Hapus ($existingDocuments),
                     jadi server hanya menyalin foto yang tidak dicentang. --}}
                <input type="hidden" name="document_selection" value="1">
            @endisset
            <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 space-y-1">
                <div class="flex items-center gap-1.5 text-[13px] font-bold text-amber-800">
                    <span class="material-symbols-outlined text-[18px]">report</span>
                    Yang perlu diperbaiki
                </div>
                <div class="text-[13px] text-amber-800">{{ $rejection?->notes ?: 'Approver tidak menuliskan alasan penolakan.' }}</div>
                @if($rejection)
                    <div class="text-[12px] text-amber-700">Ditolak oleh {{ $rejection->approver?->full_name ?? 'approver' }} · step {{ $rejection->step_order }} · {{ $rejection->created_at?->format('d/m/Y') }}</div>
                @endif
                @if($resubmissionOf->documents->isNotEmpty())
                    <div class="text-[12px] text-amber-700">
                        @isset($existingDocuments)
                            Foto dokumentasi dari LHP yang ditolak ikut disalin, kecuali yang dicentang Hapus. Tambahkan foto baru bila perlu.
                        @else
                            {{ $resubmissionOf->documents->count() }} foto dokumentasi dari LHP yang ditolak ikut disalin otomatis. Tambahkan foto baru bila perlu.
                        @endisset
                    </div>
                @endif
            </section>
        @endisset

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Budget Request Terkait</span>
                @php $deadlineHints = $lhpDeadlines ?? []; @endphp
                <select name="budget_request_id" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                    <option value="">- Tanpa Budget Request -</option>
                    @foreach($availableRequests as $budgetRequest)
                        @php $hint = $deadlineHints[$budgetRequest->id] ?? null; @endphp
                        <option value="{{ $budgetRequest->id }}"
                            data-surat-no="{{ $budgetRequest->surat_tugas_no }}"
                            data-surat-date="{{ $budgetRequest->surat_tugas_date?->format('Y-m-d') }}"
                            data-distance="{{ $budgetRequest->distance_km }}"
                            data-deadline="{{ $hint['date'] ?? '' }}"
                            data-deadline-days="{{ $hint['days'] ?? '' }}"
                            data-deadline-late="{{ $hint ? ($hint['late'] ? '1' : '0') : '' }}"
                            @selected((string) $selectedBudgetId === (string) $budgetRequest->id)>
                            {{ $budgetRequest->title }} - Rp {{ number_format((float) $budgetRequest->total_amount, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
                <div data-lhp-deadline-hint class="mt-2 hidden rounded-lg border px-3 py-2 text-[12px]"></div>
            </label>

            {{-- Jarak KM tidak ditampilkan (mengikuti aplikasi mobile); nilai lama tetap dipertahankan. --}}
            <input type="hidden" name="distance_km" value="{{ old('distance_km', $report?->distance_km) }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="sm:col-span-2">
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Kota Tujuan</span>
                    <input name="destination_city" value="{{ old('destination_city', $report?->destination_city) }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Batam">
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Tanggal Berangkat</span>
                    <span class="employee-date-shell" data-employee-date-shell>
                        <input type="date" name="departure_date" value="{{ old('departure_date', optional($report?->departure_date)->format('Y-m-d')) }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <span class="employee-date-placeholder" data-date-placeholder>mm/dd/yyyy</span>
                    </span>
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Tanggal Pulang</span>
                    <span class="employee-date-shell" data-employee-date-shell>
                        <input type="date" name="return_date" value="{{ old('return_date', optional($report?->return_date)->format('Y-m-d')) }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <span class="employee-date-placeholder" data-date-placeholder>mm/dd/yyyy</span>
                    </span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">No. Surat Tugas</span>
                    <input name="surat_tugas_no" value="{{ old('surat_tugas_no', $report?->surat_tugas_no) }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                </label>
                <label>
                    <span class="block text-[12px] font-bold text-gray-600 mb-1">Tanggal Surat</span>
                    <span class="employee-date-shell" data-employee-date-shell>
                        <input type="date" name="surat_tugas_date" value="{{ old('surat_tugas_date', optional($report?->surat_tugas_date)->format('Y-m-d')) }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <span class="employee-date-placeholder" data-date-placeholder>mm/dd/yyyy</span>
                    </span>
                </label>
            </div>

            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Tujuan Perjalanan</span>
                <textarea name="purpose" rows="3" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">{{ old('purpose', $report?->purpose) }}</textarea>
            </label>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-black text-gray-900">Aktivitas</h2>
                <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-gray-900 px-3 py-2 text-[12px] font-bold text-white" data-add-activity>
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Tambah Aktivitas
                </button>
            </div>
            <div class="space-y-3" id="travelActivities">
                @foreach($activities as $index => $activity)
                    @include('employee.travel-reports.partials.activity-row', ['index' => $index, 'activity' => $activity, 'existingDocuments' => $existingDocuments])
                @endforeach
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Kesimpulan</span>
                <textarea name="conclusion" rows="3" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">{{ old('conclusion', $report?->conclusion) }}</textarea>
            </label>
            <div>
                <div class="flex items-center justify-between gap-3 mb-2">
                    <span class="text-[12px] font-bold text-gray-600">Rekomendasi</span>
                    <button type="button" class="text-[12px] font-bold text-indigo-700" data-add-recommendation>Tambah</button>
                </div>
                <div class="space-y-2" id="recommendations">
                    @foreach($recommendations as $recommendation)
                        <div class="flex items-center gap-2" data-recommendation-row>
                            <input name="recommendations[]" value="{{ $recommendation }}" class="employee-native-field min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Rekomendasi tindak lanjut">
                            <button type="button" data-remove-recommendation aria-label="Hapus rekomendasi" title="Hapus rekomendasi"
                                class="{{ count($recommendations) > 1 ? '' : 'hidden' }} inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-white text-red-600 hover:bg-red-50">
                                <span class="material-symbols-outlined text-[18px]">close</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employee.travel-reports.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-[12px] font-bold text-gray-700">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-[12px] font-bold text-white">{{ isset($resubmissionOf) ? 'Ajukan Ulang LHP' : ($method === 'POST' ? 'Kirim LHP' : 'Simpan Perubahan') }}</button>
        </div>
    </form>
</div>

<template id="activityTemplate">
    @include('employee.travel-reports.partials.activity-row', ['index' => '__INDEX__', 'activity' => ['date' => '', 'description' => '', 'results' => [''], 'issues' => '', 'conclusion' => '']])
</template>

@push('scripts')
<script>
    // Tombol hapus baris hanya tampil bila baris lebih dari satu; baris terakhir cukup dikosongkan.
    function syncRemoveButtons(list, selector) {
        const buttons = list.querySelectorAll(selector);
        buttons.forEach((button) => button.classList.toggle('hidden', buttons.length <= 1));
    }

    // Baris baru disalin dari baris pertama lalu dikosongkan; nama isian memakai [] jadi tak perlu diganti.
    function appendEmptyRow(list, rowSelector) {
        const row = list.querySelector(rowSelector).cloneNode(true);
        row.querySelectorAll('input').forEach((input) => { input.value = ''; });
        list.append(row);
        return row;
    }

    document.querySelector('[data-add-activity]')?.addEventListener('click', function () {
        const list = document.getElementById('travelActivities');
        const template = document.getElementById('activityTemplate');
        // Pakai index terbesar + 1, bukan jumlah baris: setelah baris tengah dihapus, jumlah baris
        // bisa sama dengan index yang masih dipakai sehingga dua baris (dan dokumennya) tergabung.
        const indexes = Array.from(list.querySelectorAll('[data-travel-activity]'), (row) => Number(row.dataset.index));
        const index = Math.max(-1, ...indexes) + 1;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index);
        list.append(...wrapper.children);
    });

    document.getElementById('travelActivities')?.addEventListener('click', function (event) {
        const removeActivity = event.target.closest('[data-remove-activity]');
        if (removeActivity) {
            const rows = this.querySelectorAll('[data-travel-activity]');
            if (rows.length > 1) removeActivity.closest('[data-travel-activity]').remove();
            return;
        }

        const removeResult = event.target.closest('[data-remove-result]');
        if (removeResult) {
            const list = removeResult.closest('[data-results]');
            removeResult.closest('[data-result-row]').remove();
            syncRemoveButtons(list, '[data-remove-result]');
            return;
        }

        const addResult = event.target.closest('[data-add-result]');
        if (addResult) {
            const list = addResult.closest('[data-travel-activity]').querySelector('[data-results]');
            appendEmptyRow(list, '[data-result-row]').querySelector('input').focus();
            syncRemoveButtons(list, '[data-remove-result]');
        }
    });

    // Redupkan pratinjau dokumen lama yang dicentang Hapus.
    document.getElementById('travelActivities')?.addEventListener('change', function (event) {
        const removeDocument = event.target.closest('[data-remove-document]');
        if (!removeDocument) return;
        removeDocument.closest('[data-existing-document]')
            ?.querySelector('[data-document-preview]')
            ?.classList.toggle('opacity-40', removeDocument.checked);
    });

    // Auto-isi data dari Budget Request yang dipilih.
    const budgetSelect = document.querySelector('select[name="budget_request_id"]');
    const deadlineHint = document.querySelector('[data-lhp-deadline-hint]');

    function renderDeadlineHint(opt) {
        if (!deadlineHint) return;
        const date = opt?.dataset.deadline || '';
        if (!opt || !opt.value || !date) {
            deadlineHint.classList.add('hidden');
            return;
        }
        const days = opt.dataset.deadlineDays || '';
        const isLate = opt.dataset.deadlineLate === '1';
        deadlineHint.classList.remove('hidden', 'bg-red-50', 'border-red-200', 'text-red-700', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
        if (isLate) {
            deadlineHint.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
            deadlineHint.innerHTML = `⚠️ Batas pengumpulan LHP <b>${date}</b> (${days} hari kerja) sudah terlewat. LHP akan ditandai <b>Terlambat</b>.`;
        } else {
            deadlineHint.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
            deadlineHint.innerHTML = `🗓️ Batas pengumpulan LHP: <b>${date}</b> (${days} hari kerja setelah pulang).`;
        }
    }

    budgetSelect?.addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        renderDeadlineHint(opt);
        if (!opt || !this.value) return;
        const form = this.closest('form');
        const setVal = (name, val) => {
            const el = form?.querySelector(`[name="${name}"]`);
            if (el && val != null && val !== '') {
                el.value = val;
                // Picu change agar overlay date-shell ikut ter-update (mm/dd/yyyy hilang).
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        setVal('surat_tugas_no', opt.dataset.suratNo);
        setVal('surat_tugas_date', opt.dataset.suratDate);
        setVal('distance_km', opt.dataset.distance);
    });

    // Tampilkan hint untuk pilihan awal (mis. saat edit / old input).
    if (budgetSelect?.value) renderDeadlineHint(budgetSelect.selectedOptions[0]);

    const recommendationList = document.getElementById('recommendations');

    document.querySelector('[data-add-recommendation]')?.addEventListener('click', function () {
        appendEmptyRow(recommendationList, '[data-recommendation-row]').querySelector('input').focus();
        syncRemoveButtons(recommendationList, '[data-remove-recommendation]');
    });

    recommendationList?.addEventListener('click', function (event) {
        const removeRecommendation = event.target.closest('[data-remove-recommendation]');
        if (!removeRecommendation) return;
        removeRecommendation.closest('[data-recommendation-row]').remove();
        syncRemoveButtons(recommendationList, '[data-remove-recommendation]');
    });
</script>
@endpush
