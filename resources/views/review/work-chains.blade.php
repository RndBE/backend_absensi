<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Halaman bertoken: jangan sampai terindeks mesin pencari. --}}
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/title.ico') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <title>Tinjauan Rantai Kerja — {{ $reviewer->employee->full_name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-50">
<div class="max-w-5xl mx-auto px-4 py-6 sm:py-8">

    {{-- ── Kepala ── --}}
    <header class="mb-5">
        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">PT. Arta Teknologi Comunindo · Tinjauan</p>
        <h1 class="text-[20px] sm:text-[22px] font-bold text-gray-900 mt-1">Peta Rantai Kerja</h1>
        <p class="text-[13px] text-gray-500 mt-1">
            Halaman ini dibuka atas nama
            <strong class="text-gray-800">{{ $reviewer->employee->full_name }}</strong>
            — {{ $reviewer->employee->position }}, {{ $reviewer->employee->department?->name }}.
            Setiap perubahan tercatat dengan nama ini.
        </p>
        <p class="text-[12px] text-gray-400 mt-1.5">
            Berlaku sampai {{ $reviewer->expires_at->translatedFormat('j F Y') }} ·
            {{ $totals['chains'] }} rantai · {{ $totals['pairs'] }} pasangan · {{ $totals['people'] }} orang
        </p>
    </header>

    @if(session('success'))
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] text-emerald-800">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-800">
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-800">
        {{ $errors->first() }}
    </div>
    @endif

    <div class="mb-5 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4">
        <p class="text-[12px] text-indigo-800">
            Yang perlu Bapak/Ibu periksa: <strong>apakah pasangan di bawah benar-benar terjadi.</strong>
            Peta ini <strong>tidak menentukan nilai KPI siapa pun</strong> — hanya catatan alur kerja.
            Rantai yang menyentuh divisi Bapak/Ibu ditaruh paling atas.
        </p>
        <p class="text-[12px] text-indigo-800 mt-2">
            Baris <em>Diketahui</em> tidak perlu diisi — dihitung otomatis dari garis atasan tiap peserta.
            Halaman ini bisa <strong>menghapus pasangan yang salah</strong>,
            <strong>menambah yang terlewat</strong>, dan <strong>membuat rantai baru</strong> kalau ada
            alur kerja yang belum tercatat sama sekali. Menghapus rantai utuh sekaligus hanya bisa dari
            HRIS internal — kalau seluruh rantai memang salah, hapus pasangannya satu per satu.
        </p>
    </div>

    {{-- ── Rantai ── --}}
    @foreach($chains as $chain)
    <article id="rantai-{{ $chain['slug'] }}" class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 scroll-mt-4">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-[14px] font-bold text-gray-900">
                {{ $chain['label'] }}
                @if($chain['mine'])
                <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded align-middle">divisi Anda</span>
                @endif
            </h2>
            <p class="text-[11px] text-gray-400 mt-0.5">{{ count($chain['pairs']) }} pasangan</p>
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
                            <form action="{{ route('kpi-review.destroy-pair', ['token' => $token, 'kpiWorkRelation' => $row->id]) }}" method="POST"
                                onsubmit="return confirm('Hapus pasangan {{ $row->from?->full_name }} → {{ $row->to?->full_name }}? Tercatat atas nama {{ $reviewer->employee->full_name }}.');">
                                @csrf @method('DELETE')
                                <button type="submit" title="Pasangan ini tidak benar — hapus"
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

        <div class="px-5 py-3 border-t border-gray-100 bg-amber-50/40">
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Diketahui</span>
            @if($chain['overseers']->isEmpty())
            <p class="text-[11px] text-amber-800/70 mt-1">
                Seluruh atasan pesertanya sudah ikut serah terima langsung, jadi sudah tercantum di
                tabel di atas.
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
            @endif
        </div>

        <details class="border-t border-gray-100 rounded-b-xl" @if($focus === $chain['slug']) open @endif>
            <summary class="px-5 py-2.5 text-[12px] font-semibold text-indigo-600 cursor-pointer hover:bg-indigo-50 rounded-b-xl">
                <span class="material-symbols-outlined text-[15px] align-text-bottom">group_add</span>
                Ada pasangan yang terlewat? Tambahkan
            </summary>
            <form action="{{ route('kpi-review.add-pairs', ['token' => $token]) }}" method="POST"
                class="px-5 py-4 bg-gray-50/50 rounded-b-xl" data-pair-form>
                @csrf
                <input type="hidden" name="label" value="{{ $chain['label'] }}">
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
    <article id="rantai-baru" class="bg-white rounded-xl border-2 border-dashed border-indigo-200 shadow-sm mt-6 scroll-mt-4">
        <div class="px-5 py-3.5 border-b border-indigo-100">
            <h2 class="text-[14px] font-bold text-gray-900">Ada alur kerja yang belum tercatat?</h2>
            <p class="text-[11px] text-gray-400 mt-0.5">
                Namai prosesnya, bukan orangnya. Pasangan dibuat dari perkalian kedua sisi — 3 orang di
                kiri dan 2 di kanan menghasilkan 6 pasangan.
            </p>
        </div>
        <form action="{{ route('kpi-review.store', ['token' => $token]) }}" method="POST" class="px-5 py-4" data-pair-form>
            @csrf
            <div class="mb-4">
                <label for="rantai-baru-label" class="block text-[11px] font-semibold text-gray-600 mb-1">Nama rantai</label>
                <input type="text" name="label" id="rantai-baru-label" required maxlength="80"
                    value="{{ old('label') }}"
                    placeholder="misal: Serah terima dokumen proyek"
                    class="w-full sm:max-w-md px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500">
            </div>
            {{-- Pemilih dirender langsung, bukan dikloning: form ini selalu terbuka dan hanya di sini
                 old() perlu bekerja, karena inilah yang paling mungkin ditolak validasi. --}}
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

    {{-- ── Catatan perubahan, diperlihatkan apa adanya ── --}}
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm mt-6">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-[14px] font-bold text-gray-900">Catatan perubahan</h2>
            <p class="text-[11px] text-gray-400 mt-0.5">
                Siapa mengubah apa, termasuk dari HRIS internal. Kalau ada yang keliru, kabari HRD.
            </p>
        </div>
        @if($logs->isEmpty())
        <p class="px-5 py-6 text-[12px] text-gray-400 text-center">Belum ada perubahan.</p>
        @else
        <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
            @foreach($logs as $log)
            <div class="px-5 py-2.5 flex items-baseline justify-between gap-3 flex-wrap">
                <p class="text-[12px] text-gray-700 min-w-0">
                    <strong>{{ $log->actor?->full_name ?? 'Admin' }}</strong>
                    {{ $log->summary() }}
                    di <span class="font-semibold">{{ $log->label }}</span>
                    @if($log->source === \App\Models\KpiWorkChainEditLog::SOURCE_ADMIN)
                    <span class="ml-1 px-1 py-0.5 text-[10px] font-bold text-gray-500 bg-gray-100 rounded">HRIS internal</span>
                    @endif
                </p>
                <span class="text-[11px] text-gray-400 shrink-0 tabular-nums">{{ $log->created_at->translatedFormat('j M, H:i') }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    <p class="text-[11px] text-gray-400 mt-5 text-center">
        Tautan ini pribadi — mohon tidak diteruskan. Kalau perlu ditinjau orang lain, minta HRD
        menerbitkan tautan atas namanya sendiri supaya catatannya tetap jelas.
    </p>
</div>

{{-- Satu salinan pemilih orang, dikloning ke kartu yang dibuka. Sama seperti halaman admin:
     merender pemilih di setiap kartu berarti ratusan kotak centang sekaligus. --}}
<template id="picker-template">
    @include('admin.kpi.work-chains._side', ['side' => 'from', 'candidates' => $candidates])
    @include('admin.kpi.work-chains._side', ['side' => 'to', 'candidates' => $candidates])
</template>

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
                if (e.target.type === 'checkbox') { sync(); }
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

            if (! form.dataset.sync) { wireCounter(form); }
        }

        document.querySelectorAll('details').forEach(function (panel) {
            var form = panel.querySelector('[data-pair-form]');

            if (! form) { return; }

            if (panel.open) { fill(form); }

            panel.addEventListener('toggle', function () {
                if (panel.open) { fill(form); }
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
</body>
</html>
