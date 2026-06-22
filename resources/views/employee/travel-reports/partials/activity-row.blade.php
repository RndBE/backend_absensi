@php
    $results = $activity['results'] ?? [''];
    if (! is_array($results) || count($results) === 0) {
        $results = [''];
    }
@endphp
<div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3" data-travel-activity data-index="{{ $index }}">
    <div class="flex items-center justify-between gap-3">
        <div class="text-[13px] font-black text-gray-900">Aktivitas</div>
        <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-bold text-red-600 border border-red-100" data-remove-activity>
            <span class="material-symbols-outlined text-[15px]">delete</span>
            Hapus
        </button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Tanggal</span>
            <input type="date" name="activities[{{ $index }}][date]" value="{{ $activity['date'] ?? '' }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
        </label>
        <label class="sm:col-span-2">
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Deskripsi</span>
            <input name="activities[{{ $index }}][description]" value="{{ $activity['description'] ?? '' }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Kegiatan yang dilakukan">
        </label>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3 mb-2">
            <span class="text-[11px] font-bold uppercase text-gray-400">Hasil</span>
            <button type="button" class="text-[12px] font-bold text-indigo-700" data-add-result>Tambah hasil</button>
        </div>
        <div class="space-y-2" data-results>
            @foreach($results as $resultIndex => $result)
                <input name="activities[{{ $index }}][results][{{ $resultIndex }}]" value="{{ $result }}" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Hasil kegiatan">
            @endforeach
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Kendala</span>
            <textarea name="activities[{{ $index }}][issues]" rows="2" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">{{ $activity['issues'] ?? '' }}</textarea>
        </label>
        <label>
            <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Kesimpulan Aktivitas</span>
            <textarea name="activities[{{ $index }}][conclusion]" rows="2" class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">{{ $activity['conclusion'] ?? '' }}</textarea>
        </label>
    </div>
    <div>
        <span class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Dokumen Aktivitas</span>
        <input type="file" name="activity_documents_{{ $index }}[]" multiple class="block w-full text-[13px] text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-[12px] file:font-bold file:text-indigo-700">
    </div>
</div>
