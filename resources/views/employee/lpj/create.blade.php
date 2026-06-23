@extends('employee.layouts.app')
@section('title', 'Buat LPJ')

@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('employee.lpj.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Kembali
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Buat LPJ</h1>
        <p class="text-[13px] text-gray-500 mt-1">Laporan Pertanggungjawaban anggaran kegiatan.</p>
    </div>

    @if($errors->has('error'))
    <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-[13px]">{{ $errors->first('error') }}</div>
    @endif

    <form method="POST" action="{{ route('employee.lpj.store') }}" enctype="multipart/form-data" id="lpj-form">
        @csrf

        {{-- Pilih Anggaran --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
            <h3 class="text-[14px] font-bold text-gray-900 mb-4">Data Umum</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Pilih Pengajuan Anggaran <span class="text-red-500">*</span></label>
                    <select name="budget_request_id" id="budget_request_id" required
                        class="w-full px-3 py-2.5 text-[13px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none bg-white">
                        <option value="">-- Pilih Anggaran --</option>
                        @foreach($availableRequests as $br)
                        <option value="{{ $br->id }}"
                            data-total="{{ $br->total_amount }}"
                            data-items="{{ json_encode($br->items->map(fn($i) => ['id' => $i->id, 'uraian' => $i->description, 'type' => $i->type_label, 'anggaran' => $i->amount])) }}"
                            {{ old('budget_request_id', $selectedRequest?->id) == $br->id ? 'selected' : '' }}>
                            {{ $br->title }} — Rp {{ number_format($br->total_amount, 0, ',', '.') }}
                        </option>
                        @endforeach
                    </select>
                    @error('budget_request_id')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nomor LPJ</label>
                    <input type="text" name="nomor_lpj" value="{{ old('nomor_lpj') }}" placeholder="contoh: LPJ/2026/001"
                        class="w-full px-3 py-2.5 text-[13px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Catatan</label>
                    <textarea name="catatan" rows="2" placeholder="Catatan tambahan (opsional)"
                        class="w-full px-3 py-2.5 text-[13px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none">{{ old('catatan') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Tabel Rincian Realisasi --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-[14px] font-bold text-gray-900">Rincian Realisasi</h3>
                <button type="button" id="btn-add-item"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100">
                    <span class="material-symbols-outlined text-[16px]">add</span> Tambah Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead>
                        <tr class="bg-indigo-700 text-white text-[11px] font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-3 text-center w-8">#</th>
                            <th class="py-2.5 px-3 text-left min-w-[180px]">Uraian</th>
                            <th class="py-2.5 px-3 text-center min-w-[70px]">Satuan</th>
                            <th class="py-2.5 px-3 text-center" style="min-width:80px">Vol</th>
                            <th class="py-2.5 px-3 text-right min-w-[110px]">Anggaran</th>
                            <th class="py-2.5 px-3 text-right min-w-[110px]">Realisasi <span class="text-red-300">*</span></th>
                            <th class="py-2.5 px-3 text-right min-w-[100px]">Selisih</th>
                            <th class="py-2.5 px-3 text-left min-w-[100px]">Bukti</th>
                            <th class="py-2.5 px-3 text-left min-w-[120px]">Keterangan</th>
                            <th class="py-2.5 px-3 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        {{-- Rows will be injected by JS --}}
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-[12px]">
                            <td colspan="4" class="py-3 px-3 text-right text-gray-600">TOTAL</td>
                            <td class="py-3 px-3 text-right text-gray-700" id="total-anggaran">0</td>
                            <td class="py-3 px-3 text-right text-gray-700" id="total-realisasi">0</td>
                            <td class="py-3 px-3 text-right font-bold" id="total-selisih">0</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex gap-2 justify-end">
            <a href="{{ route('employee.lpj.index') }}" class="px-5 py-2.5 text-[13px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Batal</a>
            <button type="submit" class="px-5 py-2.5 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg hover:from-indigo-700 hover:to-indigo-600 shadow-sm">
                Ajukan LPJ
            </button>
        </div>
    </form>
</div>

<script>
let itemIndex = 0;

function formatRp(num) {
    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

function addRow(data = {}) {
    const tbody = document.getElementById('items-tbody');
    const i = itemIndex++;
    const anggaran = parseFloat(data.anggaran || 0);

    const tr = document.createElement('tr');
    tr.className = 'border-b border-gray-100 item-row';
    tr.dataset.index = i;
    tr.innerHTML = `
        <td class="py-2 px-3 text-center text-gray-400 row-no"></td>
        <td class="py-2 px-3">
            <input type="hidden" name="items[${i}][budget_request_item_id]" value="${data.id || ''}">
            <textarea name="items[${i}][uraian]" rows="2" required
                class="w-full px-2 py-1.5 text-[12px] border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none min-w-[160px] resize-y align-top" placeholder="Uraian kegiatan">${data.uraian || ''}</textarea>
        </td>
        <td class="py-2 px-3">
            <input type="text" name="items[${i}][satuan]" value="${data.satuan || data.type || ''}"
                class="w-full px-2 py-1.5 text-[12px] border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none min-w-[110px]" placeholder="Satuan">
        </td>
        <td class="py-2 px-3">
            <input type="number" name="items[${i}][volume]" value="${data.volume || 1}" min="0" step="any"
                class="w-full px-2 py-1.5 text-[12px] border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none text-center" style="min-width:64px" onchange="recalcRow(this)">
        </td>
        <td class="py-2 px-3">
            <input type="number" name="items[${i}][anggaran]" value="${anggaran}" min="0" step="any"
                class="item-anggaran w-full px-2 py-1.5 text-[12px] border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none text-right"
                onchange="recalcRow(this)" onkeyup="recalcRow(this)">
        </td>
        <td class="py-2 px-3">
            <input type="number" name="items[${i}][realisasi]" value="0" min="0" step="any" required
                class="item-realisasi w-full px-2 py-1.5 text-[12px] border border-indigo-300 rounded focus:ring-2 focus:ring-indigo-300 outline-none text-right bg-indigo-50/40"
                onchange="recalcRow(this)" onkeyup="recalcRow(this)">
        </td>
        <td class="py-2 px-3 text-right row-selisih font-bold text-[12px] text-gray-500">0</td>
        <td class="py-2 px-3">
            <input type="file" name="items[${i}][bukti_file]" accept="image/*,.pdf"
                class="text-[11px] text-gray-500 file:mr-1 file:py-1 file:px-2 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-600 file:text-[11px] file:font-semibold">
        </td>
        <td class="py-2 px-3">
            <input type="text" name="items[${i}][keterangan]" value="${data.keterangan || ''}"
                class="w-full px-2 py-1.5 text-[12px] border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none" placeholder="Ket.">
        </td>
        <td class="py-2 px-3 text-center">
            <button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-600">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    recalcTotals();
    renumberRows();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    recalcTotals();
    renumberRows();
}

function recalcRow(el) {
    const row = el.closest('tr');
    const anggaran = parseFloat(row.querySelector('.item-anggaran').value) || 0;
    const realisasi = parseFloat(row.querySelector('.item-realisasi').value) || 0;
    const selisih = anggaran - realisasi;
    const cell = row.querySelector('.row-selisih');
    cell.textContent = (selisih < 0 ? '-' : '') + 'Rp ' + Math.abs(Math.round(selisih)).toLocaleString('id-ID');
    cell.className = 'py-2 px-3 text-right row-selisih font-bold text-[12px] ' + (selisih < 0 ? 'text-red-600' : 'text-emerald-600');
    recalcTotals();
}

function recalcTotals() {
    let totalA = 0, totalR = 0;
    document.querySelectorAll('.item-anggaran').forEach(el => totalA += parseFloat(el.value) || 0);
    document.querySelectorAll('.item-realisasi').forEach(el => totalR += parseFloat(el.value) || 0);
    const sisa = totalA - totalR;
    document.getElementById('total-anggaran').textContent = formatRp(totalA);
    document.getElementById('total-realisasi').textContent = formatRp(totalR);
    const siEl = document.getElementById('total-selisih');
    siEl.textContent = (sisa < 0 ? '-' : '') + formatRp(Math.abs(sisa));
    siEl.className = 'py-3 px-3 text-right font-bold ' + (sisa < 0 ? 'text-red-600' : 'text-emerald-600');
}

function renumberRows() {
    document.querySelectorAll('#items-tbody tr.item-row').forEach((tr, idx) => {
        tr.querySelector('.row-no').textContent = idx + 1;
    });
}

document.getElementById('btn-add-item').addEventListener('click', () => addRow());

document.getElementById('budget_request_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const items = JSON.parse(opt.dataset.items || '[]');
    document.getElementById('items-tbody').innerHTML = '';
    itemIndex = 0;
    if (items.length) {
        items.forEach(item => addRow(item));
    } else {
        addRow();
    }
});

// Init: load selected request items if pre-selected
@if($selectedRequest)
@php
    $preloadItems = $selectedRequest->items->map(fn($i) => [
        'id'       => $i->id,
        'uraian'   => $i->description,
        'type'     => $i->type_label,
        'anggaran' => $i->amount,
    ])->values();
@endphp
const preloadItems = @json($preloadItems);
preloadItems.forEach(item => addRow(item));
@else
addRow();
@endif
</script>
@endsection
