@php
    /**
     * Timeline informasi kantor. Data dari \App\Support\DashboardTimeline.
     *
     * Alasan cuti sengaja tidak ada di sini dan tidak boleh ditambahkan — lihat catatan
     * privasi di DashboardTimeline.
     */
    $timelineEntries = $timeline['entries'] ?? [];
    $timelineCompany = $timeline['company'] ?? ['name' => config('app.name'), 'logo' => null];

    /**
     * Seluruh riwayat dikirim ke halaman, lalu dipotong per halaman di browser. Paginasi
     * sisi server ditolak di sini karena kartu ini menumpang di dashboard: menekan
     * "berikutnya" akan memuat ulang seluruh dashboard hanya untuk menggeser satu kartu.
     */
    // 6, bukan 4: kartunya sudah ringkas, dan 4 membuat riwayat 50-an kabar jadi 13 halaman.
    $timelinePerPage = 6;
    $timelinePages = (int) max(1, ceil(count($timelineEntries) / $timelinePerPage));
@endphp

{{--
    Kartu ini SELALU dirender, termasuk saat tidak ada data. Versi sebelumnya menyembunyikan
    seluruh section saat kosong, dan itu tidak bisa dibedakan dari fitur yang rusak.
--}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" id="timeline-card">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px] text-gray-500">event_note</span>
        <h2 class="text-[15px] font-bold text-gray-900">Timeline</h2>
    </div>

    @if(empty($timelineEntries))
        <div class="px-5 py-8 text-center">
            <span class="material-symbols-outlined text-[32px] text-gray-300">event_busy</span>
            <p class="mt-2 text-[13px] font-semibold text-gray-500">Belum ada kabar untuk ditampilkan</p>
            <p class="mt-1 text-[12px] text-gray-400">
                Belum ada riwayat izin/cuti/sakit, dan tidak ada yang berulang tahun
                sampai {{ \App\Support\DashboardTimeline::BIRTHDAY_LOOKAHEAD_DAYS }} hari ke depan.
            </p>
        </div>
    @endif

    {{--
        Tiap kabar berdiri sebagai kartu sendiri — berbingkai, membulat, di atas latar abu.
        Versi sebelumnya cuma memisah dengan garis tipis di dalam satu kartu besar, dan
        batas antar kabar jadi tidak terbaca saat satu tanggal memuat banyak nama.
    --}}
    {{-- Latar abu hanya dipasang bila ada isinya, supaya status kosong tidak diapit pita abu hampa. --}}
    <div class="{{ empty($timelineEntries) ? '' : 'bg-gray-50 p-3 sm:p-4 space-y-3' }}">
        @foreach($timelineEntries as $index => $entry)
            @php $entryPage = intdiv($index, $timelinePerPage) + 1; @endphp

            @if($entry['type'] === 'announcement')
                {{--
                    Pengumuman: satu-satunya entri yang punya judul dan isi bebas.

                    Kartunya BERLATAR PUTIH dengan pita kuning di tepi kiri, bukan seluruh
                    kartu berlatar kuning. Latar kuning penuh menurunkan kontras teks dan
                    membuat foto lampiran terlihat kotor; pita tepi memberi penanda yang sama
                    kuatnya tanpa mengorbankan keterbacaan.

                    Isinya dibagi bersekat supaya foto bisa melebar penuh ke tepi kartu —
                    kalau seluruh kartu dipadding seragam, foto selalu terkurung bingkai
                    dalam dan terlihat seperti tempelan.
                --}}
                <article class="js-timeline-entry {{ $entryPage === 1 ? '' : 'hidden' }} overflow-hidden rounded-xl border {{ $entry['pinned'] ? 'border-amber-300' : 'border-gray-200' }} border-l-4 border-l-amber-400 bg-white shadow-sm"
                         data-timeline-page="{{ $entryPage }}">
                    {{-- Padding bawah menyesuaikan apa yang menyusul: rapat bila masih ada foto
                         atau lampiran di bawahnya, penuh bila blok ini penutup kartunya. --}}
                    <div class="p-4 {{ $entry['image_url'] || $entry['file_url'] || $entry['link_url'] || $entry['author'] ? 'pb-3' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-[18px] text-amber-500 shrink-0">campaign</span>
                                <span class="text-[10px] font-black text-amber-600 uppercase tracking-[1px]">Pengumuman</span>
                                <span class="text-[11px] text-gray-300">&bull;</span>
                                <span class="text-[11px] text-gray-400 truncate">{{ $entry['relative_label'] }}</span>
                            </div>
                            @if($entry['pinned'])
                                <span class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black text-amber-700 uppercase tracking-wide shrink-0">
                                    <span class="material-symbols-outlined text-[11px]">push_pin</span>
                                    Dipaku
                                </span>
                            @endif
                        </div>

                        {{-- Judul jadi tokoh utama kartu: 16px font-black, naik dari 15px, dan
                             label "PENGUMUMAN" di atasnya diturunkan ke 10px supaya tidak bersaing. --}}
                        <h3 class="mt-2.5 text-[16px] font-black text-gray-900 leading-snug">{{ $entry['title'] }}</h3>
                        {{-- `whitespace-pre-line` supaya baris baru yang diketik HR tetap terjaga,
                             tanpa membuka celah HTML — isinya tetap di-escape Blade. --}}
                        <p class="mt-1.5 text-[13px] text-gray-600 leading-relaxed whitespace-pre-line">{{ $entry['body'] }}</p>
                    </div>

                    @if($entry['image_url'])
                        {{-- `loading="lazy"`: satu halaman bisa memuat beberapa pengumuman berfoto,
                             dan yang di bawah lipatan tidak perlu diunduh sebelum digulir ke sana. --}}
                        <img src="{{ $entry['image_url'] }}" alt="Lampiran {{ $entry['title'] }}" loading="lazy"
                             class="w-full max-h-80 object-cover border-y border-gray-100 bg-gray-50">
                    @endif

                    @if($entry['file_url'] || $entry['link_url'])
                        <div class="px-4 pt-3 {{ $entry['author'] ? '' : 'pb-4' }} flex flex-col gap-2">
                            @if($entry['file_url'])
                                {{-- Baris penuh, bukan pil kecil: nama berkas panjang-panjang dan
                                     akan terpotong tak terbaca kalau dijejalkan ke tombol sempit. --}}
                                <a href="{{ $entry['file_url'] }}"
                                   class="group flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 hover:border-amber-300 hover:bg-amber-50 transition">
                                    <span class="w-9 h-9 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center shrink-0 text-rose-600 transition">
                                        <span class="material-symbols-outlined text-[19px]">description</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[12.5px] font-bold text-gray-800 truncate">{{ $entry['file_name'] ?: 'Lampiran' }}</span>
                                        {{-- Ukuran ditampilkan supaya pembaca tahu apa yang akan diunduh
                                             sebelum menekannya, terutama saat memakai kuota. --}}
                                        <span class="block text-[11px] text-gray-400">{{ $entry['file_size'] ? $entry['file_size'].' · ' : '' }}Ketuk untuk mengunduh</span>
                                    </span>
                                    <span class="material-symbols-outlined text-[18px] text-gray-400 group-hover:text-amber-600 shrink-0 transition">download</span>
                                </a>
                            @endif

                            @if($entry['link_url'])
                                {{-- `rel="noopener noreferrer"` wajib untuk tautan luar bertarget _blank:
                                     tanpa itu halaman tujuan bisa menjangkau window pembukanya. --}}
                                <a href="{{ $entry['link_url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="group flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 hover:border-amber-300 hover:bg-amber-50 transition">
                                    <span class="w-9 h-9 rounded-lg bg-violet-50 border border-violet-200 flex items-center justify-center shrink-0 text-violet-600 transition">
                                        <span class="material-symbols-outlined text-[19px]">link</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[12.5px] font-bold text-gray-800">Buka Tautan</span>
                                        {{-- Alamat tujuannya ditampilkan: orang berhak tahu ke mana
                                             sebuah tautan membawanya sebelum menekannya. --}}
                                        <span class="block text-[11px] text-gray-400 truncate">{{ parse_url($entry['link_url'], PHP_URL_HOST) ?: $entry['link_url'] }}</span>
                                    </span>
                                    <span class="material-symbols-outlined text-[18px] text-gray-400 group-hover:text-amber-600 shrink-0 transition">open_in_new</span>
                                </a>
                            @endif
                        </div>
                    @endif

                    @if($entry['author'])
                        <div class="mt-3 px-4 py-2.5 border-t border-gray-100 bg-gray-50/60 flex items-center gap-1.5 text-[11px] text-gray-400">
                            <span class="material-symbols-outlined text-[13px]">person</span>
                            {{ $entry['author'] }}
                        </div>
                    @endif
                </article>
            @elseif($entry['type'] === 'birthday')
                <article class="js-timeline-entry {{ $entryPage === 1 ? '' : 'hidden' }} relative overflow-hidden rounded-xl border border-pink-100 bg-gradient-to-br from-pink-50 via-purple-50 to-blue-50 shadow-sm"
                         data-timeline-page="{{ $entryPage }}">
                    <div class="bg-gradient-to-r from-pink-100 via-purple-100 to-blue-100 p-4 flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-sm">
                            <span class="material-symbols-outlined text-[24px] text-red-500">cake</span>
                        </div>
                        <div class="text-[17px] sm:text-[19px] font-black text-gray-900 leading-tight tracking-tight">HAPPY<br>BIRTHDAY</div>
                    </div>

                    <div class="p-4">
                        <h3 class="text-[17px] font-black text-gray-900">Selamat Ulang Tahun</h3>
                        <div class="text-[12px] italic text-gray-400 mt-0.5">{{ $entry['relative_label'] }}</div>

                        <div class="mt-2 space-y-1 pr-12">
                            @foreach($entry['people'] as $person)
                                <p class="text-[13px] text-gray-700 leading-relaxed">
                                    <span class="font-bold text-gray-900">{{ $person['name'] }}</span>
                                    @if($person['department'])
                                        ({{ $person['department'] }})
                                    @endif
                                    ulang tahun pada {{ $entry['date_label'] }}
                                </p>
                            @endforeach
                        </div>
                    </div>

                    @if($timelineCompany['logo'])
                        {{-- Logo sudut sebagai penanda pengirim, sengaja pudar agar tidak menarik mata. --}}
                        <img src="{{ $timelineCompany['logo'] }}" alt="{{ $timelineCompany['name'] }}"
                             class="pointer-events-none absolute bottom-3 right-3 w-9 h-9 rounded-full bg-white/70 object-contain p-1 opacity-70">
                    @endif
                </article>
            @else
                <article class="js-timeline-entry {{ $entryPage === 1 ? '' : 'hidden' }} rounded-xl border border-gray-200 bg-white shadow-sm p-4"
                         data-timeline-page="{{ $entryPage }}">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl border border-gray-200 bg-white flex items-center justify-center shrink-0 overflow-hidden">
                            @if($timelineCompany['logo'])
                                <img src="{{ $timelineCompany['logo'] }}" alt="{{ $timelineCompany['name'] }}" class="w-full h-full object-contain p-1">
                            @else
                                <span class="material-symbols-outlined text-[22px] text-gray-400">domain</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-[14px] font-black text-gray-900 truncate">{{ $timelineCompany['name'] }}</div>
                            <div class="text-[12px] text-gray-400">{{ $entry['relative_label'] }}</div>
                        </div>
                    </div>

                    <p class="mt-3 text-[13px] text-gray-700 leading-relaxed">
                        Pada tanggal {{ $entry['date_short'] }}, terdapat karyawan yang melakukan izin / cuti / sakit :
                    </p>

                    <ul class="mt-2 space-y-1.5 list-disc pl-5">
                        @foreach($entry['people'] as $person)
                            <li class="text-[13px] text-gray-700 leading-relaxed">
                                <span class="font-bold text-gray-900">{{ $person['name'] }}</span>
                                @if($person['department'])
                                    <span class="text-gray-500">({{ $person['department'] }})</span>
                                @endif
                                - {{ $person['detail'] }}
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endif
        @endforeach
    </div>

    @if($timelinePages > 1)
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between gap-3">
            <button type="button" id="timeline-prev"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 text-[12px] font-bold text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                Sebelumnya
            </button>

            <div class="text-[12px] font-semibold text-gray-500">
                Halaman <span id="timeline-page">1</span> dari {{ $timelinePages }}
            </div>

            <button type="button" id="timeline-next"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 text-[12px] font-bold text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                Berikutnya
                <span class="material-symbols-outlined text-[16px]">chevron_right</span>
            </button>
        </div>

        <script>
            (function () {
                var totalPages = {{ $timelinePages }};
                var entries = document.querySelectorAll('#timeline-card .js-timeline-entry');
                var prev = document.getElementById('timeline-prev');
                var next = document.getElementById('timeline-next');
                var label = document.getElementById('timeline-page');
                var page = 1;

                function render() {
                    entries.forEach(function (el) {
                        el.classList.toggle('hidden', Number(el.dataset.timelinePage) !== page);
                    });
                    label.textContent = page;
                    prev.disabled = page === 1;
                    next.disabled = page === totalPages;
                }

                prev.addEventListener('click', function () {
                    if (page > 1) { page--; render(); }
                });
                next.addEventListener('click', function () {
                    if (page < totalPages) { page++; render(); }
                });

                render();
            })();
        </script>
    @endif
</section>
