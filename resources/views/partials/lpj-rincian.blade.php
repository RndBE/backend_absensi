@php
    $sum = \App\Support\LpjSummary::for($lpj);
    $catatanMap = $lpj->kategori_notes ?? []; // catatan per kategori (mis. Reimbursement)
@endphp

{{-- ── PEMASUKAN (Anggaran Disetujui) ── --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px] text-emerald-600">savings</span>
        <h4 class="text-[14px] font-bold text-gray-900">Pemasukan (Anggaran Disetujui)</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead>
                <tr class="bg-emerald-600 text-white text-[11px] font-bold uppercase tracking-wider">
                    <th class="py-2.5 px-3 text-center w-8">No</th>
                    <th class="py-2.5 px-3 text-left min-w-[200px]">Uraian</th>
                    <th class="py-2.5 px-3 text-left min-w-[120px]">Kategori</th>
                    <th class="py-2.5 px-3 text-right min-w-[120px]">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sum['pemasukan'] as $i => $p)
                <tr class="border-b border-gray-100 {{ $i % 2 === 0 ? '' : 'bg-gray-50/30' }}">
                    <td class="py-2.5 px-3 text-center text-gray-400">{{ $i + 1 }}</td>
                    <td class="py-2.5 px-3 font-medium text-gray-800">{{ $p['uraian'] }}</td>
                    <td class="py-2.5 px-3 text-gray-500">{{ $p['kategori_label'] }}</td>
                    <td class="py-2.5 px-3 text-right font-semibold text-gray-700">{{ number_format($p['jumlah'], 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="py-4 px-3 text-center text-gray-400">Tidak ada rincian anggaran.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-emerald-50 border-t-2 border-emerald-100 font-bold">
                    <td colspan="3" class="py-3 px-3 text-right text-gray-600">TOTAL PEMASUKAN</td>
                    <td class="py-3 px-3 text-right text-emerald-700">{{ number_format($sum['total_pemasukan'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ── PENGELUARAN (Rincian Realisasi) ── --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-indigo-600">receipt_long</span>
            <h4 class="text-[14px] font-bold text-gray-900">Pengeluaran (Rincian Realisasi)</h4>
        </div>
        <div class="text-[12px] text-gray-500">{{ $sum['pengeluaran']->count() }} item</div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead>
                <tr class="bg-indigo-700 text-white text-[11px] font-bold uppercase tracking-wider">
                    <th class="py-2.5 px-3 text-center w-8">No</th>
                    <th class="py-2.5 px-3 text-left min-w-[120px]">Kategori</th>
                    <th class="py-2.5 px-3 text-left min-w-[200px]">Uraian</th>
                    <th class="py-2.5 px-3 text-center">Sat</th>
                    <th class="py-2.5 px-3 text-center">Vol</th>
                    <th class="py-2.5 px-3 text-right min-w-[120px]">Jumlah</th>
                    <th class="py-2.5 px-3 text-center min-w-[80px]">Bukti</th>
                    <th class="py-2.5 px-3 text-left min-w-[140px]">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sum['pengeluaran'] as $i => $item)
                <tr class="border-b border-gray-100 {{ $i % 2 === 0 ? '' : 'bg-gray-50/30' }}">
                    <td class="py-2.5 px-3 text-center text-gray-400">{{ $i + 1 }}</td>
                    <td class="py-2.5 px-3 text-gray-600">{{ $item->kategori_label }}</td>
                    <td class="py-2.5 px-3 font-medium text-gray-800">{{ $item->uraian }}</td>
                    <td class="py-2.5 px-3 text-center text-gray-600">{{ $item->satuan ?? '-' }}</td>
                    <td class="py-2.5 px-3 text-center text-gray-600">{{ number_format($item->volume, 0) }}</td>
                    <td class="py-2.5 px-3 text-right font-semibold text-gray-800">{{ number_format($item->realisasi, 0, ',', '.') }}</td>
                    <td class="py-2.5 px-3 text-center">
                        @if($item->bukti_file)
                        <a href="{{ asset('storage/' . $item->bukti_file) }}" target="_blank" class="inline-flex items-center gap-0.5 text-indigo-600 hover:underline text-[11px] font-semibold">
                            <span class="material-symbols-outlined text-[13px]">attach_file</span> Bukti
                        </a>
                        @else
                        <span class="text-gray-300 text-[11px]">-</span>
                        @endif
                    </td>
                    <td class="py-2.5 px-3 text-gray-600 text-[12px]">
                        {{ $catatanMap[$item->kategori] ?? '' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="py-4 px-3 text-center text-gray-400">Belum ada rincian pengeluaran.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-indigo-700 text-white font-bold">
                    <td colspan="5" class="py-3 px-3 text-right">TOTAL PENGELUARAN</td>
                    <td class="py-3 px-3 text-right">{{ number_format($sum['total_pengeluaran'], 0, ',', '.') }}</td>
                    <td colspan="2" class="py-3 px-3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
