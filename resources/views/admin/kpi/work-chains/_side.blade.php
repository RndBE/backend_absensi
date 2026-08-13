{{--
    Satu sisi pemilih orang untuk rantai kerja.

    Daftar centang, bukan <select multiple>: memilih beberapa nama di select multiple butuh
    tahan Ctrl dan mudah membatalkan pilihan sebelumnya tanpa sadar — persoalan nyata di sini,
    karena satu klik salah bisa menghasilkan puluhan pasangan.

    $side     'from' | 'to'
    $withOld  kembalikan pilihan sebelumnya setelah validasi gagal. Hanya untuk form rantai baru:
              partial ini juga dipakai sebagai isi <template> yang dikloning ke kartu rantai lain,
              dan centang bawaan di sana akan ikut terbawa ke rantai yang salah.
--}}
@php
    $isFrom = $side === 'from';
    $title = $isFrom ? 'Dari' : 'Ke';
    $hint = $isFrom ? 'yang menyerahkan atau mengoordinasikan' : 'yang menerima';
    $old = ($withOld ?? false)
        ? collect(old($side, []))->map(fn ($v) => (int) $v)->all()
        : [];
@endphp

<div data-side="{{ $side }}" class="min-w-0">
    <div class="flex items-baseline justify-between gap-2 mb-1">
        <label class="block text-[11px] font-semibold text-gray-600">
            {{ $title }}
            <span class="font-normal text-gray-400">— {{ $hint }}</span>
        </label>
    </div>

    <input type="text" data-filter placeholder="Cari nama…" aria-label="Cari nama di sisi {{ $title }}"
        class="w-full mb-2 px-3 py-1.5 border border-gray-300 rounded-lg text-[12px] outline-none focus:border-indigo-500">

    <div class="max-h-56 overflow-y-auto border border-gray-200 rounded-lg bg-white divide-y divide-gray-50">
        @forelse($candidates as $department => $people)
        <div>
            <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 sticky top-0">
                {{ $department }}
            </p>
            @foreach($people as $person)
            <label data-name="{{ strtolower($person->full_name.' '.$person->position) }}"
                class="flex items-start gap-2 px-3 py-1.5 cursor-pointer hover:bg-indigo-50/50">
                <input type="checkbox" name="{{ $side }}[]" value="{{ $person->id }}"
                    @checked(in_array($person->id, $old, true))
                    class="mt-0.5 w-3.5 h-3.5 shrink-0 rounded border-gray-300 text-indigo-600">
                <span class="min-w-0">
                    <span class="block text-[12px] font-semibold text-gray-800 truncate">{{ $person->full_name }}</span>
                    <span class="block text-[10px] text-gray-400 truncate">
                        {{ $person->position ?: '—' }}
                        @if($person->kpiLevel)<span class="font-bold">{{ $person->kpiLevel->code }}</span>@endif
                    </span>
                </span>
            </label>
            @endforeach
        </div>
        @empty
        <p class="px-3 py-4 text-[12px] text-gray-400 text-center">Belum ada karyawan yang bisa dipilih.</p>
        @endforelse
    </div>
</div>
