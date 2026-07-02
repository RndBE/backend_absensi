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
                        class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none [color-scheme:light]">
                        <option value="">-- Pilih Anggaran --</option>
                        @foreach($availableRequests as $br)
                        <option value="{{ $br->id }}"
                            data-total="{{ $br->total_amount }}"
                            data-items="{{ json_encode($br->items->map(fn($i) => ['uraian' => $i->description, 'kategori' => $i->type_label, 'anggaran' => $i->amount])) }}"
                            {{ old('budget_request_id', $selectedRequest?->id) == $br->id ? 'selected' : '' }}>
                            {{ $br->title }} - Rp {{ number_format($br->total_amount, 0, ',', '.') }}
                        </option>
                        @endforeach
                    </select>
                    @error('budget_request_id')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nomor LPJ</label>
                    <input type="text" name="nomor_lpj" value="{{ old('nomor_lpj') }}" placeholder="contoh: menggunakan nomor surat tugas"
                        class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none [color-scheme:light]">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Catatan</label>
                    <textarea name="catatan" rows="2" placeholder="Catatan tambahan (opsional)"
                        class="w-full px-3 py-2.5 text-[13px] bg-white text-gray-900 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none [color-scheme:light]">{{ old('catatan') }}</textarea>
                </div>
            </div>
        </div>

        {{-- PEMASUKAN (otomatis dari Pengajuan Anggaran, read-only) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-emerald-600">savings</span>
                <h3 class="text-[14px] font-bold text-gray-900">Pemasukan (Anggaran Disetujui)</h3>
                <span class="text-[11px] text-gray-400">— otomatis dari pengajuan anggaran</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead>
                        <tr class="bg-emerald-600 text-white text-[11px] font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-3 text-center w-8">#</th>
                            <th class="py-2.5 px-3 text-left min-w-[180px]">Uraian</th>
                            <th class="py-2.5 px-3 text-left min-w-[120px]">Kategori</th>
                            <th class="py-2.5 px-3 text-right min-w-[120px]">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody id="pemasukan-tbody">
                        <tr><td colspan="4" class="py-4 px-3 text-center text-gray-400 text-[12px]">Pilih pengajuan anggaran dulu.</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-emerald-50 border-t-2 border-emerald-100 font-bold text-[12px]">
                            <td colspan="3" class="py-3 px-3 text-right text-gray-600">TOTAL PEMASUKAN</td>
                            <td class="py-3 px-3 text-right text-emerald-700" id="total-pemasukan">Rp 0</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- PENGELUARAN: Rincian Realisasi (input bebas, berkategori) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-indigo-600">receipt_long</span>
                    <h3 class="text-[14px] font-bold text-gray-900">Pengeluaran (Rincian Realisasi)</h3>
                </div>
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
                            <th class="py-2.5 px-3 text-left min-w-[140px]">Kategori <span class="text-red-300">*</span></th>
                            <th class="py-2.5 px-3 text-left min-w-[180px]">Uraian <span class="text-red-300">*</span></th>
                            <th class="py-2.5 px-3 text-center min-w-[80px]">Satuan</th>
                            <th class="py-2.5 px-3 text-center" style="min-width:70px">Vol</th>
                            <th class="py-2.5 px-3 text-right min-w-[120px]">Jumlah <span class="text-red-300">*</span></th>
                            <th class="py-2.5 px-3 text-left min-w-[100px]">Bukti</th>
                            <th class="py-2.5 px-3 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody">
                        {{-- Rows injected by JS --}}
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-[12px]">
                            <td colspan="5" class="py-3 px-3 text-right text-gray-600">TOTAL PENGELUARAN</td>
                            <td class="py-3 px-3 text-right text-gray-700" id="total-realisasi">Rp 0</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Ringkasan saldo --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
            <div class="flex items-center justify-between text-[13px] mb-1.5">
                <span class="text-gray-500">Total Pemasukan</span>
                <span class="font-semibold text-gray-700" id="sum-pemasukan">Rp 0</span>
            </div>
            <div class="flex items-center justify-between text-[13px] mb-1.5">
                <span class="text-gray-500">Total Pengeluaran</span>
                <span class="font-semibold text-gray-700" id="sum-pengeluaran">Rp 0</span>
            </div>
            <div class="flex items-center justify-between text-[14px] pt-2 border-t border-gray-100">
                <span class="font-bold text-gray-700">Saldo</span>
                <span class="font-black" id="sum-saldo">Rp 0</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1.5">Saldo minus artinya pengeluaran melebihi anggaran (perlu reimbursement).</p>
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
const LPJ_CATEGORIES = @json(\App\Models\LpjItem::CATEGORIES);
let itemIndex = 0;
let totalPemasukan = 0;

function formatRp(num) {
    return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

function categoryOptions(selected = '') {
    let opts = '<option value="">-- pilih --</option>';
    for (const [key, label] of Object.entries(LPJ_CATEGORIES)) {
        opts += `<option value="${key}" ${selected === key ? 'selected' : ''}>${label}</option>`;
    }
    return opts;
}

// ── PEMASUKAN (read-only) ─────────────────────────────────────────────
function renderPemasukan(items, total) {
    const tbody = document.getElementById('pemasukan-tbody');
    totalPemasukan = parseFloat(total || 0) || 0;
    if (!items || !items.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="py-4 px-3 text-center text-gray-400 text-[12px]">Tidak ada rincian anggaran.</td></tr>';
    } else {
        tbody.innerHTML = items.map((it, idx) => `
            <tr class="border-b border-gray-100">
                <td class="py-2 px-3 text-center text-gray-400">${idx + 1}</td>
                <td class="py-2 px-3 text-gray-700">${(it.uraian || '').toString().replace(/</g,'&lt;')}</td>
                <td class="py-2 px-3 text-gray-500">${(it.kategori || '-').toString().replace(/</g,'&lt;')}</td>
                <td class="py-2 px-3 text-right font-semibold text-gray-700">${formatRp(parseFloat(it.anggaran || 0))}</td>
            </tr>`).join('');
    }
    document.getElementById('total-pemasukan').textContent = formatRp(totalPemasukan);
    recalcTotals();
}

// ── PENGELUARAN (input bebas) ─────────────────────────────────────────
function addRow(data = {}) {
    const tbody = document.getElementById('items-tbody');
    const i = itemIndex++;
    const tr = document.createElement('tr');
    tr.className = 'border-b border-gray-100 item-row';
    tr.dataset.index = i;
    tr.innerHTML = `
        <td class="py-2 px-3 text-center text-gray-400 row-no"></td>
        <td class="py-2 px-3">
            <select name="items[${i}][kategori]" required
                class="w-full px-2 py-1.5 text-[12px] bg-white text-gray-900 border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none [color-scheme:light] min-w-[130px]">
                ${categoryOptions(data.kategori || '')}
            </select>
        </td>
        <td class="py-2 px-3">
            <textarea name="items[${i}][uraian]" rows="2" required
                class="w-full px-2 py-1.5 text-[12px] bg-white text-gray-900 border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none [color-scheme:light] min-w-[160px] resize-y align-top" placeholder="Uraian pengeluaran">${data.uraian || ''}</textarea>
        </td>
        <td class="py-2 px-3">
            <input type="text" name="items[${i}][satuan]" value="${data.satuan || ''}"
                class="w-full px-2 py-1.5 text-[12px] bg-white text-gray-900 border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none [color-scheme:light] min-w-[70px]" placeholder="Satuan">
        </td>
        <td class="py-2 px-3">
            <input type="number" name="items[${i}][volume]" value="${data.volume || 1}" min="0" step="any"
                class="w-full px-2 py-1.5 text-[12px] bg-white text-gray-900 border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none [color-scheme:light] text-center" style="min-width:60px">
        </td>
        <td class="py-2 px-3">
            <input type="number" name="items[${i}][realisasi]" value="${data.realisasi || 0}" min="0" step="any" required
                class="item-realisasi w-full px-2 py-1.5 text-[12px] border border-indigo-300 rounded focus:ring-2 focus:ring-indigo-300 outline-none text-right bg-indigo-50/40"
                onchange="recalcTotals()" onkeyup="recalcTotals()">
        </td>
        <td class="py-2 px-3">
            <input type="file" name="items[${i}][bukti_file]" accept="image/*,.pdf"
                class="text-[11px] text-gray-500 file:mr-1 file:py-1 file:px-2 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-600 file:text-[11px] file:font-semibold">
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

function recalcTotals() {
    let totalR = 0;
    document.querySelectorAll('.item-realisasi').forEach(el => totalR += parseFloat(el.value) || 0);
    const saldo = totalPemasukan - totalR;

    document.getElementById('total-realisasi').textContent = formatRp(totalR);
    document.getElementById('sum-pemasukan').textContent = formatRp(totalPemasukan);
    document.getElementById('sum-pengeluaran').textContent = formatRp(totalR);
    const saldoEl = document.getElementById('sum-saldo');
    saldoEl.textContent = (saldo < 0 ? '-' : '') + formatRp(Math.abs(saldo));
    saldoEl.className = 'font-black ' + (saldo < 0 ? 'text-red-600' : 'text-emerald-600');
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
    renderPemasukan(items, opt.dataset.total);
});

// Init
@if($selectedRequest)
@php
    $preloadItems = $selectedRequest->items->map(fn($i) => [
        'uraian'   => $i->description,
        'kategori' => $i->type_label,
        'anggaran' => $i->amount,
    ])->values();
@endphp
renderPemasukan(@json($preloadItems), {{ (float) ($selectedRequest->total_amount ?? 0) }});
@endif

addRow();
</script>
@endsection
