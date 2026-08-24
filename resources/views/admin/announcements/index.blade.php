@extends('admin.layouts.app')
@section('title', 'Pengumuman')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    {{-- Kiri: form terbit.
         `sticky` supaya form tetap terlihat saat daftar di kanan digulir — HR sering menulis
         pengumuman baru sambil melihat yang sudah pernah terbit. --}}
    <div class="md:col-span-1">
        <div class="md:sticky md:top-5 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-amber-50 to-white">
                <h3 class="flex items-center gap-2 text-[14px] font-black text-gray-900">
                    <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px]">campaign</span>
                    </span>
                    Terbitkan Pengumuman
                </h3>
                <p class="mt-1.5 text-[11px] text-gray-500">Tampil di Timeline dashboard seluruh karyawan.</p>
            </div>
            <form action="{{ route('admin.company.announcements.store') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 mb-1.5">Judul</label>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="150" required
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                           placeholder="Libur Bersama Idulfitri">
                    @error('title')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 mb-1.5">Isi</label>
                    <textarea name="body" rows="5" maxlength="5000" required
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 resize-none"
                              placeholder="Tuliskan pengumuman...">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 mb-1.5">Berlaku mulai</label>
                    <input type="date" name="published_at" value="{{ old('published_at') }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 [color-scheme:light]">
                    <p class="mt-1 text-[11px] text-gray-400">Kosongkan = tampil sekarang juga. Isi tanggal depan untuk menjadwalkan.</p>
                    @error('published_at')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>

                {{--
                    Lampiran dipanggil lewat baris ikon, panelnya baru muncul saat ikonnya
                    ditekan. Tiga isian yang selalu terbuka membuat form terasa panjang dan
                    terbaca sama wajibnya dengan judul — padahal sebagian besar pengumuman
                    tidak berlampiran sama sekali.

                    Panel dicari lewat form terdekat, bukan lewat id. Blok yang sama dipakai
                    ulang di setiap baris daftar untuk menyunting, jadi id akan bentrok.
                --}}
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50/60 p-3.5">
                    <div class="flex items-center gap-1.5 text-[11px] font-black text-gray-500 uppercase tracking-wide">
                        <span class="material-symbols-outlined text-[14px]">attach_file</span>
                        Lampiran
                        <span class="ml-auto font-semibold normal-case tracking-normal text-gray-400">opsional</span>
                    </div>

                    {{-- Tiap jenis lampiran punya warnanya sendiri: biru foto, merah berkas,
                         ungu tautan. Warnanya ditulis utuh di dalam larik, bukan disusun dari
                         potongan — Tailwind memindai berkas ini sebagai teks, dan kelas hasil
                         sambungan tidak akan pernah ikut terbangun. --}}
                    <div class="mt-2.5 flex items-center gap-2">
                        @foreach([
                            ['image', 'image', 'Foto', 'border-sky-200 bg-sky-50 text-sky-600 hover:border-sky-400 hover:bg-sky-100'],
                            ['file', 'description', 'Berkas', 'border-rose-200 bg-rose-50 text-rose-600 hover:border-rose-400 hover:bg-rose-100'],
                            ['link', 'link', 'Tautan', 'border-violet-200 bg-violet-50 text-violet-600 hover:border-violet-400 hover:bg-violet-100'],
                        ] as [$kunci, $ikon, $judul, $warna])
                            <button type="button" data-attachment-toggle="{{ $kunci }}" title="{{ $judul }}"
                                    class="group relative w-10 h-10 rounded-lg border {{ $warna }} flex items-center justify-center transition">
                                <span class="material-symbols-outlined text-[19px]">{{ $ikon }}</span>
                                {{-- Titik hijau = lampiran ini sudah diisi. Tanpa penanda, isian yang
                                     panelnya tertutup jadi tidak terlihat sudah terisi atau belum.
                                     Hijau dipakai untuk SEMUA jenis: warna ikon sudah membedakan
                                     jenisnya, jadi penanda terisi tidak perlu ikut berbeda-beda. --}}
                                <span data-attachment-dot class="hidden absolute -top-1 -right-1 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                            </button>
                        @endforeach
                        <span data-attachment-summary class="text-[11px] text-gray-400 truncate">Belum ada lampiran</span>
                    </div>

                    <div data-attachment-panel="image" class="hidden mt-3 pt-3 border-t border-gray-200">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Foto</label>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
                               class="w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-sky-100 file:text-sky-700 file:text-[12px] file:font-bold file:cursor-pointer hover:file:bg-sky-200">
                        <p class="mt-1 text-[11px] text-gray-400">JPG, PNG, WEBP &middot; maks 4 MB</p>
                        @error('image')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div data-attachment-panel="file" class="hidden mt-3 pt-3 border-t border-gray-200">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Berkas</label>
                        <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                               class="w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-rose-100 file:text-rose-700 file:text-[12px] file:font-bold file:cursor-pointer hover:file:bg-rose-200">
                        <p class="mt-1 text-[11px] text-gray-400">PDF, Word, Excel, PowerPoint &middot; maks 10 MB</p>
                        @error('file')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div data-attachment-panel="link" class="hidden mt-3 pt-3 border-t border-gray-200">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Tautan</label>
                        <input type="url" name="link_url" value="{{ old('link_url') }}" maxlength="2048"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[12.5px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                               placeholder="https://...">
                        @error('link_url')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <label class="flex items-start gap-2.5 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50 transition">
                    <input type="checkbox" name="is_pinned" value="1" class="mt-0.5 rounded border-gray-300">
                    <span>
                        <span class="block text-[12px] font-bold text-gray-800">Paku di puncak Timeline</span>
                        <span class="block text-[11px] text-gray-400">Selalu tampil paling atas, mengalahkan urutan tanggal.</span>
                    </span>
                </label>

                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                    <span class="material-symbols-outlined text-[17px]">send</span>
                    Terbitkan
                </button>
            </form>
        </div>
    </div>

    {{-- Kanan: daftar --}}
    <div class="md:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <span class="text-[14px] font-bold text-gray-900">Daftar Pengumuman</span>
                <span class="text-[11px] font-semibold text-gray-400">{{ $announcements->total() }} pengumuman</span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($announcements as $item)
                    @php
                        // Tiga keadaan yang mungkin, dan hanya satu yang berarti "sedang dibaca
                        // karyawan". Dihitung di sini supaya HR melihat sendiri yang mana.
                        $kedaluwarsa = $item->expires_at && $item->expires_at->lt($now);
                        $terjadwal = $item->published_at->greaterThan($now);
                        $tampil = $item->is_visible && ! $terjadwal && ! $kedaluwarsa;
                        $redup = ! $tampil;
                    @endphp
                    {{-- Pita kiri menandai keadaan tanpa memakan ruang: kuning dipaku, hijau
                         sedang tampil, biru terjadwal, abu disembunyikan atau kedaluwarsa. --}}
                    <div class="p-5 border-l-4 {{ $terjadwal ? 'border-l-blue-400' : ($redup ? 'border-l-gray-300 bg-gray-50/70' : ($item->is_pinned ? 'border-l-amber-400' : 'border-l-emerald-400')) }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-[14px] font-black {{ $redup ? 'text-gray-500' : 'text-gray-900' }}">{{ $item->title }}</h4>
                                    @if($item->is_pinned)
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-700 uppercase tracking-wide">
                                            <span class="material-symbols-outlined text-[12px]">push_pin</span> Dipaku
                                        </span>
                                    @endif
                                    {{-- Hanya keadaan yang MENYIMPANG dari normal yang dilencanai.
                                         Lencana "Tayang" untuk keadaan biasa cuma jadi hiasan. --}}
                                    @if(! $item->is_visible)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-bold text-gray-600">
                                            <span class="material-symbols-outlined text-[12px]">visibility_off</span> Disembunyikan
                                        </span>
                                    @elseif($terjadwal)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800">
                                            <span class="material-symbols-outlined text-[12px]">schedule</span>
                                            Mulai {{ $item->published_at->locale('id')->translatedFormat('j M Y') }}
                                        </span>
                                    @elseif($kedaluwarsa)
                                        <span class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-bold text-gray-600">Kedaluwarsa</span>
                                    @endif
                                </div>

                                <div class="mt-2 flex items-start gap-3">
                                    @if($item->image_path)
                                        {{-- Pratinjau kecil. Tanpa ini HR harus membuka form ubah
                                             hanya untuk mengingat foto apa yang dulu dilampirkan. --}}
                                        <img src="{{ route('admin.company.announcements.image', $item) }}" alt="" loading="lazy"
                                             class="w-16 h-16 rounded-lg object-cover border border-gray-200 bg-gray-50 shrink-0">
                                    @endif
                                    <p class="text-[12px] text-gray-600 leading-relaxed whitespace-pre-line min-w-0">{{ Str::limit($item->body, 200) }}</p>
                                </div>

                                @if($item->punyaLampiran())
                                    {{-- Penanda lampiran di daftar: sebelumnya tidak ada tanda apa pun,
                                         jadi tak mungkin tahu mana yang berlampiran tanpa membuka satu per satu. --}}
                                    {{-- Warna chip mengikuti warna ikon di form: biru foto, merah
                                         berkas, ungu tautan. Satu kode warna dipakai di seluruh
                                         halaman supaya jenis lampiran dikenali tanpa membaca teksnya. --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        @if($item->image_path)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 border border-sky-200 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                                <span class="material-symbols-outlined text-[12px]">image</span> Foto
                                            </span>
                                        @endif
                                        @if($item->file_path)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                                                <span class="material-symbols-outlined text-[12px]">description</span>
                                                <span class="truncate max-w-[160px]">{{ $item->file_name }}</span>
                                                <span class="text-rose-400">{{ $item->ukuranBerkas() }}</span>
                                            </span>
                                        @endif
                                        @if($item->link_url)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-violet-50 border border-violet-200 px-2 py-0.5 text-[10px] font-bold text-violet-700">
                                                <span class="material-symbols-outlined text-[12px]">link</span>
                                                <span class="truncate max-w-[160px]">{{ parse_url($item->link_url, PHP_URL_HOST) ?: $item->link_url }}</span>
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-400">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px]">schedule</span>
                                        {{-- Tanggal saja, tanpa jam. Sejak "berlaku mulai" jadi isian
                                             tanggal, jamnya selalu 00:00 dan cuma menyesatkan. --}}
                                        {{ $item->published_at->locale('id')->translatedFormat('j M Y') }}
                                    </span>
                                    @if($item->expires_at)
                                        <span class="inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[13px]">event_busy</span>
                                            sampai {{ $item->expires_at->locale('id')->translatedFormat('j M Y') }}
                                        </span>
                                    @endif
                                    @if($item->penulis)
                                        <span class="inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[13px]">person</span>
                                            {{ $item->penulis->full_name }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" data-edit-toggle="{{ $item->id }}"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-indigo-600 hover:bg-indigo-50 transition" title="Ubah">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                {{-- `data-confirm` memakai modal konfirmasi bersama yang sudah ada di
                                     admin/layouts/app.blade.php, bukan confirm() bawaan browser —
                                     dan bukan modal baru. Pesannya menyebut judulnya supaya yang
                                     terhapus tidak salah baris, dan menyebut akibat yang tidak bisa
                                     ditebak sendiri: berkas lampirannya ikut hilang permanen. --}}
                                <form action="{{ route('admin.company.announcements.destroy', $item) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Hapus"
                                            data-confirm="Hapus pengumuman &quot;{{ $item->title }}&quot;? Foto dan berkas lampirannya ikut terhapus permanen. Notifikasi yang sudah terkirim tidak ikut terhapus."
                                            data-confirm-text="Hapus"
                                            data-confirm-variant="danger"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-600 hover:bg-red-50 transition">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{--
                            Modal per-baris, BUKAN satu form dipakai bersama seperti di halaman
                            Tipe Cuti. Form ini dirender server lengkap dengan keadaan lampirannya
                            — pratinjau foto, nama berkas, kotak "Lepas" — dan menyuapkan semua itu
                            lewat JS berarti memindahkan pekerjaan yang sudah benar ke tempat yang
                            gampang tidak sinkron.

                            Beban DOM-nya tidak bertambah: form-form ini memang sudah ada di halaman
                            sejak dulu, hanya tersembunyi. `fixed` tidak terpotong oleh
                            `overflow-hidden` kartu induknya karena tidak ada leluhur ber-transform.
                        --}}
                        <div id="edit-{{ $item->id }}" data-edit-modal
                             class="hidden fixed inset-0 z-[90] items-center justify-center p-4"
                             role="dialog" aria-modal="true" aria-labelledby="edit-judul-{{ $item->id }}">
                            <div class="absolute inset-0 bg-gray-900/50" data-modal-close></div>

                            <div class="relative w-full max-w-lg max-h-[90vh] bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
                                    <div class="min-w-0">
                                        <h3 id="edit-judul-{{ $item->id }}" class="text-[14px] font-black text-gray-900 flex items-center gap-2">
                                            <span class="material-symbols-outlined text-[18px] text-indigo-500">edit</span>
                                            Ubah Pengumuman
                                        </h3>
                                        <p class="mt-0.5 text-[11px] text-gray-400 truncate">{{ $item->title }}</p>
                                    </div>
                                    <button type="button" data-modal-close
                                            class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition" title="Tutup">
                                        <span class="material-symbols-outlined text-[18px]">close</span>
                                    </button>
                                </div>

                                {{-- Isian menggulir sendiri; tombol Simpan menempel di bawah supaya
                                     tidak pernah jatuh di luar layar seperti pada versi inline. --}}
                                <form action="{{ route('admin.company.announcements.update', $item) }}" method="POST" enctype="multipart/form-data"
                                      class="flex flex-col min-h-0 flex-1">
                                    @csrf @method('PUT')
                                    <div class="min-h-0 flex-1 overflow-y-auto p-5 space-y-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Judul</label>
                                <input type="text" name="title" value="{{ $item->title }}" maxlength="150" required
                                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Isi</label>
                                <textarea name="body" rows="5" maxlength="5000" required
                                          class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 resize-none">{{ $item->body }}</textarea>
                            </div>
                            {{-- Baris ikon yang sama seperti form terbit. Yang sudah terpasang
                                 panelnya dibuka sejak awal (`data-attachment-open`), supaya lampiran
                                 lama tidak tersembunyi di balik ikon saat HR membuka form ubah. --}}
                            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50/60 p-3.5">
                                <div class="flex items-center gap-1.5 text-[11px] font-black text-gray-500 uppercase tracking-wide">
                                    <span class="material-symbols-outlined text-[14px]">attach_file</span>
                                    Lampiran
                                </div>

                                <div class="mt-2.5 flex items-center gap-2">
                                    @foreach([
                                        ['image', 'image', 'Foto', (bool) $item->image_path, 'border-sky-200 bg-sky-50 text-sky-600 hover:border-sky-400 hover:bg-sky-100'],
                                        ['file', 'description', 'Berkas', (bool) $item->file_path, 'border-rose-200 bg-rose-50 text-rose-600 hover:border-rose-400 hover:bg-rose-100'],
                                        ['link', 'link', 'Tautan', (bool) $item->link_url, 'border-violet-200 bg-violet-50 text-violet-600 hover:border-violet-400 hover:bg-violet-100'],
                                    ] as [$kunci, $ikon, $judul, $terpasang, $warna])
                                        <button type="button" data-attachment-toggle="{{ $kunci }}" title="{{ $judul }}"
                                                @if($terpasang) data-attachment-open @endif
                                                class="group relative w-10 h-10 rounded-lg border {{ $warna }} flex items-center justify-center transition">
                                            <span class="material-symbols-outlined text-[19px]">{{ $ikon }}</span>
                                            <span data-attachment-dot class="{{ $terpasang ? '' : 'hidden' }} absolute -top-1 -right-1 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                                        </button>
                                    @endforeach
                                    <span data-attachment-summary class="text-[11px] text-gray-400 truncate"></span>
                                </div>

                                {{--
                                    Tombol "Lepas" memakai pola peer: kotak centangnya `sr-only`
                                    (masih ada dan tetap terkirim, cuma tak terlihat), lalu
                                    tampilannya digerakkan `peer-checked:`. Nol JS, dan tetap
                                    kotak centang sungguhan — jadi nilainya ikut terkirim seperti
                                    biasa tanpa perlu input tersembunyi tambahan.

                                    Ditekan = berubah jadi merah pekat: tanda sudah "diacungkan",
                                    dan pelepasannya baru terjadi saat Simpan.
                                --}}
                                <div data-attachment-panel="image" class="{{ $item->image_path ? '' : 'hidden' }} mt-3 pt-3 border-t border-gray-200">
                                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Foto</label>
                                    @if($item->image_path)
                                        {{--
                                            Kotak centangnya jadi SAUDARA PERTAMA, bukan dibungkus di
                                            dalam label. `peer-checked:` hanya menjangkau saudara yang
                                            berada SESUDAH peer-nya — di susunan lama, pratinjaunya
                                            berada di depan sehingga tak bisa disembunyikan.

                                            Ditekan "Lepas": pratinjau langsung hilang dan berganti
                                            keterangan. Pelepasan sungguhannya baru terjadi saat Simpan;
                                            batal menyimpan berarti lampirannya tetap utuh.
                                        --}}
                                        <input type="checkbox" name="hapus_image" value="1"
                                               id="hapus-image-{{ $item->id }}" class="sr-only peer">

                                        <div class="mb-2 flex items-center gap-2 peer-checked:hidden">
                                            <img src="{{ route('admin.company.announcements.image', $item) }}" alt="" loading="lazy"
                                                 class="w-12 h-12 rounded-lg object-cover border border-gray-200 bg-white shrink-0">
                                            <span class="text-[11px] text-gray-500 flex-1">Terpasang. Unggah baru untuk menggantikan.</span>
                                            <label for="hapus-image-{{ $item->id }}"
                                                   class="inline-flex items-center gap-1 shrink-0 cursor-pointer rounded-md border border-rose-200 bg-white px-2 py-1 text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                                Lepas
                                            </label>
                                        </div>

                                        <div class="mb-2 hidden peer-checked:flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2">
                                            <span class="material-symbols-outlined text-[16px] text-rose-500 shrink-0">delete</span>
                                            <span class="text-[11px] text-rose-700 flex-1">Foto dilepas saat disimpan.</span>
                                            <label for="hapus-image-{{ $item->id }}"
                                                   class="inline-flex items-center gap-1 shrink-0 cursor-pointer rounded-md border border-rose-300 bg-white px-2 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition">
                                                <span class="material-symbols-outlined text-[13px]">undo</span>
                                                Batalkan
                                            </label>
                                        </div>
                                    @endif
                                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
                                           class="w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-sky-100 file:text-sky-700 file:text-[12px] file:font-bold file:cursor-pointer hover:file:bg-sky-200">
                                </div>
                                <div data-attachment-panel="file" class="{{ $item->file_path ? '' : 'hidden' }} mt-3 pt-3 border-t border-gray-200">
                                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Berkas</label>
                                    @if($item->file_path)
                                        <input type="checkbox" name="hapus_file" value="1"
                                               id="hapus-file-{{ $item->id }}" class="sr-only peer">

                                        <div class="mb-2 flex items-center gap-2 peer-checked:hidden">
                                            <span class="text-[11px] text-gray-500 flex-1 min-w-0 truncate">{{ $item->file_name }} ({{ $item->ukuranBerkas() }})</span>
                                            <label for="hapus-file-{{ $item->id }}"
                                                   class="inline-flex items-center gap-1 shrink-0 cursor-pointer rounded-md border border-rose-200 bg-white px-2 py-1 text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition">
                                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                                Lepas
                                            </label>
                                        </div>

                                        <div class="mb-2 hidden peer-checked:flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2">
                                            <span class="material-symbols-outlined text-[16px] text-rose-500 shrink-0">delete</span>
                                            <span class="text-[11px] text-rose-700 flex-1">Berkas dilepas saat disimpan.</span>
                                            <label for="hapus-file-{{ $item->id }}"
                                                   class="inline-flex items-center gap-1 shrink-0 cursor-pointer rounded-md border border-rose-300 bg-white px-2 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition">
                                                <span class="material-symbols-outlined text-[13px]">undo</span>
                                                Batalkan
                                            </label>
                                        </div>
                                    @endif
                                    <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                           class="w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-rose-100 file:text-rose-700 file:text-[12px] file:font-bold file:cursor-pointer hover:file:bg-rose-200">
                                </div>
                                <div data-attachment-panel="link" class="{{ $item->link_url ? '' : 'hidden' }} mt-3 pt-3 border-t border-gray-200">
                                    <div class="flex items-center gap-2 mb-1">
                                        <label class="text-[11px] font-bold text-gray-600 flex-1">Tautan</label>
                                        @if($item->link_url)
                                            {{-- Tautan tidak punya kotak centang "hapus" seperti berkas:
                                                 melepasnya cukup mengosongkan isiannya. Nilai asalnya
                                                 disimpan di `data-nilai-awal` supaya "Batalkan" bisa
                                                 mengembalikannya — tanpa itu, sekali dilepas alamatnya
                                                 hilang dari halaman dan harus diketik ulang. --}}
                                            <button type="button" data-clear-link
                                                    data-nilai-awal="{{ $item->link_url }}"
                                                    class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-white px-2 py-1 text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition">
                                                <span class="material-symbols-outlined text-[13px]" data-clear-link-icon>delete</span>
                                                <span data-clear-link-text>Lepas</span>
                                            </button>
                                        @endif
                                    </div>
                                    <input type="url" name="link_url" value="{{ $item->link_url }}" maxlength="2048"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[12.5px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                           placeholder="https://...">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 mb-1">Berlaku mulai</label>
                                <input type="date" name="published_at" value="{{ $item->published_at->format('Y-m-d') }}"
                                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 [color-scheme:light]">
                                <p class="mt-1 text-[11px] text-gray-400">Tanggal depan = belum tampil sampai hari itu tiba.</p>
                            </div>

                            <label class="flex items-center gap-2 text-[12px] font-semibold text-gray-700">
                                <input type="checkbox" name="is_pinned" value="1" @checked($item->is_pinned) class="rounded border-gray-300">
                                Paku di puncak Timeline
                            </label>

                            {{-- Saklar tampil/sembunyi. Ini pengganti tanggal kedaluwarsa yang
                                 dicabut: menurunkan pengumuman dari Timeline tanpa menghapusnya,
                                 sehingga isi dan berkas lampirannya tetap tersimpan. --}}
                            <label class="flex items-start gap-2.5 rounded-lg border {{ $item->is_visible ? 'border-emerald-200 bg-emerald-50/60' : 'border-gray-200 bg-gray-50' }} p-3 cursor-pointer transition">
                                <input type="checkbox" name="is_visible" value="1" @checked($item->is_visible) class="mt-0.5 rounded border-gray-300">
                                <span>
                                    <span class="block text-[12px] font-bold text-gray-800">Tampilkan di Timeline</span>
                                    <span class="block text-[11px] text-gray-400">Lepas centangnya untuk menurunkan tanpa menghapus — isi dan lampirannya tetap tersimpan.</span>
                                </span>
                            </label>
                                    </div>

                                    <div class="shrink-0 px-5 py-3.5 border-t border-gray-100 bg-gray-50 flex flex-wrap items-center gap-2">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                                            <span class="material-symbols-outlined text-[16px]">save</span> Simpan
                                        </button>
                                        <button type="button" data-modal-close
                                                class="px-4 py-2 text-[12px] font-bold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">Batal</button>
                                        <span class="text-[11px] text-gray-400">Menyunting tidak mengirim ulang notifikasi.</span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center text-[13px] text-gray-400">Belum ada pengumuman.</div>
                @endforelse
            </div>
        </div>
        <div class="mt-4">{{ $announcements->links() }}</div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    (function () {
        // Modal ubah. `hidden` dilepas dan `flex` dipasang bergantian karena wadahnya memakai
        // flexbox untuk memusatkan panelnya; kalau cuma `hidden` yang dilepas, panel menempel
        // di pojok kiri atas.
        function buka(modal) {
            tutupSemua();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Kunci gulir halaman di belakang, kalau tidak latar ikut bergeser saat isi
            // modal digulir sampai mentok.
            document.body.classList.add('overflow-hidden');
        }

        function tutup(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function tutupSemua() {
            document.querySelectorAll('[data-edit-modal]').forEach(tutup);
        }

        document.querySelectorAll('[data-edit-toggle]').forEach(function (tombol) {
            tombol.addEventListener('click', function () {
                var modal = document.getElementById('edit-' + tombol.dataset.editToggle);
                if (modal) { buka(modal); }
            });
        });

        // Latar gelap dan tombol Batal/silang memakai penanda yang sama.
        document.querySelectorAll('[data-modal-close]').forEach(function (pemicu) {
            pemicu.addEventListener('click', function () {
                var modal = pemicu.closest('[data-edit-modal]');
                if (modal) { tutup(modal); }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { tutupSemua(); }
        });

        // Tombol "Lepas" pada tautan bekerja dua arah: sekali tekan mengosongkan isian,
        // tekan lagi mengembalikan alamat asalnya. Sejalan dengan foto dan berkas —
        // tampilannya berubah seketika, tapi yang tersimpan baru berubah saat Simpan.
        document.querySelectorAll('[data-clear-link]').forEach(function (tombol) {
            var teks = tombol.querySelector('[data-clear-link-text]');
            var ikon = tombol.querySelector('[data-clear-link-icon]');

            tombol.addEventListener('click', function () {
                var isian = tombol.closest('form')?.querySelector('input[name="link_url"]');
                if (! isian) return;

                var dilepas = isian.value.trim() === '';
                isian.value = dilepas ? (tombol.dataset.nilaiAwal || '') : '';

                if (teks) { teks.textContent = dilepas ? 'Lepas' : 'Batalkan'; }
                if (ikon) { ikon.textContent = dilepas ? 'delete' : 'undo'; }

                // Picu 'input' supaya penanda terisi (titik hijau, ringkasan) ikut menyesuaikan.
                isian.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    })();

    (function () {
        // Semua pencarian dibatasi ke form terdekat, bukan lewat id. Blok lampiran yang sama
        // dipakai ulang di form terbit DAN di setiap baris daftar untuk menyunting, jadi id
        // pasti bentrok begitu daftarnya berisi lebih dari satu pengumuman.
        function ringkas(form) {
            var ringkasan = form.querySelector('[data-attachment-summary]');
            if (! ringkasan) return;

            var isi = [];
            var foto = form.querySelector('input[name="image"]');
            var berkas = form.querySelector('input[name="file"]');
            var tautan = form.querySelector('input[name="link_url"]');

            if (foto && foto.files.length) isi.push(foto.files[0].name);
            if (berkas && berkas.files.length) isi.push(berkas.files[0].name);
            if (tautan && tautan.value.trim()) isi.push('tautan');

            ringkasan.textContent = isi.length ? isi.join(' · ') : 'Belum ada lampiran';
        }

        function tandai(form, kunci) {
            var tombol = form.querySelector('[data-attachment-toggle="' + kunci + '"]');
            if (! tombol) return;

            var isian = form.querySelector(kunci === 'link' ? 'input[name="link_url"]' : 'input[name="' + kunci + '"]');
            var terisi = kunci === 'link'
                ? Boolean(isian && isian.value.trim())
                : Boolean(isian && isian.files.length);

            // Yang sudah punya lampiran tersimpan tetap bertitik walau belum diubah.
            var sudahAda = tombol.hasAttribute('data-attachment-open');
            var titik = tombol.querySelector('[data-attachment-dot]');
            if (titik) { titik.classList.toggle('hidden', ! (terisi || sudahAda)); }

            // Cincin hijau, bukan penggantian warna ikon: warna ikon menandai JENIS lampiran
            // dan tidak boleh berubah, sedangkan cincin menandai keadaan terisi.
            tombol.classList.toggle('ring-2', terisi);
            tombol.classList.toggle('ring-emerald-400', terisi);
            tombol.classList.toggle('ring-offset-1', terisi);
        }

        document.querySelectorAll('[data-attachment-toggle]').forEach(function (tombol) {
            var form = tombol.closest('form');
            if (! form) return;

            tombol.addEventListener('click', function () {
                var panel = form.querySelector('[data-attachment-panel="' + tombol.dataset.attachmentToggle + '"]');
                if (! panel) return;

                panel.classList.toggle('hidden');
                if (! panel.classList.contains('hidden')) {
                    var isian = panel.querySelector('input');
                    if (isian && isian.type === 'url') { isian.focus(); }
                }
            });
        });

        document.querySelectorAll('form').forEach(function (form) {
            ['image', 'file', 'link_url'].forEach(function (nama) {
                var isian = form.querySelector('input[name="' + nama + '"]');
                if (! isian) return;

                var kunci = nama === 'link_url' ? 'link' : nama;
                isian.addEventListener(isian.type === 'file' ? 'change' : 'input', function () {
                    tandai(form, kunci);
                    ringkas(form);
                });
                tandai(form, kunci);
            });
            ringkas(form);
        });
    })();
</script>
@endpush

