@php
    $results = $activity['results'] ?? [''];
    if (! is_array($results) || count($results) === 0) {
        $results = [''];
    }
    $existingDocuments ??= collect();
    $documents = collect($activity['existing_documents'] ?? [])
        ->map(fn ($documentId) => $existingDocuments->get((int) $documentId))
        ->filter();
    $removedDocumentIds = array_map('intval', $activity['remove_documents'] ?? []);
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
            <span class="employee-date-shell" data-employee-date-shell>
                <input type="date" name="activities[{{ $index }}][date]" value="{{ $activity['date'] ?? '' }}" required class="employee-native-field w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                <span class="employee-date-placeholder" data-date-placeholder>mm/dd/yyyy</span>
            </span>
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
        {{-- Nama isian memakai [] supaya baris bisa ditambah/dihapus tanpa mengurus index. --}}
        <div class="space-y-2" data-results>
            @foreach($results as $result)
                <div class="flex items-center gap-2" data-result-row>
                    <input name="activities[{{ $index }}][results][]" value="{{ $result }}" class="employee-native-field min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Hasil kegiatan">
                    <button type="button" data-remove-result aria-label="Hapus hasil" title="Hapus hasil"
                        class="{{ count($results) > 1 ? '' : 'hidden' }} inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-red-100 bg-white text-red-600 hover:bg-red-50">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
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
        @if($documents->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-2">
                @foreach($documents as $document)
                    @php
                        $documentUrl = asset('storage/'.$document->file_path);
                        $isImage = in_array(strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                        $isRemoved = in_array($document->id, $removedDocumentIds, true);
                    @endphp
                    <div class="w-20 overflow-hidden rounded-lg border border-gray-200 bg-white" data-existing-document>
                        <input type="hidden" name="activities[{{ $index }}][existing_documents][]" value="{{ $document->id }}">
                        <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="block h-20 bg-gray-100 transition-opacity {{ $isRemoved ? 'opacity-40' : '' }}" data-document-preview>
                            @if($isImage)
                                <img src="{{ $documentUrl }}" alt="{{ $document->caption ?: 'Dokumen aktivitas' }}" class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full items-center justify-center text-gray-400">
                                    <span class="material-symbols-outlined text-[24px]">description</span>
                                </span>
                            @endif
                        </a>
                        <label class="flex items-center gap-1.5 px-2 py-1.5 text-[11px] font-bold text-red-600 cursor-pointer">
                            <input type="checkbox" name="activities[{{ $index }}][remove_documents][]" value="{{ $document->id }}" @checked($isRemoved) class="h-3.5 w-3.5 accent-red-600" data-remove-document>
                            Hapus
                        </label>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-gray-500 mb-2">Dokumen lama tetap tersimpan. Centang Hapus untuk membuangnya saat disimpan; file baru akan ditambahkan.</p>
        @endif
        <input type="file" name="activity_documents_{{ $index }}[]" multiple class="block w-full text-[13px] text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-[12px] file:font-bold file:text-indigo-700">
    </div>
</div>
