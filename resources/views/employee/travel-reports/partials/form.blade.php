@php
    $selectedBudgetId = old('budget_request_id', $report?->budget_request_id);
    $activities = old('activities');
    if (! is_array($activities)) {
        $activities = $report
            ? $report->activities->map(fn ($activity) => [
                'date' => optional($activity->activity_date)->format('Y-m-d'),
                'description' => $activity->description,
                'results' => $activity->results ?: [''],
                'issues' => $activity->issues,
                'conclusion' => $activity->conclusion,
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

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Budget Request Terkait</span>
                <select name="budget_request_id" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                    <option value="">- Tanpa Budget Request -</option>
                    @foreach($availableRequests as $budgetRequest)
                        <option value="{{ $budgetRequest->id }}"
                            data-surat-no="{{ $budgetRequest->surat_tugas_no }}"
                            data-surat-date="{{ $budgetRequest->surat_tugas_date?->format('Y-m-d') }}"
                            data-distance="{{ $budgetRequest->distance_km }}"
                            @selected((string) $selectedBudgetId === (string) $budgetRequest->id)>
                            {{ $budgetRequest->title }} - Rp {{ number_format((float) $budgetRequest->total_amount, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
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
                    @include('employee.travel-reports.partials.activity-row', ['index' => $index, 'activity' => $activity])
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
                        <input name="recommendations[]" value="{{ $recommendation }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Rekomendasi tindak lanjut">
                    @endforeach
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employee.travel-reports.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-[12px] font-bold text-gray-700">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-[12px] font-bold text-white">{{ $method === 'POST' ? 'Kirim LHP' : 'Simpan Perubahan' }}</button>
        </div>
    </form>
</div>

<template id="activityTemplate">
    @include('employee.travel-reports.partials.activity-row', ['index' => '__INDEX__', 'activity' => ['date' => '', 'description' => '', 'results' => [''], 'issues' => '', 'conclusion' => '']])
</template>

@push('scripts')
<script>
    document.querySelector('[data-add-activity]')?.addEventListener('click', function () {
        const list = document.getElementById('travelActivities');
        const template = document.getElementById('activityTemplate');
        const index = list.querySelectorAll('[data-travel-activity]').length;
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

        const addResult = event.target.closest('[data-add-result]');
        if (addResult) {
            const activity = addResult.closest('[data-travel-activity]');
            const list = activity.querySelector('[data-results]');
            const activityIndex = activity.dataset.index;
            const resultIndex = list.querySelectorAll('input').length;
            const input = document.createElement('input');
            input.name = `activities[${activityIndex}][results][${resultIndex}]`;
            input.className = 'employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100';
            input.placeholder = 'Hasil kegiatan';
            list.append(input);
        }
    });

    // Auto-isi data dari Budget Request yang dipilih.
    const budgetSelect = document.querySelector('select[name="budget_request_id"]');
    budgetSelect?.addEventListener('change', function () {
        const opt = this.selectedOptions[0];
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

    document.querySelector('[data-add-recommendation]')?.addEventListener('click', function () {
        const list = document.getElementById('recommendations');
        const input = document.createElement('input');
        input.name = 'recommendations[]';
        input.className = 'employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100';
        input.placeholder = 'Rekomendasi tindak lanjut';
        list.append(input);
    });
</script>
@endpush
