<div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3" data-budget-item>
    @php
        $item = $item ?? null;
        $itemId = old("items.{$index}.id", data_get($item, 'id'));
        $selectedType = old("items.{$index}.type", data_get($item, 'type'));
        $description = old("items.{$index}.description", data_get($item, 'description'));
        $amount = old("items.{$index}.amount", data_get($item, 'amount'));
        $attachments = $attachments ?? collect();
    @endphp
    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $itemId }}">
    <div class="flex items-center justify-between gap-3">
        <div class="text-[13px] font-black text-gray-900">
            {{ $itemId ? 'Item Biaya' : 'Item Baru' }}
        </div>
        <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-bold text-red-600 border border-red-100" data-remove-budget-item>
            <span class="material-symbols-outlined text-[15px]">delete</span>
            Hapus
        </button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Jenis</span>
            <select name="items[{{ $index }}][type]" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                @foreach($itemTypes as $value => $label)
                    <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="sm:col-span-2">
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Deskripsi</span>
            <input name="items[{{ $index }}][description]" value="{{ $description }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Tunjangan luar kota 2 hari">
        </label>
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Nominal</span>
            <input type="number" min="0" name="items[{{ $index }}][amount]" value="{{ $amount }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="0">
        </label>
    </div>
    @if($attachments->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($attachments as $att)
                <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-white px-2.5 py-1 text-[11.5px] font-semibold text-indigo-600">
                    <span class="material-symbols-outlined text-[14px]">attach_file</span>
                    {{ $att->file_name ?: 'Lampiran' }}
                </a>
            @endforeach
        </div>
    @endif
</div>
