@extends('admin.layouts.app')
@section('title', 'Info Perusahaan')

@section('content')
<style>
    .company-info-shell {
        width: 100%;
    }

    .company-info-two-column {
        display: grid;
        gap: 24px;
        align-items: start;
    }

    .company-regulation-tabs {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #f9fafb;
    }

    .company-regulation-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 32px;
        padding: 0 12px;
        border-radius: 8px;
        color: #6b7280;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: all .18s ease;
    }

    .company-regulation-tab.is-active {
        background: #ffffff;
        color: #4f46e5;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
    }

    .company-status-choice input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .company-status-choice span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 36px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: #ffffff;
        color: #4b5563;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: all .18s ease;
    }

    .company-status-choice input:checked + span {
        border-color: #6366f1;
        background: #eef2ff;
        color: #4338ca;
    }

    @media (min-width: 1100px) {
        .company-info-two-column {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }
    }
</style>

@php
    $showCreateRegulation = $errors->has('title') || $errors->has('attachment') || $errors->has('attachments') || $errors->has('attachments.*');
    $showImportRegulation = $errors->has('import_file');
    $selectedCreateStatus = old('is_active', '1');
@endphp

<div class="company-info-shell space-y-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-500 flex items-center justify-center">
            <span class="material-symbols-outlined text-white text-[20px]">domain</span>
        </div>
        <div>
            <h2 class="text-lg font-bold text-gray-900">Info Perusahaan</h2>
            <p class="text-xs text-gray-500">Kelola informasi dasar perusahaan dan peraturan untuk karyawan</p>
        </div>
    </div>

    <div class="company-info-two-column">
    <form action="{{ route('admin.company.update') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        @csrf
        @method('PUT')

        <div class="p-6 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                <div class="w-20 h-20 rounded-xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden shrink-0" id="logo-preview-container">
                    <span class="material-symbols-outlined text-gray-400 text-[32px] {{ $company && $company->logo ? 'hidden' : '' }}" id="logo-placeholder">domain</span>
                    <img src="{{ $company && $company->logo ? asset('storage/' . $company->logo) : '' }}"
                         alt="Logo"
                         class="w-full h-full object-contain {{ $company && $company->logo ? '' : 'hidden' }}"
                         id="logo-preview"
                         onerror="this.classList.add('hidden'); document.getElementById('logo-placeholder')?.classList.remove('hidden');">
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-indigo-600 bg-indigo-50 rounded-lg border border-indigo-200 hover:bg-indigo-100 cursor-pointer transition">
                        <span class="material-symbols-outlined text-[16px]">upload</span>
                        Ganti Logo
                        <input type="file" name="logo" accept="image/*" class="hidden" onchange="previewLogo(this)">
                    </label>
                    <p class="text-[11px] text-gray-400 mt-1.5">Format: JPG, PNG. Maks 2MB</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nama Perusahaan <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $company->name ?? '') }}" required
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                    placeholder="Contoh: PT Arta Teknologi Comunindo">
                @error('name')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Alamat</label>
                <textarea name="address" rows="3"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition resize-none"
                    placeholder="Alamat lengkap perusahaan">{{ old('address', $company->address ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', $company->phone ?? '') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                        placeholder="021-xxxx-xxxx">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', $company->email ?? '') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                        placeholder="info@perusahaan.com">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">NPWP</label>
                <input type="text" name="npwp" value="{{ old('npwp', $company->npwp ?? '') }}"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition"
                    placeholder="xx.xxx.xxx.x-xxx.xxx">
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50/80 border-t border-gray-100 rounded-b-2xl flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-lg hover:from-indigo-700 hover:to-indigo-600 shadow-sm transition cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">save</span>
                Simpan Perubahan
            </button>
        </div>
    </form>

    <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-bold text-gray-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-indigo-500">rule</span>
                    Peraturan Perusahaan
                </h3>
            </div>
            <div class="company-regulation-tabs">
                <button type="button" id="regulationTabList" onclick="switchRegulationTab('list')" class="company-regulation-tab {{ $showCreateRegulation || $showImportRegulation ? '' : 'is-active' }}">
                    <span class="material-symbols-outlined text-[15px]">list_alt</span>
                    Daftar
                </button>
                <button type="button" id="regulationTabCreate" onclick="switchRegulationTab('create')" class="company-regulation-tab {{ $showCreateRegulation ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined text-[15px]">add</span>
                    Tambah Baru
                </button>
                <button type="button" id="regulationTabImport" onclick="switchRegulationTab('import')" class="company-regulation-tab {{ $showImportRegulation ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined text-[15px]">upload_file</span>
                    Import
                </button>
            </div>
        </div>

        <div id="regulationListPanel" class="{{ $showCreateRegulation || $showImportRegulation ? 'hidden' : '' }} divide-y divide-gray-100">
            @forelse($regulations as $regulation)
                @php
                    $regulationAttachments = $regulation->attachments;
                @endphp
                <article class="p-5">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-[14px] font-bold text-gray-900">{{ $regulation->title }}</h4>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $regulation->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                                    {{ $regulation->is_active ? 'Aktif' : 'Draft' }}
                                </span>
                            </div>
                            @if($regulation->effective_date || $regulationAttachments->isNotEmpty())
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-gray-500">
                                    @if($regulation->effective_date)
                                        <span>Berlaku {{ $regulation->effective_date->format('d/m/Y') }}</span>
                                    @endif
                                    @if($regulation->effective_date && $regulationAttachments->isNotEmpty())
                                        <span>&bull;</span>
                                    @endif
                                    @if($regulationAttachments->isNotEmpty())
                                        <span>{{ $regulationAttachments->count() }} lampiran</span>
                                    @endif
                                </div>
                            @endif
                            @if($regulation->content)
                                <p class="mt-3 text-[13px] leading-6 text-gray-600 whitespace-pre-line">{{ $regulation->content }}</p>
                            @endif
                            @if($regulationAttachments->isNotEmpty())
                                <div class="mt-3 flex flex-col gap-2">
                                    @foreach($regulationAttachments as $attachment)
                                        <a href="{{ route('admin.company.regulations.attachments.download', [$regulation, $attachment]) }}" class="inline-flex max-w-full items-center gap-2 text-[12px] font-semibold text-indigo-600 hover:text-indigo-700">
                                            <span class="material-symbols-outlined text-[16px] shrink-0">description</span>
                                            <span class="truncate">{{ $attachment->file_name ?: 'Lampiran ' . $loop->iteration }}</span>
                                            @if($attachment->file_size)
                                                <span class="text-gray-400 shrink-0">({{ number_format($attachment->file_size / 1024 / 1024, 2) }} MB)</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="toggleRegulationEdit('regulationEdit{{ $regulation->id }}')" class="inline-flex items-center px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">Edit</button>
                            <form method="POST" action="{{ route('admin.company.regulations.destroy', $regulation) }}" class="inline" data-confirm="Hapus peraturan perusahaan ini?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all cursor-pointer">Hapus</button>
                            </form>
                        </div>
                    </div>

                    <form id="regulationEdit{{ $regulation->id }}" action="{{ route('admin.company.regulations.update', $regulation) }}" method="POST" enctype="multipart/form-data" class="hidden mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Judul</label>
                            <input type="text" name="title" value="{{ $regulation->title }}" required class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13px] outline-none focus:border-indigo-500 bg-white">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Tanggal Berlaku</label>
                                <input type="date" name="effective_date" value="{{ $regulation->effective_date?->format('Y-m-d') }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13px] outline-none focus:border-indigo-500 bg-white [color-scheme:light]">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Status</label>
                                <select name="is_active" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13px] outline-none focus:border-indigo-500 bg-white">
                                    <option value="1" {{ $regulation->is_active ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ ! $regulation->is_active ? 'selected' : '' }}>Draft</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Isi</label>
                            <textarea name="content" rows="4" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-[13px] outline-none focus:border-indigo-500 bg-white resize-y">{{ $regulation->content }}</textarea>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Lampiran</label>
                            @if($regulationAttachments->isNotEmpty())
                                <div class="mb-3 space-y-2">
                                    @foreach($regulationAttachments as $attachment)
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border border-indigo-100 bg-white px-3 py-2.5" data-existing-attachment-row>
                                    <div class="flex items-center gap-2 min-w-0">
                                        <input type="hidden" name="delete_attachments[]" value="{{ $attachment->id }}" disabled data-existing-attachment-delete-input>
                                        <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-[18px]">description</span>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-[12px] font-bold text-gray-900 truncate">{{ $attachment->file_name ?: 'Lampiran peraturan' }}</p>
                                            <p class="text-[11px] text-gray-400">Lampiran saat ini{{ $attachment->file_size ? ' - ' . number_format($attachment->file_size / 1024 / 1024, 2) . ' MB' : '' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.company.regulations.attachments.download', [$regulation, $attachment]) }}" class="inline-flex items-center justify-center gap-2 px-3 py-1.5 text-[11px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition">
                                        <span class="material-symbols-outlined text-[15px]">download</span>
                                        Download
                                    </a>
                                    <button type="button" class="inline-flex items-center justify-center gap-1 px-3 py-1.5 text-[11px] font-bold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition" data-existing-attachment-delete>
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                        Hapus
                                    </button>
                                    </div>
                                    </div>
                                    @endforeach
                                </div>
                            @endif
                            <input type="file" name="attachments[]" accept=".pdf,.doc,.docx" multiple data-regulation-file-input data-file-list="regulationEditFiles{{ $regulation->id }}" class="block w-full text-[12px] text-gray-600 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-white file:text-indigo-700 file:font-semibold">
                            <div id="regulationEditFiles{{ $regulation->id }}" class="hidden mt-2 space-y-2"></div>
                            <p class="text-[11px] text-gray-400 mt-1">Pilih satu atau beberapa PDF/DOC/DOCX untuk ditambahkan.</p>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div></div>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                                <button type="button" onclick="toggleRegulationEdit('regulationEdit{{ $regulation->id }}')" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">close</span>
                                    Tutup
                                </button>
                                <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">save</span>
                                    Perbarui
                                </button>
                            </div>
                        </div>
                    </form>
                </article>
            @empty
                <div class="p-10 text-center">
                    <span class="material-symbols-outlined text-[32px] text-gray-300">rule_folder</span>
                    <p class="mt-2 text-[13px] text-gray-400">Belum ada peraturan perusahaan.</p>
                    <button type="button" onclick="switchRegulationTab('create')" class="mt-4 inline-flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        Buat Peraturan Pertama
                    </button>
                </div>
            @endforelse
        </div>

        <form id="regulationCreatePanel" action="{{ route('admin.company.regulations.store') }}" method="POST" enctype="multipart/form-data" class="{{ $showCreateRegulation ? '' : 'hidden' }} bg-gray-50/70 p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Judul Peraturan <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-white"
                    placeholder="Contoh: Tata Tertib Absensi">
                @error('title')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Tanggal Berlaku</label>
                    <input type="date" name="effective_date" value="{{ old('effective_date') }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-white [color-scheme:light]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Status</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="company-status-choice relative">
                            <input type="radio" name="is_active" value="0" {{ (string) $selectedCreateStatus === '0' ? 'checked' : '' }}>
                            <span>Draft</span>
                        </label>
                        <label class="company-status-choice relative">
                            <input type="radio" name="is_active" value="1" {{ (string) $selectedCreateStatus !== '0' ? 'checked' : '' }}>
                            <span>Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Isi Peraturan</label>
                <textarea name="content" rows="6"
                    class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition resize-y bg-white"
                    placeholder="Tuliskan isi atau ringkasan peraturan...">{{ old('content') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Lampiran</label>
                <input type="file" name="attachments[]" accept=".pdf,.doc,.docx" multiple data-regulation-file-input data-file-list="regulationCreateFiles"
                    class="block w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-semibold hover:file:bg-indigo-100">
                <div id="regulationCreateFiles" class="hidden mt-2 space-y-2"></div>
                <p class="text-[11px] text-gray-400 mt-1">PDF/DOC/DOCX, maks 25MB per file. Bisa pilih lebih dari satu lampiran.</p>
                @error('attachments')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
                @foreach($errors->get('attachments.*') as $messages)
                    @foreach($messages as $message)
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @endforeach
                @endforeach
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 pt-1">
                <button type="button" onclick="switchRegulationTab('list')" class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">Batal</button>
                <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">save</span>
                    Simpan Peraturan
                </button>
            </div>
        </form>

        <form id="regulationImportPanel" action="{{ route('admin.company.regulations.import') }}" method="POST" enctype="multipart/form-data" class="{{ $showImportRegulation ? '' : 'hidden' }} bg-gray-50/70 p-5 space-y-4">
            @csrf
            <div class="rounded-xl border border-indigo-100 bg-white p-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">table</span>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm font-bold text-gray-900">Import Peraturan Sekaligus</h4>
                        <p class="mt-1 text-xs leading-5 text-gray-500">Upload PDF resmi, atau CSV/XLSX dengan header: judul, isi, tanggal_berlaku, status.</p>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-gray-50">
                    <table class="min-w-full text-left text-[12px]">
                        <thead class="bg-white text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-bold">judul</th>
                                <th class="px-3 py-2 font-bold">isi</th>
                                <th class="px-3 py-2 font-bold">tanggal_berlaku</th>
                                <th class="px-3 py-2 font-bold">status</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600">
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap">Tata Tertib Absensi</td>
                                <td class="px-3 py-2">Clock in tepat waktu</td>
                                <td class="px-3 py-2 whitespace-nowrap">2026-08-01</td>
                                <td class="px-3 py-2 whitespace-nowrap">Aktif</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">File Import <span class="text-red-500">*</span></label>
                <input type="file" name="import_file" accept=".pdf,.csv,.txt,.xlsx" required
                    class="block w-full text-[12px] text-gray-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-semibold hover:file:bg-indigo-100">
                <p class="text-[11px] text-gray-400 mt-1">PDF akan masuk sebagai satu dokumen lampiran aktif. Maks 25MB. Status spreadsheet kosong otomatis menjadi Aktif.</p>
                @error('import_file')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 pt-1">
                <button type="button" onclick="switchRegulationTab('list')" class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">Batal</button>
                <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">upload_file</span>
                    Import Peraturan
                </button>
            </div>
        </form>
    </section>
    </div>
</div>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('logo-preview');
            const placeholder = document.getElementById('logo-placeholder');
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function switchRegulationTab(tab) {
    const listPanel = document.getElementById('regulationListPanel');
    const createPanel = document.getElementById('regulationCreatePanel');
    const importPanel = document.getElementById('regulationImportPanel');
    const listTab = document.getElementById('regulationTabList');
    const createTab = document.getElementById('regulationTabCreate');
    const importTab = document.getElementById('regulationTabImport');
    const showCreate = tab === 'create';
    const showImport = tab === 'import';

    listPanel?.classList.toggle('hidden', showCreate || showImport);
    createPanel?.classList.toggle('hidden', !showCreate);
    importPanel?.classList.toggle('hidden', !showImport);
    listTab?.classList.toggle('is-active', !showCreate && !showImport);
    createTab?.classList.toggle('is-active', showCreate);
    importTab?.classList.toggle('is-active', showImport);
}

function toggleRegulationEdit(targetId) {
    document.getElementById(targetId)?.classList.toggle('hidden');
}

function initRegulationFileInputs() {
    document.querySelectorAll('[data-regulation-file-input]').forEach((input) => {
        if (input.dataset.fileInputReady === '1') {
            return;
        }

        input.dataset.fileInputReady = '1';
        let selectedFiles = [];

        const syncInputFiles = () => {
            const transfer = new DataTransfer();
            selectedFiles.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        };

        const fileKey = (file) => [file.name, file.size, file.lastModified].join('|');

        const renderSelectedFiles = () => {
            const list = document.getElementById(input.dataset.fileList);

            if (!list) {
                return;
            }

            list.innerHTML = '';
            list.classList.toggle('hidden', selectedFiles.length === 0);

            selectedFiles.forEach((file, index) => {
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between gap-3 rounded-lg border border-indigo-100 bg-white px-3 py-2';

                const name = document.createElement('span');
                name.className = 'min-w-0 truncate text-[12px] font-semibold text-gray-700';
                name.textContent = file.name;

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'inline-flex items-center justify-center gap-1 px-2 py-1 text-[11px] font-bold text-red-600 bg-red-50 rounded-md hover:bg-red-100 transition';
                removeButton.innerHTML = '<span class="material-symbols-outlined text-[14px]">close</span>Hapus';
                removeButton.addEventListener('click', () => {
                    selectedFiles.splice(index, 1);
                    syncInputFiles();
                    renderSelectedFiles();
                });

                row.append(name, removeButton);
                list.appendChild(row);
            });
        };

        input.addEventListener('change', () => {
            const existing = new Set(selectedFiles.map(fileKey));

            Array.from(input.files).forEach((file) => {
                const key = fileKey(file);

                if (!existing.has(key)) {
                    selectedFiles.push(file);
                    existing.add(key);
                }
            });

            syncInputFiles();
            renderSelectedFiles();
        });
    });
}

function initExistingAttachmentDeleteButtons() {
    document.querySelectorAll('[data-existing-attachment-delete]').forEach((button) => {
        if (button.dataset.deleteButtonReady === '1') {
            return;
        }

        button.dataset.deleteButtonReady = '1';
        button.addEventListener('click', () => {
            const row = button.closest('[data-existing-attachment-row]');
            const input = row?.querySelector('[data-existing-attachment-delete-input]');

            if (!row || !input) {
                return;
            }

            input.disabled = false;
            row.classList.add('hidden');
        });
    });
}

initRegulationFileInputs();
initExistingAttachmentDeleteButtons();
</script>
@endsection
