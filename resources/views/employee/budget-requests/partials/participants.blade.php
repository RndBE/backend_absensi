@php
    $selected = $selected ?? [];
@endphp
<div data-participant-picker>
    <div class="flex items-center justify-between gap-3 mb-2">
        <span class="block text-[12px] font-bold text-gray-600">Tag Tim <span class="font-semibold text-gray-400">(peserta perjalanan)</span></span>
        <span class="text-[11px] font-semibold text-gray-400"><span data-participant-count>{{ count($selected) }}</span> dipilih</span>
    </div>
    <p class="text-[11px] text-gray-400 mb-2">Anggota yang ditandai bisa memilih budget ini saat mengajukan LHP.</p>

    <div class="relative mb-2">
        <span class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-gray-400">search</span>
        <input
            type="text"
            data-participant-search
            placeholder="Cari nama karyawan..."
            autocomplete="off"
            class="employee-native-field w-full rounded-lg border border-gray-200 pl-9 pr-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
        >
    </div>

    <div class="max-h-44 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @forelse($employees as $participant)
                <label
                    data-participant-row
                    data-participant-name="{{ \Illuminate\Support\Str::lower($participant->full_name) }}"
                    class="flex min-h-10 items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-[13px] font-semibold text-gray-700 hover:border-indigo-200 hover:bg-indigo-50"
                >
                    <input
                        type="checkbox"
                        name="participants[]"
                        value="{{ $participant->id }}"
                        @checked(in_array($participant->id, $selected, true))
                        class="h-4 w-4 rounded border-gray-300 accent-indigo-600"
                    >
                    <span class="leading-4">{{ $participant->full_name }}</span>
                </label>
            @empty
                <p class="text-[12px] text-gray-400 px-1 py-2">Belum ada karyawan lain yang bisa ditandai.</p>
            @endforelse
        </div>
        <p class="hidden text-[12px] text-gray-400 px-1 py-2" data-participant-empty>Tidak ada karyawan yang cocok.</p>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-participant-picker]').forEach(function (picker) {
        if (picker.dataset.bound === '1') return;
        picker.dataset.bound = '1';

        var search = picker.querySelector('[data-participant-search]');
        var rows = Array.prototype.slice.call(picker.querySelectorAll('[data-participant-row]'));
        var emptyMsg = picker.querySelector('[data-participant-empty]');
        var countEl = picker.querySelector('[data-participant-count]');

        function updateCount() {
            if (countEl) countEl.textContent = picker.querySelectorAll('input[type="checkbox"]:checked').length;
        }

        if (search) {
            search.addEventListener('input', function () {
                var q = this.value.trim().toLowerCase();
                var visible = 0;
                rows.forEach(function (row) {
                    var match = !q || (row.getAttribute('data-participant-name') || '').indexOf(q) !== -1;
                    row.classList.toggle('hidden', !match);
                    if (match) visible++;
                });
                if (emptyMsg) emptyMsg.classList.toggle('hidden', visible > 0 || rows.length === 0);
            });
        }

        picker.addEventListener('change', updateCount);
        updateCount();
    });
})();
</script>
