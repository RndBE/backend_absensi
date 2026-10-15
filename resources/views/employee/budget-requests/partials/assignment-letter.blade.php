@php
    $letterSummaries = $assignmentLetters
        ->map(fn ($letter) => \App\Support\AssignmentLetterBudgetLink::summary($letter))
        ->keyBy('id');
    $selectedLetterId = (string) old('assignment_letter_id', $selectedLetterId ?? '');
    $shortDate = fn ($date) => \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('d M Y');
@endphp

<div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 space-y-3" data-assignment-letter-picker>
    <label class="block">
        <span class="block text-[12px] font-bold text-gray-700 mb-1">Surat Tugas <span class="font-semibold text-gray-400">(disarankan untuk perjalanan dinas)</span></span>
        <select name="assignment_letter_id" data-assignment-letter-select class="employee-native-field w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
            <option value="">— Tanpa surat tugas (isi manual) —</option>
            @foreach($letterSummaries as $summary)
                <option value="{{ $summary['id'] }}" @selected($selectedLetterId === (string) $summary['id'])>
                    {{ $summary['number'] }} — {{ \Illuminate\Support\Str::limit($summary['title'], 60) }} ({{ $shortDate($summary['departure_date']) }})
                </option>
            @endforeach
        </select>
    </label>

    @if($letterSummaries->isEmpty())
        <p class="text-[11.5px] text-gray-500">Belum ada surat tugas terbit atas nama Anda yang belum dipakai pengajuan lain. Surat tugas dibuat oleh HRD / GA&HSE.</p>
    @else
        <p class="text-[11.5px] text-gray-500" data-assignment-letter-hint>Pilih surat tugas supaya nomor surat, tanggal perjalanan, peserta, dan zona terisi otomatis.</p>
    @endif

    <div class="hidden rounded-lg border border-indigo-200 bg-white p-3 text-[12px] text-gray-700 space-y-1" data-assignment-letter-info>
        <div class="font-bold text-indigo-700" data-info-number></div>
        <div data-info-schedule></div>
        <div data-info-people></div>
        <div data-info-distance></div>
        <div class="text-[11px] text-gray-400">Data di atas mengikuti surat tugas dan tidak bisa diubah di sini. Satu surat tugas dipakai satu pengajuan; peserta lain memakai anggaran ini saat membuat LHP.</div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const picker = document.querySelector('[data-assignment-letter-picker]');
    if (!picker) return;

    const LETTERS = @json($letterSummaries);
    const select = picker.querySelector('[data-assignment-letter-select]');
    const info = picker.querySelector('[data-assignment-letter-info]');
    const form = picker.closest('form');
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const fmt = v => { const [y, m, d] = v.split('-'); return `${d} ${MONTHS[+m - 1]} ${y}`; };
    const lockedFields = ['surat_tugas_no', 'surat_tugas_date', 'departure_date', 'return_date'];

    function field(name) {
        return form?.querySelector(`[name="${name}"]`);
    }

    function setField(name, value, lock) {
        const el = field(name);
        if (!el) return;
        if (value !== undefined && value !== null) {
            el.value = value;
            // Memicu overlay date-shell (mm/dd/yyyy) ikut hilang.
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        el.readOnly = lock;
        el.classList.toggle('bg-gray-50', lock);
        el.classList.toggle('text-gray-500', lock);
    }

    function syncParticipants(letter) {
        const boxes = form?.querySelectorAll('input[name="participants[]"]') || [];
        const ids = letter ? letter.employees.map(e => String(e.employee_id)) : null;
        boxes.forEach(box => {
            if (ids) box.checked = ids.includes(box.value);
            // Checkbox tidak dikunci lewat "disabled" (nilainya tak ikut terkirim); server tetap
            // memakai peserta surat tugas, jadi di sini cukup dicegah diubah.
            box.onclick = letter ? (e => e.preventDefault()) : null;
            box.closest('label')?.classList.toggle('opacity-60', !!letter);
        });
        boxes[0]?.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function apply(initial) {
        const letter = LETTERS[select.value] || null;

        lockedFields.forEach(name => setField(name, letter ? {
            surat_tugas_no: letter.number,
            surat_tugas_date: letter.letter_date,
            departure_date: letter.departure_date,
            return_date: letter.return_date,
        }[name] : null, !!letter));

        syncParticipants(letter);

        if (letter) {
            const title = field('title');
            if (title && !title.value.trim()) title.value = letter.title;
            if (letter.distance_km !== null) {
                const distance = field('distance_km');
                if (distance) distance.value = letter.distance_km;
            }
            picker.querySelector('[data-info-number]').textContent = `${letter.number} · ${fmt(letter.letter_date)}`;
            picker.querySelector('[data-info-schedule]').textContent =
                `Berangkat ${fmt(letter.departure_date)}, kembali ${fmt(letter.return_date)} (${letter.working_days} hari kerja)`;
            picker.querySelector('[data-info-people]').textContent = 'Peserta: ' + letter.employees.map(e => e.name).join(', ');
            picker.querySelector('[data-info-distance]').textContent = letter.distance_km !== null
                ? `Lokasi terjauh ${letter.distance_km} km` + (letter.travel_zone ? ` · ${letter.travel_zone.name}` : '')
                : 'Jarak belum diisi di surat tugas.';
        }
        info.classList.toggle('hidden', !letter);
        picker.querySelector('[data-assignment-letter-hint]')?.classList.toggle('hidden', !!letter);

        // Form pengajuan baru punya estimator zona; beri tahu supaya kartu zona & uang makan ikut.
        if (!initial || letter) {
            document.dispatchEvent(new CustomEvent('assignment-letter:selected', { detail: letter }));
        }
    }

    select.addEventListener('change', () => apply(false));
    // Ditunda: skrip estimator zona di halaman dimuat setelah partial ini dan perlu sudah
    // memasang listener-nya saat pilihan lama (old input / edit) diterapkan.
    setTimeout(() => apply(true), 0);
})();
</script>
@endpush
