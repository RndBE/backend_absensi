@extends('employee.layouts.app')
@section('title', 'Dana Transfer LPJ')

@section('content')
@php
    $sum = \App\Support\LpjSummary::for($lpj);

    $existingById = $lpj->disbursements->keyBy('id');

    // Isian sebelumnya bila validasi gagal, selain itu catatan yang sudah tersimpan.
    $initialRows = collect(old('disbursements', $lpj->disbursements->map(fn ($d) => [
        'id' => $d->id,
        'transferred_at' => $d->transferred_at?->format('Y-m-d'),
        'amount' => (float) $d->amount,
        'notes' => $d->notes,
    ])->values()->all()))
        ->map(function ($row) use ($existingById) {
            $record = $existingById->get((int) ($row['id'] ?? 0));
            $row['proof_url'] = $record?->proof_file ? asset('storage/'.$record->proof_file) : null;
            $row['proof_name'] = $record?->proof_file ? basename($record->proof_file) : null;
            $row['proof_is_image'] = $record?->proof_file
                ? in_array(strtolower(pathinfo($record->proof_file, PATHINFO_EXTENSION)), \App\Support\LpjPresenter::PROOF_IMAGE_EXTENSIONS, true)
                : false;

            return $row;
        })->values();
@endphp

<div class="space-y-4 max-w-3xl">
    <div>
        <a href="{{ route('employee.approvals.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Persetujuan Tim
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Dana Transfer LPJ</h1>
        <p class="text-[13px] text-gray-500 mt-1">Catat uang yang Anda transfer untuk LPJ ini. Tabel ini tampil terpisah di Excel LPJ dan tidak mengubah realisasi pengaju.</p>
    </div>

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Pengaju</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">{{ $lpj->employee?->full_name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Nomor LPJ</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">{{ $lpj->nomor_lpj ?: '-' }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Anggaran disetujui</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">Rp {{ number_format($sum['total_pemasukan'], 0, ',', '.') }}</div>
        </div>
        <div>
            <div class="text-[11px] font-bold uppercase text-gray-400">Realisasi pengaju</div>
            <div class="mt-1 text-[13px] font-semibold text-gray-900">Rp {{ number_format($sum['total_pengeluaran'], 0, ',', '.') }}</div>
        </div>
    </section>

    <form method="POST" action="{{ route('employee.approvals.lpj.disbursements.update', $lpj->id) }}" class="space-y-4" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-emerald-600">payments</span>
                    <h3 class="text-[14px] font-bold text-gray-900">Uang yang Ditransfer</h3>
                </div>
                <button type="button" id="btn-add-row"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100">
                    <span class="material-symbols-outlined text-[16px]">add</span> Tambah Baris
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead>
                        <tr class="bg-emerald-600 text-white text-[11px] font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-3 text-center w-8">#</th>
                            <th class="py-2.5 px-3 text-left min-w-[150px]">Tanggal <span class="text-red-200">*</span></th>
                            <th class="py-2.5 px-3 text-right min-w-[140px]">Jumlah <span class="text-red-200">*</span></th>
                            <th class="py-2.5 px-3 text-left min-w-[180px]">Keterangan</th>
                            <th class="py-2.5 px-3 text-left min-w-[200px]">Bukti Transfer</th>
                            <th class="py-2.5 px-3 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="rows-tbody"></tbody>
                    <tfoot>
                        <tr class="bg-emerald-50 border-t-2 border-emerald-100 font-bold text-[12px]">
                            <td colspan="2" class="py-3 px-3 text-right text-gray-600">TOTAL DITRANSFER</td>
                            <td class="py-3 px-3 text-right text-emerald-700" id="total-cell">Rp 0</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p id="empty-hint" class="hidden px-5 py-4 text-[12px] text-gray-400">Belum ada baris. Klik Tambah Baris, atau simpan kosong bila tidak ada transfer.</p>
            <p class="px-5 py-3 text-[11.5px] text-gray-400 border-t border-gray-100">Bukti transfer berupa foto (JPG, PNG, GIF, WEBP) atau PDF, maksimal 10 MB. Foto ikut tertanam di Excel LPJ.</p>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('employee.approvals.index') }}" class="px-4 py-2.5 text-[12px] font-bold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Batal</a>
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 text-[12px] font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700">
                <span class="material-symbols-outlined text-[17px]">save</span> Simpan
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var initial = @json($initialRows);
    var tbody = document.getElementById('rows-tbody');
    var totalCell = document.getElementById('total-cell');
    var hint = document.getElementById('empty-hint');
    var inputCls = 'w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100';

    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function recalc() {
        var total = 0;
        tbody.querySelectorAll('[data-field="amount"]').forEach(function (el) { total += parseFloat(el.value) || 0; });
        totalCell.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
    }

    function renumber() {
        Array.prototype.forEach.call(tbody.rows, function (tr, i) {
            tr.querySelector('.no').textContent = i + 1;
            tr.querySelectorAll('[data-field]').forEach(function (el) {
                el.name = 'disbursements[' + i + '][' + el.dataset.field + ']';
            });
            var remove = tr.querySelector('.remove-proof');
            if (remove) { remove.name = 'disbursements[' + i + '][remove_proof]'; }
        });
        hint.classList.toggle('hidden', tbody.rows.length > 0);
        recalc();
    }

    function proofCell(row) {
        var html = '';
        if (row.proof_url) {
            html += '<div class="mb-1.5 flex items-center gap-2">' +
                (row.proof_is_image
                    ? '<a href="' + esc(row.proof_url) + '" target="_blank"><img src="' + esc(row.proof_url) + '" alt="Bukti" class="h-10 w-10 rounded border border-gray-200 object-cover"></a>'
                    : '<span class="material-symbols-outlined text-[18px] text-gray-400">picture_as_pdf</span>') +
                '<a href="' + esc(row.proof_url) + '" target="_blank" class="text-[11.5px] font-semibold text-emerald-700 hover:underline">Lihat bukti</a>' +
                '<label class="ml-auto inline-flex items-center gap-1 text-[11px] text-gray-500"><input type="checkbox" value="1" class="remove-proof"> Hapus</label>' +
                '</div>';
        }
        html += '<input type="file" data-field="proof_file" accept="image/*,application/pdf" class="block w-full text-[11.5px] text-gray-500 file:mr-2 file:rounded-md file:border-0 file:bg-emerald-50 file:px-2.5 file:py-1.5 file:text-[11.5px] file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100">';
        return html;
    }

    function addRow(row) {
        row = row || {};
        var tr = document.createElement('tr');
        tr.className = 'border-b border-gray-100';
        tr.innerHTML =
            '<td class="no py-2 px-3 text-center text-gray-400"></td>' +
            '<td class="py-2 px-3"><input type="date" required data-field="transferred_at" class="' + inputCls + '" value="' + esc(row.transferred_at) + '"></td>' +
            '<td class="py-2 px-3"><input type="number" required min="0" step="any" data-field="amount" class="' + inputCls + ' text-right" value="' + esc(row.amount) + '"></td>' +
            '<td class="py-2 px-3"><input type="text" maxlength="255" data-field="notes" class="' + inputCls + '" placeholder="Mis. transfer ke rekening BCA" value="' + esc(row.notes) + '"></td>' +
            '<td class="py-2 px-3 align-top">' + (row.id ? '<input type="hidden" data-field="id" value="' + esc(row.id) + '">' : '') + proofCell(row) + '</td>' +
            '<td class="py-2 px-3 text-center"><button type="button" class="btn-del text-gray-400 hover:text-red-600" aria-label="Hapus baris"><span class="material-symbols-outlined text-[18px]">delete</span></button></td>';
        tbody.appendChild(tr);
        renumber();
    }

    tbody.addEventListener('input', recalc);
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-del');
        if (btn) { btn.closest('tr').remove(); renumber(); }
    });
    document.getElementById('btn-add-row').addEventListener('click', function () {
        addRow({ transferred_at: new Date().toISOString().slice(0, 10) });
    });

    initial.forEach(addRow);
    renumber();
})();
</script>
@endsection
