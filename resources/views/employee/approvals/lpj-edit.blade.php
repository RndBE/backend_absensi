@extends('employee.layouts.app')
@section('title', 'Koreksi Realisasi LPJ')

@section('content')
@php
    $rows = $lpj->items->sortBy('id')->values();
    $old = old('items');
@endphp

<div class="space-y-4">
    <div>
        <a href="{{ route('employee.approvals.index') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Persetujuan Tim
        </a>
        <h1 class="text-[22px] font-black text-gray-900">Koreksi Realisasi LPJ</h1>
        <p class="text-[13px] text-gray-500 mt-1">Hanya nominal realisasi dan keterangan yang bisa diubah. Perubahan dicatat atas nama Anda dan dikirim ke pengaju. Setelah disimpan, lanjutkan dengan menyetujui atau menolak.</p>
    </div>

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] font-semibold text-red-700">
            {{ $errors->first() }}
        </div>
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

    <form method="POST" action="{{ route('employee.approvals.lpj.update', $lpj->id) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <h2 class="text-[15px] font-black text-gray-900">Item Realisasi</h2>

            <div class="space-y-3" id="lpjItems">
                @foreach($rows as $index => $item)
                    <div class="rounded-lg border border-gray-200 p-3 space-y-2">
                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="text-[13px] font-bold text-gray-900">{{ $item->uraian }}</div>
                                <div class="text-[11.5px] text-gray-500">{{ $item->kategori_label }}</div>
                            </div>
                            <div class="text-right text-[11.5px] text-gray-500">
                                Anggaran
                                <div class="text-[13px] font-bold text-gray-700">Rp {{ number_format((float) $item->anggaran, 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="block">
                                <span class="block text-[12px] font-bold text-gray-600 mb-1">Realisasi (Rp)</span>
                                <input type="number" name="items[{{ $index }}][realisasi]" min="0" step="1" required
                                    value="{{ $old[$index]['realisasi'] ?? (int) round((float) $item->realisasi) }}"
                                    class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                            </label>
                            <label class="block">
                                <span class="block text-[12px] font-bold text-gray-600 mb-1">Keterangan</span>
                                <input type="text" name="items[{{ $index }}][keterangan]" maxlength="500"
                                    value="{{ $old[$index]['keterangan'] ?? $item->keterangan }}"
                                    class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between border-t border-gray-100 pt-3 text-[13px]">
                <span class="font-bold text-gray-500">Total realisasi</span>
                <span class="font-black text-gray-900" data-lpj-total>Rp {{ number_format((float) $lpj->total_realisasi, 0, ',', '.') }}</span>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <label class="block">
                <span class="block text-[12px] font-bold text-gray-600 mb-1">Catatan koreksi <span class="font-normal text-gray-400">(opsional, ikut dikirim ke pengaju)</span></span>
                <textarea name="notes" rows="2" maxlength="1000" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Nota parkir hanya Rp 20.000, selisih dikembalikan">{{ old('notes') }}</textarea>
            </label>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employee.approvals.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-[12px] font-bold text-gray-700">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-[12px] font-bold text-white">Simpan Koreksi</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const list = document.getElementById('lpjItems');
        const totalEl = document.querySelector('[data-lpj-total]');
        if (!list || !totalEl) return;

        list.addEventListener('input', function () {
            let total = 0;
            list.querySelectorAll('input[name$="[realisasi]"]').forEach(function (input) {
                total += parseFloat(input.value) || 0;
            });
            totalEl.textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
        });
    })();
</script>
@endpush
