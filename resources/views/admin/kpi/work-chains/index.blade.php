@extends('admin.layouts.app')
@section('title', 'Rantai Kerja')

@section('content')
<div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4">
    <p class="text-[12px] text-indigo-800">
        Peta alur kerja antar orang: siapa menyerahkan atau mengoordinasikan apa kepada siapa.
        <strong>Tidak menentukan nilai KPI siapa pun</strong> — yang menentukan penilaian adalah
        Matriks Relasi Kerja dan Penilai Silang. Karena itu peta ini aman diubah kapan saja,
        termasuk saat periode sedang berjalan.
    </p>
    <p class="text-[12px] text-indigo-800 mt-2">
        Isinya <strong>serah terima nyata</strong> saja. Atasan yang mengetahui koordinasi anak
        buahnya tidak dicatat sebagai pasangan — ditampilkan otomatis di baris
        <em>Diketahui</em> tiap rantai, diturunkan dari garis atasan sampai tingkat Manajer.
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[268px_minmax(0,1fr)] gap-4 items-start">

    {{-- ── Sisi kiri: daftar rantai ── --}}
    <aside class="min-w-0 lg:sticky lg:top-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h3 class="text-[13px] font-bold text-gray-900">Rantai kerja</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    {{ $totals['chains'] }} rantai · {{ $totals['pairs'] }} pasangan · {{ $totals['people'] }} orang
                </p>
            </div>

            @if($chains->isEmpty())
            <p class="px-4 py-6 text-[12px] text-gray-400 text-center">Belum ada rantai kerja.</p>
            @else
            <nav class="max-h-[60vh] overflow-y-auto divide-y divide-gray-50">
                @foreach($chains as $chain)
                <a href="#rantai-{{ $chain['slug'] }}"
                    class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-gray-50 transition-colors {{ $focus === $chain['slug'] ? 'bg-indigo-50' : '' }}">
                    <span class="text-[12px] font-semibold text-gray-700 truncate">{{ $chain['label'] }}</span>
                    <span class="shrink-0 px-1.5 py-0.5 text-[10px] font-bold text-gray-500 bg-gray-100 rounded tabular-nums">
                        {{ count($chain['pairs']) }}
                    </span>
                </a>
                @endforeach
            </nav>
            @endif

            <a href="#rantai-baru"
                class="block px-4 py-3 border-t border-gray-100 text-[12px] font-semibold text-indigo-600 hover:bg-indigo-50 transition-colors">
                <span class="material-symbols-outlined text-[15px] align-text-bottom">add</span>
                Rantai baru
            </a>
        </div>

        @if($totals['manual'] > 0)
        <div class="mt-3 bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-[11px] text-gray-500">
                <span class="font-bold text-gray-700 tabular-nums">{{ $totals['manual'] }}</span>
                pasangan ditambahkan lewat halaman ini
            </p>
        </div>
        @endif
    </aside>

    {{-- ── Sisi kanan: isi rantai ── --}}
    <div class="min-w-0">

        @foreach($chains as $chain)
        <article id="rantai-{{ $chain['slug'] }}" class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 scroll-mt-4">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="text-[14px] font-bold text-gray-900">{{ $chain['label'] }}</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        {{ count($chain['pairs']) }} pasangan
                        @if($chain['from_seeder'])
                        · <span class="text-gray-500">hasil konfirmasi manajemen</span>
                        @endif
                    </p>
                </div>
                <form action="{{ route('admin.kpi-work-chains.destroy-chain') }}" method="POST"
                    data-confirm="Hapus rantai &quot;{{ $chain['label'] }}&quot; beserta {{ count($chain['pairs']) }} pasangannya? Tidak bisa dibatalkan.@if($chain['from_seeder']) Sebagian berasal dari seeder dan akan kembali saat db:seed berikutnya kecuali dicabut juga dari $workChains.@endif">
                    @csrf @method('DELETE')
                    <input type="hidden" name="label" value="{{ $chain['label'] }}">
                    <button type="submit" class="px-2.5 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg cursor-pointer hover:bg-red-100">
                        Hapus rantai
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100">
                            <th class="px-5 py-2">Dari</th>
                            <th class="px-5 py-2">Ke</th>
                            <th class="px-5 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($chain['pairs'] as $row)
                        <tr>
                            <td class="px-5 py-2.5 align-top">
                                <span class="block font-semibold text-gray-800">{{ $row->from?->full_name ?? '—' }}</span>
                                <span class="block text-[11px] text-gray-400">
                                    {{ $row->from?->position ?: '—' }}
                                    @if($row->from?->department)<span class="ml-1 px-1 py-0.5 bg-gray-100 rounded text-[10px] font-bold">{{ $row->from->department->name }}</span>@endif
                                </span>
                            </td>
                            <td class="px-5 py-2.5 align-top">
                                <span class="block font-semibold text-gray-800">{{ $row->to?->full_name ?? '—' }}</span>
                                <span class="block text-[11px] text-gray-400">
                                    {{ $row->to?->position ?: '—' }}
                                    @if($row->to?->department)<span class="ml-1 px-1 py-0.5 bg-gray-100 rounded text-[10px] font-bold">{{ $row->to->department->name }}</span>@endif
                                </span>
                            </td>
                            <td class="px-5 py-2.5 align-top">
                                <form action="{{ route('admin.kpi-work-chains.destroy', $row->id) }}" method="POST"
                                    data-confirm="Hapus pasangan {{ $row->from?->full_name }} → {{ $row->to?->full_name }}? Tidak bisa dibatalkan.@if($row->isFromSeeder()) Pasangan ini dari seeder dan akan kembali saat db:seed berikutnya kecuali dicabut juga dari $workChains.@endif">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Hapus pasangan"
                                        class="px-2 py-1 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg cursor-pointer hover:bg-red-100">
                                        <span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Bukan data tersimpan: dihitung dari garis atasan tiap peserta, jadi Leader ikut
                 terbawa tanpa penanda manual. Lihat App\Support\KpiWorkChainOverseers. --}}
            <div class="px-5 py-3 border-t border-gray-100 bg-amber-50/40">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Diketahui</span>
                @if($chain['overseers']->isEmpty())
                {{-- Kosong bukan berarti tidak ada yang tahu: bisa jadi seluruh atasan peserta
                     memang sudah ikut serah terima, jadi sudah tampil di tabel di atas. Ditulis
                     terang-terangan supaya tidak terbaca seperti data hilang. --}}
                <p class="text-[11px] text-amber-800/70 mt-1">
                    Seluruh atasan pesertanya sudah ikut serah terima langsung, jadi sudah tercantum
                    di tabel di atas. Di atas mereka hanya ada Direksi, yang tidak ditampilkan.
                </p>
                @else
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @foreach($chain['overseers'] as $boss)
                    <span class="inline-flex items-baseline gap-1 px-2 py-0.5 bg-white border border-amber-200 rounded text-[11px]">
                        <span class="font-semibold text-gray-800">{{ $boss->full_name }}</span>
                        <span class="text-gray-400">{{ $boss->kpiLevel?->code === 'L2' ? 'Manajer' : 'Leader' }}</span>
                    </span>
                    @endforeach
                </div>
                <p class="text-[11px] text-amber-800/70 mt-1.5">
                    Atasan para peserta rantai ini — tidak ikut serah terima, tapi mengetahui koordinasinya.
                </p>
                @endif
            </div>

            {{-- Tambah pasangan: kedua sisi diminta sekaligus, karena pasangan lahir dari perkaliannya --}}
            <details class="border-t border-gray-100 rounded-b-xl" @if($focus === $chain['slug']) open @endif>
                <summary class="px-5 py-2.5 text-[12px] font-semibold text-indigo-600 cursor-pointer hover:bg-indigo-50 rounded-b-xl">
                    <span class="material-symbols-outlined text-[15px] align-text-bottom">group_add</span>
                    Tambah pasangan
                </summary>
                <form action="{{ route('admin.kpi-work-chains.add-pairs') }}" method="POST"
                    class="px-5 py-4 bg-gray-50/50 rounded-b-xl" data-pair-form>
                    @csrf
                    <input type="hidden" name="label" value="{{ $chain['label'] }}">
                    {{-- Pemilih diisi dari <template> saat dibuka — lihat catatan di skrip bawah --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" data-picker-slot></div>
                    <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                        <p class="text-[11px] text-gray-500" data-pair-count>Pilih kedua sisi.</p>
                        <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm cursor-pointer">
                            Tambahkan
                        </button>
                    </div>
                </form>
            </details>
        </article>
        @endforeach

        {{-- ── Rantai baru ── --}}
        <article id="rantai-baru" class="bg-white rounded-xl border-2 border-dashed border-indigo-200 shadow-sm scroll-mt-4">
            <div class="px-5 py-3.5 border-b border-indigo-100">
                <h3 class="text-[14px] font-bold text-gray-900">Rantai baru</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    Pasangan dibuat dari perkalian kedua sisi — 3 orang di kiri dan 2 di kanan menghasilkan 6 pasangan.
                </p>
            </div>
            <form action="{{ route('admin.kpi-work-chains.store') }}" method="POST" class="px-5 py-4" data-pair-form>
                @csrf
                <div class="mb-4">
                    <label for="new-label" class="block text-[11px] font-semibold text-gray-600 mb-1">Nama rantai</label>
                    <input type="text" name="label" id="new-label" required maxlength="80"
                        value="{{ old('label') }}"
                        placeholder="misal: Serah terima dokumen proyek"
                        class="w-full sm:max-w-md px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500">
                    <p class="text-[11px] text-gray-400 mt-1">Namai prosesnya, bukan orangnya — nama ini yang mengelompokkan pasangan jadi satu rantai.</p>
                </div>
                {{-- Form ini selalu terbuka, jadi pemilihnya dirender langsung: hanya di sini old()
                     perlu bekerja, karena inilah form yang paling mungkin ditolak validasi. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @include('admin.kpi.work-chains._side', ['side' => 'from', 'candidates' => $candidates, 'withOld' => true])
                    @include('admin.kpi.work-chains._side', ['side' => 'to', 'candidates' => $candidates, 'withOld' => true])
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-[11px] text-gray-500" data-pair-count>Pilih kedua sisi.</p>
                    <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm cursor-pointer">
                        Buat rantai
                    </button>
                </div>
            </form>
        </article>
    </div>
</div>

{{--
    Satu salinan pemilih untuk seluruh kartu rantai. Kalau setiap kartu merender pemilihnya
    sendiri, halaman ini memuat 2 × jumlah_karyawan × jumlah_rantai kotak centang — dengan 30
    karyawan dan 14 rantai sudah 900 kotak dan 1 MB HTML, dan tumbuh lurus seiring karyawan
    bertambah. Template dikloning hanya ke kartu yang benar-benar dibuka.
--}}
<template id="picker-template">
    @include('admin.kpi.work-chains._side', ['side' => 'from', 'candidates' => $candidates])
    @include('admin.kpi.work-chains._side', ['side' => 'to', 'candidates' => $candidates])
</template>

@push('scripts')
<script>
    (function () {
        var template = document.getElementById('picker-template');

        function wireFilters(scope) {
            scope.querySelectorAll('[data-filter]').forEach(function (box) {
                box.addEventListener('input', function () {
                    var q = box.value.trim().toLowerCase();
                    box.closest('[data-side]').querySelectorAll('[data-name]').forEach(function (row) {
                        row.classList.toggle('hidden', q !== '' && row.dataset.name.indexOf(q) === -1);
                    });
                });
            });
        }

        // Jumlah pasangan yang akan lahir ditampilkan sebelum disimpan: perkalian dua sisi mudah
        // meledak tanpa disadari, dan admin berhak tahu sebelum menekan tombol.
        function wireCounter(form) {
            var out = form.querySelector('[data-pair-count]');

            function sync() {
                var a = form.querySelectorAll('input[name="from[]"]:checked').length;
                var b = form.querySelectorAll('input[name="to[]"]:checked').length;

                out.textContent = (a === 0 || b === 0)
                    ? 'Pilih kedua sisi.'
                    : a + ' × ' + b + ' = ' + (a * b) + ' pasangan akan dibuat.';
            }

            form.addEventListener('change', function (e) {
                if (e.target.type === 'checkbox') {
                    sync();
                }
            });

            form.dataset.sync = '1';
            sync();
        }

        function fill(form) {
            var slot = form.querySelector('[data-picker-slot]');

            if (slot && ! slot.hasChildNodes()) {
                slot.appendChild(template.content.cloneNode(true));
                wireFilters(slot);
            }

            if (! form.dataset.sync) {
                wireCounter(form);
            }
        }

        // Kartu rantai: pemilih baru dipasang saat panelnya dibuka, bukan saat halaman dimuat.
        document.querySelectorAll('details').forEach(function (panel) {
            var form = panel.querySelector('[data-pair-form]');

            if (! form) {
                return;
            }

            if (panel.open) {
                fill(form);
            }

            panel.addEventListener('toggle', function () {
                if (panel.open) {
                    fill(form);
                }
            });
        });

        // Form rantai baru berada di luar <details> dan pemilihnya sudah dirender server.
        document.querySelectorAll('[data-pair-form]').forEach(function (form) {
            if (! form.closest('details')) {
                wireFilters(form);
                wireCounter(form);
            }
        });
    })();
</script>
@endpush
@endsection
