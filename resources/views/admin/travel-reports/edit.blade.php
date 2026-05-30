@extends('admin.layouts.app')
@section('title', 'Edit LHP')

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.travel-reports.show', $report->id) }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke Detail
    </a>
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11.5px] font-semibold bg-amber-100 text-amber-700">
        <span class="material-symbols-outlined text-[13px]">edit</span> Mode Edit — Status: Menunggu
    </span>
</div>

@if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
        <ul class="list-disc pl-4 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.travel-reports.update', $report->id) }}" enctype="multipart/form-data" id="lhpForm">
    @csrf
    @method('PUT')

    {{-- 1. Karyawan & Budget Request --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4">
            <span class="material-symbols-outlined text-[16px] text-indigo-500">link</span> Informasi Dasar
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Karyawan</label>
                <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-[13px] text-gray-700 font-medium">
                    {{ $report->employee->full_name ?? '-' }}
                    <span class="text-gray-400 font-normal"> · {{ $report->employee->department->name ?? '' }}</span>
                </div>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Budget Request Terkait (Opsional)</label>
                <select name="budget_request_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Tanpa Budget Request —</option>
                    @foreach($availableRequests as $req)
                        <option value="{{ $req->id }}" {{ old('budget_request_id', $report->budget_request_id) == $req->id ? 'selected' : '' }}>
                            {{ $req->title }} — {{ $req->employee->full_name ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- 2. Identitas Perjalanan --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4">
            <span class="material-symbols-outlined text-[16px] text-indigo-500">badge</span> Identitas Perjalanan Dinas
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Kota Tujuan <span class="text-red-500">*</span></label>
                <input type="text" name="destination_city" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('destination_city', $report->destination_city) }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nomor Surat Tugas</label>
                <input type="text" name="surat_tugas_no" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('surat_tugas_no', $report->surat_tugas_no) }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Keberangkatan <span class="text-red-500">*</span></label>
                <input type="date" name="departure_date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('departure_date', $report->departure_date?->format('Y-m-d')) }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Kepulangan <span class="text-red-500">*</span></label>
                <input type="date" name="return_date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('return_date', $report->return_date?->format('Y-m-d')) }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Surat Tugas</label>
                <input type="date" name="surat_tugas_date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('surat_tugas_date', $report->surat_tugas_date?->format('Y-m-d')) }}">
            </div>
        </div>
    </div>

    {{-- 3. Maksud dan Tujuan --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4">
            <span class="material-symbols-outlined text-[16px] text-indigo-500">flag</span> Maksud dan Tujuan
        </h3>
        <textarea name="purpose" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="3" required>{{ old('purpose', $report->purpose) }}</textarea>
    </div>

    {{-- 4. Kegiatan (pre-filled) --}}
    <div id="activitiesContainer">
        @foreach($report->activities as $i => $activity)
        <div class="activity-block bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden" data-index="{{ $i }}">
            <div class="px-5 py-3 bg-gradient-to-r from-indigo-50 to-indigo-100/50 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-[13px] font-bold text-indigo-700 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[11px] font-bold activity-number">{{ $i + 1 }}</span>
                    Kegiatan {{ $i + 1 }}
                </h3>
                <button type="button" onclick="removeActivityBlock(this)" class="text-[11px] font-semibold text-red-500 hover:text-red-700 activity-remove-btn cursor-pointer" style="{{ $report->activities->count() > 1 ? '' : 'display:none' }}">✕ Hapus</button>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" name="activities[{{ $i }}][date]" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            value="{{ $activity->activity_date?->format('Y-m-d') }}" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Pelaksanaan <span class="text-red-500">*</span></label>
                        <textarea name="activities[{{ $i }}][description]" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2" required>{{ $activity->description }}</textarea>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">✅ Hasil</label>
                    <div class="results-container space-y-2">
                        @forelse($activity->results ?? [] as $r => $result)
                        <div class="result-row flex gap-2 items-center">
                            <span class="text-[11px] font-medium text-gray-400 w-5 shrink-0 result-num">{{ $r + 1 }}.</span>
                            <input type="text" name="activities[{{ $i }}][results][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" value="{{ $result }}">
                            <button type="button" onclick="removeSubRow(this, 'result')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 sub-remove cursor-pointer" {{ count($activity->results ?? []) > 1 ? '' : 'style=display:none' }}>✕</button>
                        </div>
                        @empty
                        <div class="result-row flex gap-2 items-center">
                            <span class="text-[11px] font-medium text-gray-400 w-5 shrink-0 result-num">1.</span>
                            <input type="text" name="activities[{{ $i }}][results][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Hasil yang dicapai...">
                            <button type="button" onclick="removeSubRow(this, 'result')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 sub-remove cursor-pointer" style="display:none">✕</button>
                        </div>
                        @endforelse
                    </div>
                    <button type="button" onclick="addSubRow(this, 'result', {{ $i }})" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Hasil</button>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">⚠️ Permasalahan</label>
                    <textarea name="activities[{{ $i }}][issues]" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2">{{ $activity->issues }}</textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">📝 Kesimpulan Kegiatan</label>
                    <textarea name="activities[{{ $i }}][conclusion]" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2">{{ $activity->conclusion }}</textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">📸 Dokumentasi Baru</label>
                    @if($activity->documents->isNotEmpty())
                    <p class="text-[11px] text-amber-600 mb-2">⚠️ Foto lama ({{ $activity->documents->count() }} file) akan diganti jika kamu upload foto baru. Biarkan kosong untuk mempertahankan foto lama.</p>
                    @endif
                    <div class="docs-container space-y-2">
                        <div class="doc-row flex gap-2 items-start">
                            <input type="file" name="activities[{{ $i }}][documents][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" accept=".jpg,.jpeg,.png,.pdf">
                            <input type="text" name="activities[{{ $i }}][document_captions][]" class="w-36 shrink-0 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" placeholder="Keterangan">
                            <button type="button" onclick="removeSubRow(this, 'doc')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 mt-1 sub-remove cursor-pointer" style="display:none">✕</button>
                        </div>
                    </div>
                    <button type="button" onclick="addSubRow(this, 'doc', {{ $i }})" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Foto</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mb-5">
        <button type="button" onclick="addActivityBlock()" class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-[13px] font-semibold text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition-all cursor-pointer">
            ＋ Tambah Kegiatan Baru
        </button>
    </div>

    {{-- 5. Kesimpulan & Rekomendasi --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4">
            <span class="material-symbols-outlined text-[16px] text-indigo-500">description</span> Kesimpulan & Rekomendasi
        </h3>
        <div class="space-y-4">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Kesimpulan <span class="text-red-500">*</span></label>
                <textarea name="conclusion" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="3" required>{{ old('conclusion', $report->conclusion) }}</textarea>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Rekomendasi Tindak Lanjut</label>
                <div id="recommendationsContainer">
                    @forelse($report->recommendations ?? [] as $r => $rec)
                    <div class="recommendation-row flex gap-2 mb-2 items-center">
                        <span class="text-[12px] font-medium text-gray-400 w-5 shrink-0 rec-num">{{ $r + 1 }}.</span>
                        <input type="text" name="recommendations[]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" value="{{ $rec }}">
                        <button type="button" onclick="removeRecommendation(this)" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 rec-remove cursor-pointer" {{ count($report->recommendations ?? []) > 1 ? '' : 'style=display:none' }}>✕</button>
                    </div>
                    @empty
                    <div class="recommendation-row flex gap-2 mb-2 items-center">
                        <span class="text-[12px] font-medium text-gray-400 w-5 shrink-0 rec-num">1.</span>
                        <input type="text" name="recommendations[]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Rekomendasi tindak lanjut...">
                        <button type="button" onclick="removeRecommendation(this)" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 rec-remove cursor-pointer" style="display:none">✕</button>
                    </div>
                    @endforelse
                </div>
                <button type="button" onclick="addRecommendation()" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Rekomendasi</button>
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.travel-reports.show', $report->id) }}" class="px-5 py-2.5 text-[13px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">Batal</a>
        <button type="submit" class="px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-amber-500 to-amber-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Perubahan
        </button>
    </div>
</form>

@push('scripts')
<script>
let activityIndex = {{ $report->activities->count() }};

function addActivityBlock() {
    const container = document.getElementById('activitiesContainer');
    const idx = activityIndex;
    const num = container.querySelectorAll('.activity-block').length + 1;
    const block = document.createElement('div');
    block.className = 'activity-block bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden';
    block.dataset.index = idx;
    block.innerHTML = `
        <div class="px-5 py-3 bg-gradient-to-r from-indigo-50 to-indigo-100/50 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-[13px] font-bold text-indigo-700 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[11px] font-bold activity-number">${num}</span>
                Kegiatan ${num}
            </h3>
            <button type="button" onclick="removeActivityBlock(this)" class="text-[11px] font-semibold text-red-500 hover:text-red-700 activity-remove-btn cursor-pointer">✕ Hapus</button>
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Tanggal <span class="text-red-500">*</span></label>
                    <input type="date" name="activities[${idx}][date]" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Pelaksanaan <span class="text-red-500">*</span></label>
                    <textarea name="activities[${idx}][description]" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2" required placeholder="Deskripsi kegiatan..."></textarea>
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">✅ Hasil</label>
                <div class="results-container space-y-2">
                    <div class="result-row flex gap-2 items-center">
                        <span class="text-[11px] font-medium text-gray-400 w-5 shrink-0 result-num">1.</span>
                        <input type="text" name="activities[${idx}][results][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Hasil yang dicapai...">
                        <button type="button" onclick="removeSubRow(this, 'result')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 sub-remove cursor-pointer" style="display:none">✕</button>
                    </div>
                </div>
                <button type="button" onclick="addSubRow(this, 'result', ${idx})" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Hasil</button>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">⚠️ Permasalahan</label>
                <textarea name="activities[${idx}][issues]" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2" placeholder="Kosongkan jika tidak ada..."></textarea>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">📝 Kesimpulan Kegiatan</label>
                <textarea name="activities[${idx}][conclusion]" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2" placeholder="Kesimpulan kegiatan ini..."></textarea>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">📸 Dokumentasi</label>
                <div class="docs-container space-y-2">
                    <div class="doc-row flex gap-2 items-start">
                        <input type="file" name="activities[${idx}][documents][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" accept=".jpg,.jpeg,.png,.pdf">
                        <input type="text" name="activities[${idx}][document_captions][]" class="w-36 shrink-0 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" placeholder="Keterangan">
                        <button type="button" onclick="removeSubRow(this, 'doc')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 mt-1 sub-remove cursor-pointer" style="display:none">✕</button>
                    </div>
                </div>
                <button type="button" onclick="addSubRow(this, 'doc', ${idx})" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Foto</button>
            </div>
        </div>
    `;
    container.appendChild(block);
    activityIndex++;
    updateActivityNumbers();
}

function removeActivityBlock(btn) {
    btn.closest('.activity-block').remove();
    updateActivityNumbers();
}

function updateActivityNumbers() {
    const blocks = document.querySelectorAll('#activitiesContainer .activity-block');
    blocks.forEach((block, i) => {
        const numEl = block.querySelector('.activity-number');
        if (numEl) numEl.textContent = i + 1;
        const titleText = numEl?.parentElement;
        if (titleText) {
            const last = titleText.lastChild;
            if (last && last.nodeType === 3) last.textContent = ` Kegiatan ${i + 1}`;
        }
        const removeBtn = block.querySelector('.activity-remove-btn');
        if (removeBtn) removeBtn.style.display = blocks.length > 1 ? '' : 'none';
    });
}

function addSubRow(addBtn, type, actIdx) {
    const container = addBtn.previousElementSibling;
    const actBlock = addBtn.closest('.activity-block');
    const idx = actBlock ? actBlock.dataset.index : actIdx;
    if (type === 'result') {
        const count = container.querySelectorAll('.result-row').length + 1;
        const row = document.createElement('div');
        row.className = 'result-row flex gap-2 items-center';
        row.innerHTML = `<span class="text-[11px] font-medium text-gray-400 w-5 shrink-0 result-num">${count}.</span><input type="text" name="activities[${idx}][results][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Hasil..."><button type="button" onclick="removeSubRow(this,'result')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 sub-remove cursor-pointer">✕</button>`;
        container.appendChild(row);
    } else {
        const row = document.createElement('div');
        row.className = 'doc-row flex gap-2 items-start';
        row.innerHTML = `<input type="file" name="activities[${idx}][documents][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" accept=".jpg,.jpeg,.png,.pdf"><input type="text" name="activities[${idx}][document_captions][]" class="w-36 shrink-0 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" placeholder="Keterangan"><button type="button" onclick="removeSubRow(this,'doc')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 mt-1 sub-remove cursor-pointer">✕</button>`;
        container.appendChild(row);
    }
    updateSubRemoveButtons(container, type);
}

function removeSubRow(btn, type) {
    const container = btn.closest(type === 'result' ? '.results-container' : '.docs-container');
    btn.closest(type === 'result' ? '.result-row' : '.doc-row').remove();
    if (type === 'result') container.querySelectorAll('.result-row').forEach((r, i) => r.querySelector('.result-num').textContent = (i + 1) + '.');
    updateSubRemoveButtons(container, type);
}

function updateSubRemoveButtons(container, type) {
    const rows = container.querySelectorAll(type === 'result' ? '.result-row' : '.doc-row');
    rows.forEach(r => { const b = r.querySelector('.sub-remove'); if (b) b.style.display = rows.length > 1 ? '' : 'none'; });
}

function addRecommendation() {
    const container = document.getElementById('recommendationsContainer');
    const count = container.querySelectorAll('.recommendation-row').length + 1;
    const row = document.createElement('div');
    row.className = 'recommendation-row flex gap-2 mb-2 items-center';
    row.innerHTML = `<span class="text-[12px] font-medium text-gray-400 w-5 shrink-0 rec-num">${count}.</span><input type="text" name="recommendations[]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Rekomendasi..."><button type="button" onclick="removeRecommendation(this)" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 rec-remove cursor-pointer">✕</button>`;
    container.appendChild(row);
    updateRecRemoveButtons();
}

function removeRecommendation(btn) {
    btn.closest('.recommendation-row').remove();
    document.getElementById('recommendationsContainer').querySelectorAll('.recommendation-row').forEach((r, i) => r.querySelector('.rec-num').textContent = (i + 1) + '.');
    updateRecRemoveButtons();
}

function updateRecRemoveButtons() {
    const rows = document.getElementById('recommendationsContainer').querySelectorAll('.recommendation-row');
    rows.forEach(r => { const b = r.querySelector('.rec-remove'); if (b) b.style.display = rows.length > 1 ? '' : 'none'; });
}

updateActivityNumbers();
updateRecRemoveButtons();
</script>
@endpush
@endsection
