@extends('admin.layouts.app')
@section('title', 'Surat Tugas')

@section('content')
@php
    $statusColors = [
        'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
        'issued' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
    ];
    $years = range(now()->year, max(2026, now()->year - 3));
    $actionBtn = 'inline-flex items-center justify-center gap-1 h-8 px-2 text-[11px] font-semibold border rounded-lg transition-all';
@endphp

@if($canIssue)
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
        <div class="text-[13px] text-gray-600">
            <span class="material-symbols-outlined text-[18px] align-text-bottom text-indigo-500">tag</span>
            Nomor terakhir {{ $numbering['year'] }}: <strong class="text-gray-900">{{ $numbering['last'] }}</strong>
            <span class="text-gray-400">·</span>
            surat berikutnya: <strong class="text-gray-900 font-mono">{{ $numbering['next'] }}</strong>
        </div>
        <button type="button" onclick="document.getElementById('numberingForm').classList.toggle('hidden')"
                class="text-[12px] font-semibold text-indigo-600 hover:text-indigo-800 cursor-pointer">Atur nomor terakhir</button>
    </div>
    <form id="numberingForm" method="POST" action="{{ route('admin.assignment-letters.numbering') }}"
          class="{{ $errors->has('last_number') ? '' : 'hidden' }} px-5 pb-4 border-t border-gray-100 pt-3">
        @csrf @method('PUT')
        <p class="text-[12px] text-gray-500 mb-3">
            Dipakai sekali saat mulai memakai sistem: isi nomor surat tugas terakhir yang dibuat di Word
            (mis. <strong>70</strong> untuk 070/ST-ATC/IX/{{ $numbering['year'] }}). Surat pertama dari sistem akan melanjutkan nomor itu.
        </p>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Tahun</label>
                <input type="number" name="year" value="{{ old('year', $numbering['year']) }}" min="2000" max="2100" class="w-24 px-3 py-2 border border-gray-200 rounded-lg text-[13px]">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Nomor terakhir</label>
                <input type="number" name="last_number" value="{{ old('last_number', $numbering['last']) }}" min="0" max="9999" class="w-28 px-3 py-2 border border-gray-200 rounded-lg text-[13px]">
            </div>
            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 cursor-pointer"
                    data-confirm="Nomor surat tugas berikutnya akan melanjutkan dari angka ini. Lanjutkan?" data-confirm-variant="primary">Simpan</button>
        </div>
    </form>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">assignment</span> Surat Tugas</h3>
        @if($canManage)
        <a href="{{ route('admin.assignment-letters.create') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 shrink-0">
            <span class="material-symbols-outlined text-[14px]">add</span> Buat Surat Tugas
        </a>
        @endif
    </div>

    <div class="px-5 pt-4 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3 border-b-2 border-gray-200">
        {{-- Tanpa overflow-x-auto: -mb-[2px] di tab membuat kontainer ber-overflow vertikal
             dan memunculkan panah scroll. --}}
        <div class="flex gap-0 order-2 lg:order-1">
            @foreach(['' => 'Semua'] + \App\Models\AssignmentLetter::STATUSES as $key => $label)
                <a href="{{ route('admin.assignment-letters.index', array_merge(request()->except(['status', 'page']), $key ? ['status' => $key] : [])) }}"
                   class="px-4 py-2 text-[13px] font-semibold border-b-2 -mb-[2px] whitespace-nowrap transition-all duration-200
                          {{ (string) request('status', '') === (string) $key ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" class="order-1 lg:order-2 flex flex-wrap items-center gap-2 pb-3">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="relative">
                <span class="material-symbols-outlined text-[16px] text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2">search</span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor / nama / tujuan..."
                       class="pl-8 pr-3 py-1.5 text-[12px] border border-gray-200 rounded-lg focus:ring-1 focus:ring-indigo-300 focus:border-indigo-300 w-60">
            </div>
            {{-- Lebar inline: searchable-select hanya menyalin style.width ke dropdown-nya; kelas w-*
                 kalah oleh `.ss-wrapper { width: 100% }` yang dimuat setelah Tailwind. --}}
            <select name="year" class="text-[12px]" style="width: 9rem">
                <option value="">Semua tahun</option>
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected((string) request('year') === (string) $y)>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3 py-1.5 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer">Filter</button>
            @if(request('search') || request('year') || request('status'))
            <a href="{{ route('admin.assignment-letters.index') }}" class="px-3 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">Reset</a>
            @endif
        </form>
    </div>

    <div class="p-5">
        @if($letters->isEmpty())
        <div class="text-center py-10 text-gray-400">
            <span class="material-symbols-outlined text-[36px]">assignment</span>
            <p class="text-sm font-medium mt-2">Belum ada surat tugas</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="py-3 px-3">Nomor</th>
                        <th class="py-3 px-3">Karyawan</th>
                        <th class="py-3 px-3">Tujuan</th>
                        <th class="py-3 px-3">Jadwal</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($letters as $letter)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-all align-top">
                        <td class="py-3 px-3 whitespace-nowrap">
                            @if($letter->number)
                                <div class="text-[13px] font-semibold text-gray-900 font-mono">{{ $letter->number }}</div>
                            @elseif($estimate = $letter->estimatedNumber($draftLastNumbers[$letter->letter_date->year] ?? null))
                                <div class="text-[13px] font-semibold text-gray-400 font-mono" title="Nomor pasti diberikan saat surat diterbitkan">{{ $estimate }}</div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-amber-600">Perkiraan</div>
                            @else
                                <div class="text-[13px] font-semibold text-gray-400 font-mono">—</div>
                            @endif
                            <div class="text-[11px] text-gray-400">{{ $letter->letter_date->format('d M Y') }}</div>
                        </td>
                        <td class="py-3 px-3">
                            <div class="text-[13px] text-gray-800 max-w-[240px]">{{ $letter->employees->pluck('name')->take(3)->implode(', ') }}</div>
                            @if($letter->employees->count() > 3)
                                <div class="text-[11px] text-gray-400">+{{ $letter->employees->count() - 3 }} lainnya</div>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            <div class="text-[12.5px] text-gray-700 max-w-[280px] line-clamp-2">{{ $letter->destinations->first()?->activity }}</div>
                            @if($letter->destinations->count() > 1)
                                <div class="text-[11px] text-gray-400">{{ $letter->destinations->count() }} lokasi</div>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-[12px] text-gray-600 whitespace-nowrap">
                            {{ $letter->departure_date->format('d M') }} – {{ $letter->return_date->format('d M Y') }}
                            <div class="text-[11px] text-gray-400">{{ $letter->workingDays() }} hari kerja</div>
                        </td>
                        <td class="py-3 px-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusColors[$letter->status] ?? '' }}">
                                {{ $letter->statusLabel() }}
                            </span>
                            @if($letter->hasSignedFile())
                                <div class="mt-1 flex items-center gap-0.5 text-[11px] font-semibold text-emerald-700 whitespace-nowrap">
                                    <span class="material-symbols-outlined" style="font-size: 14px">check_circle</span> Sudah TTD
                                </div>
                            @elseif($letter->isIssued())
                                <div class="mt-1 text-[11px] text-gray-400 whitespace-nowrap">Belum unggah TTD</div>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            {{-- Semua tombol setinggi h-8. Ukuran ikon ditulis inline karena CSS layout
                                 memaksa .material-symbols-outlined 20px, yang membuat tombol berikon lebih tinggi. --}}
                            {{-- Tiga slot berlebar tetap (Detail · aksi sesuai status · Cetak) supaya tombol
                                 lurus antarbaris walau jumlah tombolnya berbeda. --}}
                            <div class="grid grid-cols-[76px_108px_92px] gap-1.5 justify-center whitespace-nowrap">
                                <a href="{{ route('admin.assignment-letters.show', $letter->id) }}" title="Lihat detail"
                                   class="{{ $actionBtn }} text-indigo-600 bg-indigo-50 border-indigo-200 hover:bg-indigo-100">
                                    <span class="material-symbols-outlined" style="font-size: 16px">visibility</span> Detail
                                </a>
                                @if($canManage && $letter->isDraft())
                                    <a href="{{ route('admin.assignment-letters.edit', $letter->id) }}" title="Ubah draft"
                                       class="{{ $actionBtn }} text-amber-700 bg-amber-50 border-amber-200 hover:bg-amber-100">
                                        <span class="material-symbols-outlined" style="font-size: 16px">edit</span> Ubah
                                    </a>
                                @elseif($letter->hasSignedFile())
                                    <a href="{{ route('admin.assignment-letters.signed.show', $letter->id) }}" target="_blank" title="Lihat surat yang sudah ditandatangani"
                                       class="{{ $actionBtn }} text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100">
                                        <span class="material-symbols-outlined" style="font-size: 16px">verified</span> Lihat TTD
                                    </a>
                                @elseif($canManage && $letter->isIssued())
                                    {{-- Pilih file langsung mengunggah; ganti/hapus file ada di halaman Detail. --}}
                                    <form method="POST" action="{{ route('admin.assignment-letters.signed.store', $letter->id) }}" enctype="multipart/form-data" data-signed-upload>
                                        @csrf
                                        <input type="file" name="signed_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                        <button type="button" title="Unggah hasil pindai surat yang sudah ditandatangani & diparaf (PDF/JPG/PNG, maks 10 MB)"
                                                class="{{ $actionBtn }} w-full text-violet-700 bg-violet-50 border-violet-200 hover:bg-violet-100 cursor-pointer">
                                            <span class="material-symbols-outlined" style="font-size: 16px">upload</span> <span data-label>Unggah TTD</span>
                                        </button>
                                    </form>
                                @else
                                    <span></span>
                                @endif
                                <a href="{{ route('admin.assignment-letters.print', $letter->id) }}" target="_blank" title="{{ $letter->isDraft() ? 'Pratinjau cetak' : 'Cetak surat' }}"
                                   class="{{ $actionBtn }} text-teal-700 bg-teal-50 border-teal-200 hover:bg-teal-100">
                                    <span class="material-symbols-outlined" style="font-size: 16px">print</span> {{ $letter->isDraft() ? 'Pratinjau' : 'Cetak' }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $letters->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Tombol unggah surat bertanda tangan: buka pemilih file, lalu kirim begitu file dipilih.
    document.querySelectorAll('form[data-signed-upload]').forEach(form => {
        const input = form.querySelector('input[type=file]');
        const button = form.querySelector('button');
        button.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            if (!input.files.length) return;
            button.disabled = true;
            button.classList.add('opacity-60');
            button.querySelector('[data-label]').textContent = 'Mengunggah…';
            form.submit();
        });
    });
</script>
@endpush
