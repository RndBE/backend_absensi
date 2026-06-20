@extends('admin.layouts.app')
@section('title', 'Tambah Karyawan')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Tambah Karyawan Baru</h3>
        <a href="{{ route('admin.employees.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">← Kembali</a>
    </div>
    <div class="p-5">
        <form action="{{ route('admin.employees.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Profile Photo --}}
            <div class="mb-5 flex items-center gap-5">
                <div class="relative group">
                    <div id="photoPreview" class="w-20 h-20 rounded-full border-2 border-dashed border-gray-300 overflow-hidden bg-gray-50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[28px] text-gray-300">person</span>
                    </div>
                    <label class="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-all cursor-pointer flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-[20px]">photo_camera</span>
                        <input type="file" name="photo" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                    </label>
                </div>
                <div>
                    <div class="text-[13px] font-semibold text-gray-800">Foto Profil</div>
                    <div class="text-[11px] text-gray-400">Klik untuk upload foto. Maks 2MB. Opsional.</div>
                    @error('photo')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kode Karyawan *</label>
                    <input type="text" name="employee_code" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('employee_code') }}" placeholder="001/DIV/I/2025" required>
                    @error('employee_code')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Nama Lengkap *</label>
                    <input type="text" name="full_name" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('full_name') }}" required>
                    @error('full_name')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Email *</label>
                    <input type="email" name="email" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('email') }}" required>
                    @error('email')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">No. Handphone</label>
                    <input type="text" name="phone" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('phone') }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Password *</label>
                    <input type="password" name="password" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" required>
                    @error('password')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Role *</label>
                    <select name="role" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        @foreach($adminRoles as $roleSlug => $roleLabel)
                            <option value="{{ $roleSlug }}" {{ old('role', 'employee') === $roleSlug ? 'selected' : '' }}>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Departemen *</label>
                    <select name="department_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        <option value="">Pilih Departemen</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Jadwal Kerja</label>
                    <select name="work_schedule_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Pilih Jadwal</option>
                        @foreach($workSchedules as $ws)
                            <option value="{{ $ws->id }}" {{ old('work_schedule_id') == $ws->id ? 'selected' : '' }}>{{ $ws->name }} ({{ $ws->start_time }} - {{ $ws->end_time }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Posisi</label>
                    <input type="text" name="position" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('position') }}">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Level Pekerjaan</label>
                    <input type="number" name="job_level" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('job_level') }}" min="1">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Status Kepegawaian *</label>
                    <select name="employment_status" id="employmentStatusCreate" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" data-no-search required>
                        <option value="contract" {{ old('employment_status','contract') === 'contract' ? 'selected' : '' }}>Kontrak</option>
                        <option value="permanent" {{ old('employment_status') === 'permanent' ? 'selected' : '' }}>Tetap</option>
                        <option value="intern" {{ old('employment_status') === 'intern' ? 'selected' : '' }}>Magang</option>
                        <option value="probation" {{ old('employment_status') === 'probation' ? 'selected' : '' }}>Probation</option>
                        <option value="outsourcing" {{ old('employment_status') === 'outsourcing' ? 'selected' : '' }}>Outsourcing</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Bergabung</label>
                    <input type="date" name="join_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('join_date') }}">
                </div>
            </div>

            {{-- Contract Dates (shown when status = contract/intern/probation/outsourcing) --}}
            <div id="contractDatesRowCreate" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="{{ in_array(old('employment_status', 'contract'), ['contract','intern','probation','outsourcing']) ? '' : 'display:none' }}">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kontrak Mulai</label>
                    <input type="date" name="contract_start_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('contract_start_date') }}">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kontrak Berakhir</label>
                    <input type="date" name="contract_end_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('contract_end_date') }}">
                </div>
            </div>

            {{-- Internship Info (shown when status = intern) --}}
            <div id="internshipSectionCreate" style="{{ old('employment_status') === 'intern' ? '' : 'display:none' }}">
                <div class="mb-4 p-4 bg-orange-50 border border-orange-200 rounded-xl">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-[16px] text-orange-600">school</span>
                        <h4 class="text-[13px] font-bold text-orange-800">Informasi Magang</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Institusi / Universitas</label>
                            <input type="text" name="internship_institution" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('internship_institution') }}" placeholder="Universitas Gadjah Mada">
                            @error('internship_institution')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Pembimbing Institusi <span class="font-normal text-gray-400">(Opsional)</span></label>
                            <input type="text" name="internship_supervisor" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('internship_supervisor') }}" placeholder="Boleh dikosongi jika tidak ada pembimbing dari kampus/sekolah">
                            @error('internship_supervisor')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Pembimbing Lapangan / Kantor</label>
                            <input type="text" name="internship_field_supervisor" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('internship_field_supervisor') }}" placeholder="Nama pembimbing di kantor/lapangan">
                            @error('internship_field_supervisor')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Catatan Magang</label>
                        <textarea name="internship_notes" rows="2" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400 resize-none" placeholder="Catatan atau keterangan tambahan mengenai magang...">{{ old('internship_notes') }}</textarea>
                        @error('internship_notes')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Manager</label>
                    <select name="manager_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Tidak Ada</option>
                        @foreach($managers as $mgr)
                            <option value="{{ $mgr->id }}" {{ old('manager_id') == $mgr->id ? 'selected' : '' }}>Lv{{ $mgr->job_level }} — {{ $mgr->full_name }} ({{ $mgr->position }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Approver (Atasan untuk Approval) <span class="text-indigo-500">*penting</span></label>
                    <select name="approver_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Tidak Ada (Top Level)</option>
                        @foreach($managers as $mgr)
                            <option value="{{ $mgr->id }}" {{ old('approver_id') == $mgr->id ? 'selected' : '' }}>Lv{{ $mgr->job_level }} — {{ $mgr->full_name }} ({{ $mgr->position }})</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Menentukan siapa yang approve pengajuan karyawan ini. Chain: karyawan → approver → approver's approver → dst.</p>
                </div>
            </div>

            <hr class="my-6 border-gray-100">
            <h4 class="text-[14px] font-bold text-gray-800 mb-4">Informasi Personal</h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">NIK KTP</label>
                    <input type="text" name="nik" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('nik') }}" placeholder="35xxxxxxxxxxxxxx">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Agama</label>
                    <select name="religion" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Pilih Agama</option>
                        <option value="Islam" {{ old('religion') == 'Islam' ? 'selected' : '' }}>Islam</option>
                        <option value="Kristen" {{ old('religion') == 'Kristen' ? 'selected' : '' }}>Kristen</option>
                        <option value="Katolik" {{ old('religion') == 'Katolik' ? 'selected' : '' }}>Katolik</option>
                        <option value="Hindu" {{ old('religion') == 'Hindu' ? 'selected' : '' }}>Hindu</option>
                        <option value="Buddha" {{ old('religion') == 'Buddha' ? 'selected' : '' }}>Buddha</option>
                        <option value="Konghucu" {{ old('religion') == 'Konghucu' ? 'selected' : '' }}>Konghucu</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tempat Lahir</label>
                    <input type="text" name="birth_place" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('birth_place') }}">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Lahir</label>
                    <input type="date" name="birth_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('birth_date') }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Jenis Kelamin</label>
                    <select name="gender" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Status Perkawinan</label>
                    <select name="marital_status" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Status</option>
                        <option value="single" {{ old('marital_status') == 'single' ? 'selected' : '' }}>Belum Menikah</option>
                        <option value="married" {{ old('marital_status') == 'married' ? 'selected' : '' }}>Menikah</option>
                        <option value="divorced" {{ old('marital_status') == 'divorced' ? 'selected' : '' }}>Cerai</option>
                        <option value="widowed" {{ old('marital_status') == 'widowed' ? 'selected' : '' }}>Cerai Mati</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Gol. Darah</label>
                    <select name="blood_type" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Gol Darah</option>
                        <option value="A" {{ old('blood_type') == 'A' ? 'selected' : '' }}>A</option>
                        <option value="B" {{ old('blood_type') == 'B' ? 'selected' : '' }}>B</option>
                        <option value="AB" {{ old('blood_type') == 'AB' ? 'selected' : '' }}>AB</option>
                        <option value="O" {{ old('blood_type') == 'O' ? 'selected' : '' }}>O</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Alamat KTP</label>
                    <textarea name="ktp_address" rows="2" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400">{{ old('ktp_address') }}</textarea>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Alamat Tempat Tinggal</label>
                    <textarea name="residential_address" rows="2" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400">{{ old('residential_address') }}</textarea>
                </div>
            </div>

            <div class="mb-2 w-1/2 pr-2">
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kode Pos</label>
                <input type="text" name="postal_code" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('postal_code') }}">
            </div>

            <div class="mt-8">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-[0_2px_8px_rgba(79,70,229,0.3)] hover:shadow-[0_4px_12px_rgba(79,70,229,0.4)] hover:-translate-y-0.5 transition-all duration-200 cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Karyawan</button>
            </div>
        </form>
    </div>
</div>
@endsection

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="w-full h-full object-cover">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Toggle contract dates and internship section based on employment status
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('employmentStatusCreate');
    const contractRow = document.getElementById('contractDatesRowCreate');
    const internshipSection = document.getElementById('internshipSectionCreate');

    function toggleStatusSections() {
        const val = statusSelect.value;
        const showContract = ['contract','intern','probation','outsourcing'].includes(val);
        contractRow.style.display = showContract ? '' : 'none';
        if (internshipSection) {
            internshipSection.style.display = val === 'intern' ? '' : 'none';
        }
    }

    if (statusSelect && contractRow) {
        statusSelect.addEventListener('change', toggleStatusSections);
    }
});
</script>
