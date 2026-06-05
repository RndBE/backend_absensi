@extends('admin.layouts.app')

@section('title', 'Arsip Foto Absensi')

@section('content')
@php
    $canManage = app(\App\Support\AdminPermission::class)->can($currentAdmin, 'attendance.manage');
    $statusStyles = [
        'processing' => 'bg-indigo-100 text-indigo-700',
        'ready' => 'bg-emerald-100 text-emerald-700',
        'archived' => 'bg-blue-100 text-blue-700',
        'failed' => 'bg-red-100 text-red-700',
        'pending' => 'bg-gray-100 text-gray-700',
    ];
    $statusLabels = [
        'processing' => 'Diproses',
        'ready' => 'ZIP siap download',
        'archived' => 'Sudah diarsipkan',
        'failed' => 'Gagal',
        'pending' => 'Menunggu',
    ];
@endphp

<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-[20px] font-bold text-gray-900">Arsip Foto Absensi</h2>
            <p class="text-[13px] text-gray-500 mt-1">Generate ZIP foto clock-in/out bulanan, download, lalu simpan link Drive setelah HRD upload manual.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-[14px] font-bold text-gray-800">Generate ZIP Bulanan</h3>
                <p class="text-[12px] text-gray-500 mt-0.5">Foto lokal baru dihapus setelah arsip ditandai sudah upload ke Drive.</p>
            </div>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)_auto] gap-4 items-end">
                <form method="GET" action="{{ route('admin.attendance-photo-archives.index') }}" class="space-y-1.5">
                    <label class="block text-[11px] uppercase font-bold text-gray-400 tracking-wide">Pilih Bulan</label>
                    <div class="flex items-center gap-2">
                        <input type="month" name="period" value="{{ $period }}" class="h-[42px] w-[170px] rounded-lg border border-gray-300 bg-white px-3 text-[13px] font-semibold text-gray-800 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                        <button type="submit" class="h-[42px] inline-flex items-center gap-1.5 px-4 text-[12.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[17px]">search</span>
                            Tampilkan
                        </button>
                    </div>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-lg border border-gray-200 px-4 py-3 bg-gray-50 min-h-[74px]">
                        <div class="text-[11px] uppercase font-bold text-gray-400 tracking-wide">Periode</div>
                        <div class="text-[18px] font-bold text-gray-900 mt-1">{{ \Illuminate\Support\Carbon::parse($period . '-01')->translatedFormat('F Y') }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 px-4 py-3 bg-gray-50 min-h-[74px]">
                        <div class="text-[11px] uppercase font-bold text-gray-400 tracking-wide">Foto lokal tersedia</div>
                        <div class="text-[18px] font-bold text-gray-900 mt-1">{{ number_format($photoCount, 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 px-4 py-3 bg-gray-50 min-h-[74px]">
                        <div class="text-[11px] uppercase font-bold text-gray-400 tracking-wide">Status periode</div>
                        <div class="mt-2">
                            @if($selectedArchive)
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold {{ $statusStyles[$selectedArchive->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $statusLabels[$selectedArchive->status] ?? $selectedArchive->status }}
                                </span>
                            @else
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">Belum dibuat</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('admin.attendance-photo-archives.generate') }}" class="xl:justify-self-end">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        <button type="submit" class="h-[42px] inline-flex items-center gap-1.5 px-4 text-[12.5px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer whitespace-nowrap">
                            <span class="material-symbols-outlined text-[17px]">folder_zip</span>
                            Generate ZIP
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[14px] font-bold text-gray-800">Daftar Arsip</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Bulan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Jumlah Foto</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Drive</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($archives as $archive)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3.5 border-b border-gray-100">
                                <div class="text-[13.5px] font-bold text-gray-900">{{ \Illuminate\Support\Carbon::parse($archive->period . '-01')->translatedFormat('F Y') }}</div>
                                <div class="text-[11px] text-gray-400">{{ $archive->zip_file_name ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3.5 border-b border-gray-100">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold {{ $statusStyles[$archive->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $statusLabels[$archive->status] ?? $archive->status }}
                                </span>
                                @if($archive->error_message)
                                    <div class="text-[11px] text-red-600 mt-1">{{ $archive->error_message }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ number_format($archive->photo_count, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 border-b border-gray-100">
                                @if($archive->drive_link)
                                    <a href="{{ $archive->drive_link }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[12px] font-semibold text-blue-600 hover:text-blue-700">
                                        <span class="material-symbols-outlined text-[15px]">open_in_new</span>
                                        Buka Link Drive
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                        <span class="material-symbols-outlined text-[13px]">cloud_off</span>
                                        Belum upload ke Drive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 border-b border-gray-100">
                                <div class="flex items-center justify-end gap-2">
                                    @if($archive->status === 'ready')
                                        @if($archive->zip_file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($archive->zip_file_path))
                                            <a href="{{ route('admin.attendance-photo-archives.download', $archive) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11.5px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition whitespace-nowrap">
                                                <span class="material-symbols-outlined text-[15px]">download</span>
                                                Download ZIP
                                            </a>
                                        @endif

                                        <button type="button" onclick="openUploadModal({{ $archive->id }}, '{{ e($archive->period) }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11.5px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer whitespace-nowrap">
                                            <span class="material-symbols-outlined text-[16px]">cloud_upload</span>
                                            Tandai Sudah Upload ke Drive
                                        </button>
                                    @elseif($archive->status === 'archived')
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 text-[11.5px] font-semibold text-emerald-700 bg-emerald-50 rounded-lg">
                                            <span class="material-symbols-outlined text-[15px]">check_circle</span>
                                            Foto lokal terhapus
                                        </span>
                                    @else
                                        <span class="text-[11px] text-gray-400">-</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-12 text-gray-400 text-sm">Belum ada arsip foto absensi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="uploadArchiveModal" class="hidden fixed inset-0 z-[90] items-center justify-center px-4">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" onclick="closeUploadModal()"></div>
    <div class="relative w-full max-w-lg rounded-xl bg-white shadow-2xl border border-gray-200 overflow-hidden">
        <form id="uploadArchiveForm" method="POST">
            @csrf
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-[15px] font-bold text-gray-900">Tandai Sudah Upload ke Drive</h3>
                <p class="text-[12px] text-gray-500 mt-1" id="uploadArchivePeriod">Masukkan Link Google Drive ZIP sebelum menghapus foto lokal.</p>
            </div>
            <div class="p-5 space-y-3">
                <label class="block text-[12px] font-bold text-gray-700">Link Google Drive ZIP</label>
                <input type="url" name="drive_link" required placeholder="Tempel link file ZIP dari Google Drive di sini" class="w-full h-[44px] rounded-lg border border-gray-300 px-3 text-[13px] text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500">
                <p class="text-[11px] leading-5 text-gray-500">Pastikan ZIP sudah diupload ke Drive HRD. Setelah disimpan, sistem akan menghapus foto clock-in/out lokal yang masuk manifest ZIP bulan ini.</p>
            </div>
            <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeUploadModal()" class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-[12px] font-bold text-white bg-red-600 rounded-lg hover:bg-red-700 transition cursor-pointer">Simpan Link Drive & Hapus Foto Lokal</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openUploadModal(archiveId, period) {
    const modal = document.getElementById('uploadArchiveModal');
    const form = document.getElementById('uploadArchiveForm');
    const periodText = document.getElementById('uploadArchivePeriod');
    form.action = '{{ url('/admin/attendance-photo-archives') }}/' + archiveId + '/mark-uploaded';
    periodText.textContent = 'Periode ' + period + ' - tempel link Drive setelah file ZIP selesai diupload.';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeUploadModal() {
    const modal = document.getElementById('uploadArchiveModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
@endpush
@endsection
