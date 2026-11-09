@extends('employee.layouts.app')
@section('title', 'Edit Realisasi LPJ')

@section('content')
@php
    $sum = \App\Support\LpjSummary::for($lpj);
    $existingById = $lpj->items->keyBy('id');

    // Baris awal: isian sebelumnya bila validasi gagal, selain itu isi LPJ sekarang.
    $oldItems = old('items');
    $initialRows = collect($oldItems ?? $lpj->items->sortBy('sort_order')->map(fn ($item) => [
            'id' => $item->id,
            'kategori' => $item->kategori,
            'uraian' => $item->uraian,
            'satuan' => $item->satuan,
            'volume' => (float) $item->volume,
            'realisasi' => (float) $item->realisasi,
            'keterangan' => $item->keterangan,
        ])->values()->all())
        ->map(function ($row) use ($existingById) {
            $item = $existingById->get((int) ($row['id'] ?? 0));
            $row['bukti_url'] = $item?->bukti_file ? asset('storage/'.$item->bukti_file) : null;
            $row['bukti_is_image'] = $item?->bukti_file
                ? in_array(strtolower(pathinfo($item->bukti_file, PATHINFO_EXTENSION)), \App\Support\LpjPresenter::PROOF_IMAGE_EXTENSIONS, true)
                : false;

            return $row;
        })->values();
@endphp

<div class="space-y-4">
    <div>
        <a href="{{ route('employee.approvals.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Persetujuan Tim
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Edit Realisasi LPJ</h1>
        <p class="text-[13px] text-gray-500 mt-1">Ubah, tambah, atau hapus baris pengeluaran. Perubahan dicatat atas nama Anda dan dikirim ke pengaju. Setelah disimpan, lanjutkan dengan menyetujui atau menolak.</p>
    </div>

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Pengaju</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">{{ $lpj->employee?->full_name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Nomor LPJ</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">{{ $lpj->nomor_lpj ?: '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Anggaran</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">{{ $lpj->budgetRequest?->title ?? '-' }}</div>
        </div>
    </section>

    @include('lpj.partials.revisions', ['revisions' => $lpj->revisions])

    {{-- PEMASUKAN: hanya dibaca, bersumber dari pengajuan anggaran. --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-emerald-600">savings</span>
            <h3 class="text-[14px] font-bold text-gray-900">Pemasukan (Anggaran Disetujui)</h3>
            <span class="text-[11px] text-gray-400">— tidak bisa diubah</span>
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
                <tbody>
                    @forelse($sum['pemasukan'] as $i => $p)
                        <tr class="border-b border-gray-100">
                            <td class="py-2 px-3 text-center text-gray-400">{{ $i + 1 }}</td>
                            <td class="py-2 px-3 text-gray-700">{{ $p['uraian'] }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $p['kategori_label'] }}</td>
                            <td class="py-2 px-3 text-right font-semibold text-gray-700">Rp {{ number_format($p['jumlah'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 px-3 text-center text-gray-400">Tidak ada rincian anggaran.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-emerald-50 border-t-2 border-emerald-100 font-bold">
                        <td colspan="3" class="py-3 px-3 text-right text-gray-600">TOTAL PEMASUKAN</td>
                        <td class="py-3 px-3 text-right text-emerald-700">Rp {{ number_format($sum['total_pemasukan'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('employee.approvals.lpj.update', $lpj->id) }}" enctype="multipart/form-data" id="lpj-edit-form" class="space-y-4">
        @csrf
        @method('PUT')

        {{-- PENGELUARAN: tabel yang sama dengan form pembuatan LPJ, plus kolom keterangan. --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-indigo-600">receipt_long</span>
                    <h3 class="text-[14px] font-bold text-gray-900">Pengeluaran (Rincian Realisasi)</h3>
                </div>
                <button type="button" id="btn-add-item"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100">
                    <span class="material-symbols-outlined text-[16px]">add</span> Tambah Baris
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
                            <th class="py-2.5 px-3 text-left min-w-[160px]">Keterangan</th>
                            <th class="py-2.5 px-3 text-left min-w-[140px]">Bukti</th>
                            <th class="py-2.5 px-3 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody"></tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-[12px]">
                            <td colspan="5" class="py-3 px-3 text-right text-gray-600">TOTAL PENGELUARAN</td>
                            <td class="py-3 px-3 text-right text-gray-700" id="total-realisasi">Rp 0</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between text-[13px] mb-1.5">
                <span class="text-gray-500">Total Pemasukan</span>
                <span class="font-semibold text-gray-700">Rp {{ number_format($sum['total_pemasukan'], 0, ',', '.') }}</span>
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

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Catatan perubahan <span class="font-normal text-gray-400">(opsional, ikut dikirim ke pengaju)</span></span>
                <textarea name="notes" rows="2" maxlength="1000" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Parkir disesuaikan dengan nota, baris tol ditambahkan">{{ old('notes') }}</textarea>
            </label>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employee.approvals.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-[12px] font-bold text-gray-700">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-[12px] font-bold text-white">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const CATEGORIES = @json(\App\Models\LpjItem::CATEGORIES);
    const TOTAL_PEMASUKAN = {{ (float) $sum['total_pemasukan'] }};
    const INITIAL_ROWS = @json($initialRows);
    const tbody = document.getElementById('items-tbody');
    let index = 0;

    const fieldClass = 'w-full px-2 py-1.5 text-[12px] bg-white text-gray-900 border border-gray-200 rounded focus:ring-2 focus:ring-indigo-200 outline-none [color-scheme:light]';

    function esc(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatRp(num) {
        return 'Rp ' + Math.round(num).toLocaleString('id-ID');
    }

    function categoryOptions(selected) {
        let options = '<option value="">-- pilih --</option>';
        for (const [key, label] of Object.entries(CATEGORIES)) {
            options += '<option value="' + key + '"' + (selected === key ? ' selected' : '') + '>' + label + '</option>';
        }
        return options;
    }

    function proofMarkup(i, data) {
        if (!data.bukti_url) return '';
        const preview = data.bukti_is_image
            ? '<img src="' + esc(data.bukti_url) + '" alt="Bukti" class="h-14 w-14 rounded border border-gray-200 object-cover">'
            : '<span class="text-[11px] text-indigo-600 underline">Lihat bukti (PDF)</span>';

        return '<div class="mb-1">'
            + '<a href="' + esc(data.bukti_url) + '" target="_blank" class="inline-block">' + preview + '</a>'
            + '<label class="mt-1 flex items-center gap-1 text-[11px] text-gray-500">'
            + '<input type="checkbox" name="items[' + i + '][remove_bukti]" value="1"> Lepas bukti ini</label>'
            + '</div>';
    }

    function addRow(data) {
        data = data || {};
        const i = index++;
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-100 item-row align-top';
        tr.innerHTML =
            '<td class="py-2 px-3 text-center text-gray-400 row-no"></td>'
            + '<td class="py-2 px-3">'
            +   (data.id ? '<input type="hidden" name="items[' + i + '][id]" value="' + esc(data.id) + '">' : '')
            +   '<select name="items[' + i + '][kategori]" required class="' + fieldClass + ' min-w-[130px]">' + categoryOptions(data.kategori || '') + '</select>'
            + '</td>'
            + '<td class="py-2 px-3"><textarea name="items[' + i + '][uraian]" rows="2" required maxlength="255" class="' + fieldClass + ' min-w-[160px] resize-y" placeholder="Uraian pengeluaran">' + esc(data.uraian) + '</textarea></td>'
            + '<td class="py-2 px-3"><input type="text" name="items[' + i + '][satuan]" maxlength="50" value="' + esc(data.satuan) + '" class="' + fieldClass + ' min-w-[70px]" placeholder="Satuan"></td>'
            + '<td class="py-2 px-3"><input type="number" name="items[' + i + '][volume]" value="' + esc(data.volume ?? 1) + '" min="0" step="any" class="' + fieldClass + ' text-center" style="min-width:60px"></td>'
            + '<td class="py-2 px-3"><input type="number" name="items[' + i + '][realisasi]" value="' + esc(data.realisasi ?? 0) + '" min="0" step="any" required class="item-realisasi w-full px-2 py-1.5 text-[12px] border border-indigo-300 rounded focus:ring-2 focus:ring-indigo-300 outline-none text-right bg-indigo-50/40"></td>'
            + '<td class="py-2 px-3"><textarea name="items[' + i + '][keterangan]" rows="2" maxlength="255" class="' + fieldClass + ' min-w-[140px] resize-y" placeholder="Keterangan">' + esc(data.keterangan) + '</textarea></td>'
            + '<td class="py-2 px-3">' + proofMarkup(i, data)
            +   '<input type="file" name="items[' + i + '][bukti_file]" accept="image/*,.pdf" class="text-[11px] text-gray-500 file:mr-1 file:py-1 file:px-2 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-600 file:text-[11px] file:font-semibold">'
            + '</td>'
            + '<td class="py-2 px-3 text-center"><button type="button" data-remove-row class="text-red-400 hover:text-red-600" aria-label="Hapus baris"><span class="material-symbols-outlined text-[16px]">close</span></button></td>';
        tbody.appendChild(tr);
        refresh();
    }

    function refresh() {
        let total = 0;
        tbody.querySelectorAll('.item-realisasi').forEach(function (el) { total += parseFloat(el.value) || 0; });
        tbody.querySelectorAll('tr.item-row').forEach(function (tr, n) { tr.querySelector('.row-no').textContent = n + 1; });

        const saldo = TOTAL_PEMASUKAN - total;
        document.getElementById('total-realisasi').textContent = formatRp(total);
        document.getElementById('sum-pengeluaran').textContent = formatRp(total);
        const saldoEl = document.getElementById('sum-saldo');
        saldoEl.textContent = (saldo < 0 ? '-' : '') + formatRp(Math.abs(saldo));
        saldoEl.className = 'font-black ' + (saldo < 0 ? 'text-red-600' : 'text-emerald-600');
    }

    document.getElementById('btn-add-item').addEventListener('click', function () { addRow(); });

    tbody.addEventListener('input', refresh);
    tbody.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-row]');
        if (!button) return;

        if (tbody.querySelectorAll('tr.item-row').length <= 1) {
            alert('LPJ minimal punya satu baris pengeluaran.');
            return;
        }

        const row = button.closest('tr');
        const hasId = row.querySelector('input[name$="[id]"]');
        if (hasId && !confirm('Hapus baris ini dari LPJ? Buktinya ikut terhapus.')) return;

        row.remove();
        refresh();
    });

    INITIAL_ROWS.forEach(addRow);
    if (!INITIAL_ROWS.length) addRow();
})();
</script>
@endpush
