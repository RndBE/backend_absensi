@extends('admin.layouts.app')
@section('title', 'Edit Karyawan')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span> Edit — {{ $employee->full_name }}</h3>
        <a href="{{ route('admin.employees.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">← Kembali</a>
    </div>
    <div class="p-5">
        <form action="{{ route('admin.employees.update', $employee->id) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')

            {{-- Profile Photo --}}
            <div class="mb-5 flex items-center gap-5">
                <div class="relative group">
                    <div id="photoPreview" class="w-20 h-20 rounded-full border-2 border-gray-200 overflow-hidden bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-2xl font-bold shrink-0">
                        @if($employee->photo)
                            <img src="{{ asset('storage/' . $employee->photo) }}" alt="Photo" class="w-full h-full object-cover">
                        @else
                            {{ substr($employee->full_name, 0, 1) }}
                        @endif
                    </div>
                    <label class="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-all cursor-pointer flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-[20px]">photo_camera</span>
                        <input type="file" name="photo" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                    </label>
                </div>
                <div>
                    <div class="text-[13px] font-semibold text-gray-800">Foto Profil</div>
                    <div class="text-[11px] text-gray-400">Klik foto untuk mengubah. Maks 2MB.</div>
                    @if($employee->photo)
                        <label class="inline-flex items-center gap-1 mt-1 text-[11px] text-red-500 cursor-pointer hover:underline">
                            <input type="checkbox" name="remove_photo" value="1" class="accent-red-500 w-3 h-3"> Hapus foto
                        </label>
                    @endif
                    @error('photo')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Signature Upload --}}
            <div class="mb-5 flex items-center gap-5 pb-5 border-b border-gray-100">
                <div class="relative group">
                    <div id="signaturePreview" class="w-32 h-16 rounded-lg border-2 border-dashed border-gray-200 overflow-hidden bg-white flex items-center justify-center shrink-0">
                        @if($employee->signature)
                            <img src="{{ asset('storage/' . $employee->signature) }}" alt="Signature" class="max-w-full max-h-full object-contain">
                        @else
                            <span class="material-symbols-outlined text-[24px] text-gray-300">draw</span>
                        @endif
                    </div>
                    <label class="absolute inset-0 rounded-lg bg-black/40 opacity-0 group-hover:opacity-100 transition-all cursor-pointer flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-[20px]">edit</span>
                        <input type="file" name="signature" accept="image/*" class="hidden" onchange="previewSignature(this)">
                    </label>
                </div>
                <div>
                    <div class="text-[13px] font-semibold text-gray-800">Tanda Tangan</div>
                    <div class="text-[11px] text-gray-400">Upload tanda tangan digital. Maks 2MB. Latar transparan (PNG) disarankan.</div>
                    @if($employee->signature)
                        <label class="inline-flex items-center gap-1 mt-1 text-[11px] text-red-500 cursor-pointer hover:underline">
                            <input type="checkbox" name="remove_signature" value="1" class="accent-red-500 w-3 h-3"> Hapus tanda tangan
                        </label>
                    @endif
                    @error('signature')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kode Karyawan *</label>
                    <input type="text" name="employee_code" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('employee_code', $employee->employee_code) }}" required>
                    @error('employee_code')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Nama Lengkap *</label>
                    <input type="text" name="full_name" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('full_name', $employee->full_name) }}" required>
                    @error('full_name')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Email *</label>
                    <input type="email" name="email" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('email', $employee->email) }}" required>
                    @error('email')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">No. Handphone</label>
                    <input type="text" name="phone" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('phone', $employee->phone) }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Password <span class="text-gray-400 font-normal text-xs">(kosongkan jika tidak diubah)</span></label>
                    <input type="password" name="password" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" autocomplete="new-password">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Role *</label>
                    @php
                        $selectedRole = old('role', $employee->roles->first()?->slug ?? ($employee->role === 'admin' ? 'hr_admin' : $employee->role));
                    @endphp
                    <select name="role" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        @foreach($adminRoles as $roleSlug => $roleLabel)
                            <option value="{{ $roleSlug }}" {{ $selectedRole === $roleSlug ? 'selected' : '' }}>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Departemen *</label>
                    <select name="department_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id', $employee->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Jadwal Kerja</label>
                    <select name="work_schedule_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Pilih Jadwal</option>
                        @foreach($workSchedules as $ws)
                            <option value="{{ $ws->id }}" {{ old('work_schedule_id', $employee->work_schedule_id) == $ws->id ? 'selected' : '' }}>{{ $ws->name }} ({{ $ws->start_time }} - {{ $ws->end_time }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Posisi</label>
                    <input type="text" name="position" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('position', $employee->position) }}">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Level Pekerjaan</label>
                    <input type="number" name="job_level" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('job_level', $employee->job_level) }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Status Kepegawaian *</label>
                    <select name="employment_status" id="employmentStatus" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" data-no-search required>
                        <option value="contract" {{ old('employment_status', $employee->employment_status) === 'contract' ? 'selected' : '' }}>Kontrak</option>
                        <option value="permanent" {{ old('employment_status', $employee->employment_status) === 'permanent' ? 'selected' : '' }}>Tetap</option>
                        <option value="intern" {{ old('employment_status', $employee->employment_status) === 'intern' ? 'selected' : '' }}>Magang</option>
                        <option value="probation" {{ old('employment_status', $employee->employment_status) === 'probation' ? 'selected' : '' }}>Probation</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Bergabung</label>
                    <input type="date" name="join_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('join_date', $employee->join_date?->format('Y-m-d')) }}">
                </div>
            </div>

            {{-- Contract End Date (shown when status = contract/intern/probation) --}}
            <div id="contractDatesRow" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="{{ in_array(old('employment_status', $employee->employment_status), ['contract','intern','probation']) ? '' : 'display:none' }}">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kontrak Berakhir</label>
                    <input type="date" name="contract_end_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('contract_end_date', $employee->contract_end_date?->format('Y-m-d')) }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Manager</label>
                    <select name="manager_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Tidak Ada</option>
                        @foreach($managers as $mgr)
                            <option value="{{ $mgr->id }}" {{ old('manager_id', $employee->manager_id) == $mgr->id ? 'selected' : '' }}>Lv{{ $mgr->job_level }} — {{ $mgr->full_name }} ({{ $mgr->position }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <hr class="my-6 border-gray-100">
            <h4 class="text-[14px] font-bold text-gray-800 mb-4">Informasi Personal</h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">NIK KTP</label>
                    <input type="text" name="nik" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('nik', $employee->nik) }}" placeholder="35xxxxxxxxxxxxxx">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Agama</label>
                    <select name="religion" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Pilih Agama</option>
                        <option value="Islam" {{ old('religion', $employee->religion) == 'Islam' ? 'selected' : '' }}>Islam</option>
                        <option value="Kristen" {{ old('religion', $employee->religion) == 'Kristen' ? 'selected' : '' }}>Kristen</option>
                        <option value="Katolik" {{ old('religion', $employee->religion) == 'Katolik' ? 'selected' : '' }}>Katolik</option>
                        <option value="Hindu" {{ old('religion', $employee->religion) == 'Hindu' ? 'selected' : '' }}>Hindu</option>
                        <option value="Buddha" {{ old('religion', $employee->religion) == 'Buddha' ? 'selected' : '' }}>Buddha</option>
                        <option value="Konghucu" {{ old('religion', $employee->religion) == 'Konghucu' ? 'selected' : '' }}>Konghucu</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tempat Lahir</label>
                    <input type="text" name="birth_place" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('birth_place', $employee->birth_place) }}">
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Lahir</label>
                    <input type="date" name="birth_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Jenis Kelamin</label>
                    <select name="gender" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Pilih</option>
                        <option value="male" {{ old('gender', $employee->gender) == 'male' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="female" {{ old('gender', $employee->gender) == 'female' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Status Perkawinan</label>
                    <select name="marital_status" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Status</option>
                        <option value="single" {{ old('marital_status', $employee->marital_status) == 'single' ? 'selected' : '' }}>Belum Menikah</option>
                        <option value="married" {{ old('marital_status', $employee->marital_status) == 'married' ? 'selected' : '' }}>Menikah</option>
                        <option value="divorced" {{ old('marital_status', $employee->marital_status) == 'divorced' ? 'selected' : '' }}>Cerai</option>
                        <option value="widowed" {{ old('marital_status', $employee->marital_status) == 'widowed' ? 'selected' : '' }}>Cerai Mati</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Gol. Darah</label>
                    <select name="blood_type" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Darah</option>
                        <option value="A" {{ old('blood_type', $employee->blood_type) == 'A' ? 'selected' : '' }}>A</option>
                        <option value="B" {{ old('blood_type', $employee->blood_type) == 'B' ? 'selected' : '' }}>B</option>
                        <option value="AB" {{ old('blood_type', $employee->blood_type) == 'AB' ? 'selected' : '' }}>AB</option>
                        <option value="O" {{ old('blood_type', $employee->blood_type) == 'O' ? 'selected' : '' }}>O</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Alamat KTP</label>
                    <textarea name="ktp_address" rows="2" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400">{{ old('ktp_address', $employee->ktp_address) }}</textarea>
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Alamat Tempat Tinggal</label>
                    <textarea name="residential_address" rows="2" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400">{{ old('residential_address', $employee->residential_address) }}</textarea>
                </div>
            </div>

            <div class="mb-2 w-1/2 pr-2">
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kode Pos</label>
                <input type="text" name="postal_code" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400" value="{{ old('postal_code', $employee->postal_code) }}">
            </div>

            <div class="mt-8">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-[0_2px_8px_rgba(79,70,229,0.3)] hover:shadow-[0_4px_12px_rgba(79,70,229,0.4)] hover:-translate-y-0.5 transition-all duration-200 cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Perbarui</button>
            </div>
        </form>
    </div>
</div>

{{-- Approval Chain Configuration --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mt-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">approval</span> Pengaturan Approval — {{ $employee->full_name }}</h3>
        <p class="text-[12px] text-gray-400 mt-1">Atur siapa saja yang harus menyetujui pengajuan karyawan ini, per tipe pengajuan.</p>
    </div>
    <div class="p-5">
        <form action="{{ route('admin.employees.approvers.store', $employee->id) }}" method="POST" id="approvalForm">
            @csrf
            @php $types = ['leave' => 'Cuti', 'overtime' => 'Lembur', 'attendance' => 'Presensi', 'budget' => 'Anggaran', 'travel_report' => 'LHP']; @endphp

            <div class="flex gap-0 border-b-2 border-gray-200 mb-5">
                @foreach($types as $tKey => $tLabel)
                    <button type="button" onclick="switchApprovalTab('{{ $tKey }}')"
                       class="approval-tab px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200"
                       data-tab="{{ $tKey }}"
                       id="tab-{{ $tKey }}">
                        {{ $tLabel }}
                    </button>
                @endforeach
            </div>

            @foreach($types as $tKey => $tLabel)
            <div class="approval-panel" id="panel-{{ $tKey }}" style="display: none;">
                <div id="chain-{{ $tKey }}" class="space-y-2 mb-4">
                    @if(isset($approvalChains[$tKey]) && $approvalChains[$tKey]->count())
                        @foreach($approvalChains[$tKey] as $i => $chain)
                        <div class="flex items-center gap-3 step-row">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[12px] font-bold shrink-0 step-num">{{ $i + 1 }}</div>
                            <select name="chains[{{ $tKey }}][]" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                                <option value="">Pilih Approver</option>
                                @foreach($managers as $mgr)
                                    <option value="{{ $mgr->id }}" {{ $chain->approver_id == $mgr->id ? 'selected' : '' }}>Lv{{ $mgr->job_level }} — {{ $mgr->full_name }} ({{ $mgr->position }})</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="removeStep(this)" class="w-8 h-8 flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all cursor-pointer"><span class="material-symbols-outlined text-[18px]">close</span></button>
                        </div>
                        @endforeach
                    @endif
                </div>

                {{-- Flow visualization --}}
                <div class="flex items-center gap-2 py-2 px-3 bg-gray-50 rounded-lg mb-3 flow-vis" id="flow-{{ $tKey }}"></div>

                <button type="button" onclick="addStep('{{ $tKey }}')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[14px]">add</span> Tambah Step
                </button>
            </div>
            @endforeach

            <div class="mt-5 pt-4 border-t border-gray-100">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                    <span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Pengaturan Approval
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const managersData = @json($managers->map(fn($m) => ['id' => $m->id, 'label' => 'Lv'.($m->job_level ?? '?').' — '.$m->full_name.' ('.($m->position ?? '-').')']));

function switchApprovalTab(tab) {
    document.querySelectorAll('.approval-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.approval-tab').forEach(t => {
        t.classList.remove('text-indigo-600', 'border-indigo-600');
        t.classList.add('text-gray-500', 'border-transparent');
    });
    document.getElementById('panel-' + tab).style.display = 'block';
    const activeTab = document.getElementById('tab-' + tab);
    activeTab.classList.add('text-indigo-600', 'border-indigo-600');
    activeTab.classList.remove('text-gray-500', 'border-transparent');
    updateFlow(tab);
}

function addStep(type) {
    const chain = document.getElementById('chain-' + type);
    const count = chain.querySelectorAll('.step-row').length;
    const opts = managersData.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
    const html = `<div class="flex items-center gap-3 step-row">
        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[12px] font-bold shrink-0 step-num">${count + 1}</div>
        <select name="chains[${type}][]" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" onchange="updateFlow('${type}')">
            <option value="">Pilih Approver</option>${opts}
        </select>
        <button type="button" onclick="removeStep(this)" class="w-8 h-8 flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all cursor-pointer"><span class="material-symbols-outlined text-[18px]">close</span></button>
    </div>`;
    chain.insertAdjacentHTML('beforeend', html);
    // Init searchable select on the newly added select
    const newSelect = chain.querySelector('.step-row:last-child select');
    if (newSelect && typeof initSearchableSelect === 'function') {
        initSearchableSelect(newSelect);
    }
    updateFlow(type);
}

function removeStep(btn) {
    const row = btn.closest('.step-row');
    const chain = row.parentElement;
    const type = chain.id.replace('chain-', '');
    row.remove();
    renumberSteps(type);
    updateFlow(type);
}

function renumberSteps(type) {
    const rows = document.querySelectorAll('#chain-' + type + ' .step-row');
    rows.forEach((r, i) => { r.querySelector('.step-num').textContent = i + 1; });
}

function updateFlow(type) {
    const flow = document.getElementById('flow-' + type);
    const selects = document.querySelectorAll('#chain-' + type + ' select');
    let html = '<span class="text-[11px] font-semibold text-gray-500">Flow:</span><span class="text-[11px] text-gray-600">Submit</span>';
    selects.forEach(s => {
        if (s.value) {
            const txt = s.options[s.selectedIndex].text.split(' — ')[1] || s.options[s.selectedIndex].text;
            html += `<span class="text-gray-400">→</span><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-600">${txt}</span>`;
        }
    });
    html += '<span class="text-gray-400">→</span><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-600">✓ Approved</span>';
    flow.innerHTML = html;
}

// Add onchange to existing selects
document.querySelectorAll('.step-row select').forEach(s => {
    const type = s.closest('.approval-panel').id.replace('panel-', '');
    s.addEventListener('change', () => updateFlow(type));
});

switchApprovalTab('leave');
</script>
@endpush

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

function previewSignature(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('signaturePreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Signature" class="max-w-full max-h-full object-contain">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Toggle contract dates based on employment status
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('employmentStatus');
    const contractRow = document.getElementById('contractDatesRow');

    function toggleContractDates() {
        const val = statusSelect.value;
        const show = ['contract', 'intern', 'probation'].includes(val);
        contractRow.style.display = show ? '' : 'none';
    }

    if (statusSelect && contractRow) {
        statusSelect.addEventListener('change', toggleContractDates);
    }
});
</script>
