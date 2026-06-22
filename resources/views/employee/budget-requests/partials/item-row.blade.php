<div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3" data-budget-item>
    <div class="flex items-center justify-between gap-3">
        <div class="text-[13px] font-black text-gray-900">Item Biaya</div>
        <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-bold text-red-600 border border-red-100" data-remove-budget-item>
            <span class="material-symbols-outlined text-[15px]">delete</span>
            Hapus
        </button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Jenis</span>
            <select name="items[{{ $index }}][type]" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                @foreach($itemTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="sm:col-span-2">
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Deskripsi</span>
            <input name="items[{{ $index }}][description]" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Contoh: Taksi bandara">
        </label>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Nominal</span>
            <input type="number" min="0" name="items[{{ $index }}][amount]" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="0">
        </label>
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Lampiran Item</span>
            <input type="file" name="item_attachments_{{ $index }}[]" multiple class="block w-full text-[13px] text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-[12px] file:font-bold file:text-indigo-700">
        </label>
    </div>
</div>
