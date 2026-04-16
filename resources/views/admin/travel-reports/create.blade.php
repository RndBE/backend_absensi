@extends('admin.layouts.app')
@section('title', 'Buat LHP')

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('admin.travel-reports.index') }}" class="inline-flex items-center gap-1 text-[13px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
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

<form method="POST" action="{{ route('admin.travel-reports.store') }}" enctype="multipart/form-data" id="lhpForm">
    @csrf

    {{-- 1. Request & Employee --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4"><span class="material-symbols-outlined text-[16px] text-indigo-500">link</span> Informasi Dasar</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Karyawan <span class="text-red-500">*</span></label>
                <select name="employee_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" required>
                    <option value="">— Pilih Karyawan —</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ old('employee_id', $selectedRequest?->employee_id) == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Budget Request Terkait (Opsional)</label>
                <select name="budget_request_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    onchange="if(this.value) window.location.href='{{ route('admin.travel-reports.create') }}?budget_request_id=' + this.value">
                    <option value="">— Tanpa Budget Request —</option>
                    @foreach($availableRequests as $req)
                        <option value="{{ $req->id }}" {{ ($selectedRequest && $selectedRequest->id == $req->id) ? 'selected' : '' }}>
                            {{ $req->title }} — {{ $req->employee->full_name ?? '' }} (Rp {{ number_format($req->total_amount, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- 2. Identitas Perjalanan --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4"><span class="material-symbols-outlined text-[16px] text-indigo-500">badge</span> Identitas Perjalanan Dinas</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Kota Tujuan <span class="text-red-500">*</span></label>
                <input type="text" name="destination_city" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('destination_city') }}" required placeholder="Contoh: Jakarta">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nomor Surat Tugas</label>
                <input type="text" name="surat_tugas_no" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('surat_tugas_no', $selectedRequest?->surat_tugas_no) }}" placeholder="Contoh: 001/ST-ATC/IV/2026">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Keberangkatan <span class="text-red-500">*</span></label>
                <input type="date" name="departure_date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('departure_date') }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Kepulangan <span class="text-red-500">*</span></label>
                <input type="date" name="return_date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('return_date') }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tanggal Surat Tugas</label>
                <input type="date" name="surat_tugas_date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    value="{{ old('surat_tugas_date', $selectedRequest?->surat_tugas_date) }}">
            </div>
        </div>
    </div>

    {{-- 3. Maksud dan Tujuan --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4"><span class="material-symbols-outlined text-[16px] text-indigo-500">flag</span> Maksud dan Tujuan</h3>
        <textarea name="purpose" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="3" required
            placeholder="Jelaskan tujuan perjalanan dinas...">{{ old('purpose', $selectedRequest?->title) }}</textarea>
    </div>

    {{-- 4. Kegiatan --}}
    <div id="activitiesContainer">
        <div class="activity-block bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden" data-index="0">
            <div class="px-5 py-3 bg-gradient-to-r from-indigo-50 to-indigo-100/50 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-[13px] font-bold text-indigo-700 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[11px] font-bold activity-number">1</span>
                    Kegiatan 1
                </h3>
                <button type="button" onclick="removeActivityBlock(this)" class="text-[11px] font-semibold text-red-500 hover:text-red-700 activity-remove-btn cursor-pointer" style="display:none;">✕ Hapus</button>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" name="activities[0][date]" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Pelaksanaan <span class="text-red-500">*</span></label>
                        <textarea name="activities[0][description]" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2" required placeholder="Deskripsi kegiatan..."></textarea>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">✅ Hasil</label>
                    <div class="results-container space-y-2">
                        <div class="result-row flex gap-2 items-center">
                            <span class="text-[11px] font-medium text-gray-400 w-5 shrink-0 result-num">1.</span>
                            <input type="text" name="activities[0][results][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Hasil yang dicapai...">
                            <button type="button" onclick="removeSubRow(this, 'result')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 sub-remove cursor-pointer" style="display:none;">✕</button>
                        </div>
                    </div>
                    <button type="button" onclick="addSubRow(this, 'result', 0)" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Hasil</button>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">⚠️ Permasalahan</label>
                    <textarea name="activities[0][issues]" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2" placeholder="Kosongkan jika tidak ada..."></textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">📝 Kesimpulan Kegiatan</label>
                    <textarea name="activities[0][conclusion]" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="2" placeholder="Kesimpulan kegiatan ini..."></textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">📸 Dokumentasi</label>
                    <div class="docs-container space-y-2">
                        <div class="doc-row flex gap-2 items-start">
                            <input type="file" name="activities[0][documents][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" accept=".jpg,.jpeg,.png,.pdf">
                            <input type="text" name="activities[0][document_captions][]" class="w-36 shrink-0 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" placeholder="Keterangan">
                            <button type="button" onclick="removeSubRow(this, 'doc')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 mt-1 sub-remove cursor-pointer" style="display:none;">✕</button>
                        </div>
                    </div>
                    <button type="button" onclick="addSubRow(this, 'doc', 0)" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Foto</button>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-5">
        <button type="button" onclick="addActivityBlock()" class="w-full py-3 border-2 border-dashed border-gray-300 rounded-xl text-[13px] font-semibold text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition-all cursor-pointer">
            ＋ Tambah Kegiatan Baru
        </button>
    </div>

    {{-- 5. Kesimpulan & Rekomendasi --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-[14px] font-bold text-gray-800 flex items-center gap-1.5 mb-4"><span class="material-symbols-outlined text-[16px] text-indigo-500">description</span> Kesimpulan & Rekomendasi</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Kesimpulan <span class="text-red-500">*</span></label>
                <textarea name="conclusion" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" rows="3" required placeholder="Kesimpulan keseluruhan...">{{ old('conclusion') }}</textarea>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Rekomendasi Tindak Lanjut</label>
                <div id="recommendationsContainer">
                    <div class="recommendation-row flex gap-2 mb-2 items-center">
                        <span class="text-[12px] font-medium text-gray-400 w-5 shrink-0 rec-num">1.</span>
                        <input type="text" name="recommendations[]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Rekomendasi tindak lanjut...">
                        <button type="button" onclick="removeRecommendation(this)" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 rec-remove cursor-pointer" style="display:none;">✕</button>
                    </div>
                </div>
                <button type="button" onclick="addRecommendation()" class="text-[11px] text-indigo-500 hover:underline mt-1 cursor-pointer">+ Tambah Rekomendasi</button>
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.travel-reports.index') }}" class="px-5 py-2.5 text-[13px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">Batal</a>
        <button type="submit" class="px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan LHP</button>
    </div>
</form>

@push('scripts')
<script>
let activityIndex = 1;

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
                        <button type="button" onclick="removeSubRow(this, 'result')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 sub-remove cursor-pointer" style="display:none;">✕</button>
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
                        <button type="button" onclick="removeSubRow(this, 'doc')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 mt-1 sub-remove cursor-pointer" style="display:none;">✕</button>
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
        const titleEl = numEl.parentElement;
        numEl.textContent = i + 1;
        titleEl.childNodes[titleEl.childNodes.length - 1].textContent = ` Kegiatan ${i + 1}`;
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
        row.innerHTML = `
            <span class="text-[11px] font-medium text-gray-400 w-5 shrink-0 result-num">${count}.</span>
            <input type="text" name="activities[${idx}][results][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Hasil yang dicapai...">
            <button type="button" onclick="removeSubRow(this, 'result')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 sub-remove cursor-pointer">✕</button>
        `;
        container.appendChild(row);
    } else if (type === 'doc') {
        const row = document.createElement('div');
        row.className = 'doc-row flex gap-2 items-start';
        row.innerHTML = `
            <input type="file" name="activities[${idx}][documents][]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" accept=".jpg,.jpeg,.png,.pdf">
            <input type="text" name="activities[${idx}][document_captions][]" class="w-36 shrink-0 border border-gray-200 rounded-lg px-3 py-1.5 text-[12px]" placeholder="Keterangan">
            <button type="button" onclick="removeSubRow(this, 'doc')" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 mt-1 sub-remove cursor-pointer">✕</button>
        `;
        container.appendChild(row);
    }
    updateSubRemoveButtons(container, type);
}

function removeSubRow(btn, type) {
    const container = btn.closest(type === 'result' ? '.results-container' : '.docs-container');
    btn.closest(type === 'result' ? '.result-row' : '.doc-row').remove();
    if (type === 'result') {
        container.querySelectorAll('.result-row').forEach((row, i) => row.querySelector('.result-num').textContent = (i + 1) + '.');
    }
    updateSubRemoveButtons(container, type);
}

function updateSubRemoveButtons(container, type) {
    const rows = container.querySelectorAll(type === 'result' ? '.result-row' : '.doc-row');
    rows.forEach(row => { const btn = row.querySelector('.sub-remove'); if (btn) btn.style.display = rows.length > 1 ? '' : 'none'; });
}

function addRecommendation() {
    const container = document.getElementById('recommendationsContainer');
    const count = container.querySelectorAll('.recommendation-row').length + 1;
    const row = document.createElement('div');
    row.className = 'recommendation-row flex gap-2 mb-2 items-center';
    row.innerHTML = `
        <span class="text-[12px] font-medium text-gray-400 w-5 shrink-0 rec-num">${count}.</span>
        <input type="text" name="recommendations[]" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300" placeholder="Rekomendasi tindak lanjut...">
        <button type="button" onclick="removeRecommendation(this)" class="text-red-400 hover:text-red-600 text-sm font-bold shrink-0 rec-remove cursor-pointer">✕</button>
    `;
    container.appendChild(row);
    updateRecRemoveButtons();
}

function removeRecommendation(btn) {
    btn.closest('.recommendation-row').remove();
    const container = document.getElementById('recommendationsContainer');
    container.querySelectorAll('.recommendation-row').forEach((row, i) => row.querySelector('.rec-num').textContent = (i + 1) + '.');
    updateRecRemoveButtons();
}

function updateRecRemoveButtons() {
    const container = document.getElementById('recommendationsContainer');
    const rows = container.querySelectorAll('.recommendation-row');
    rows.forEach(row => { const btn = row.querySelector('.rec-remove'); if (btn) btn.style.display = rows.length > 1 ? '' : 'none'; });
}

updateActivityNumbers();
updateRecRemoveButtons();
</script>
@endpush
@endsection
