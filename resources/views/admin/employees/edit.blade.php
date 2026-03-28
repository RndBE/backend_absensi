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
                    <select name="role" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        <option value="employee" {{ old('role', $employee->role) === 'employee' ? 'selected' : '' }}>Employee</option>
                        <option value="manager" {{ old('role', $employee->role) === 'manager' ? 'selected' : '' }}>Manager</option>
                        <option value="admin" {{ old('role', $employee->role) === 'admin' ? 'selected' : '' }}>Admin</option>
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
                    <select name="employment_status" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Akhir Kontrak</label>
                    <input type="date" name="contract_end_date" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('contract_end_date', $employee->contract_end_date?->format('Y-m-d')) }}">
                </div>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Approver (Atasan untuk Approval) <span class="text-indigo-500">*penting</span></label>
                    <select name="approver_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Tidak Ada (Top Level)</option>
                        @foreach($managers as $mgr)
                            <option value="{{ $mgr->id }}" {{ old('approver_id', $employee->approver_id) == $mgr->id ? 'selected' : '' }}>Lv{{ $mgr->job_level }} — {{ $mgr->full_name }} ({{ $mgr->position }})</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Menentukan siapa yang approve pengajuan karyawan ini. Chain: karyawan → approver → approver's approver → dst.</p>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-[0_2px_8px_rgba(79,70,229,0.3)] hover:shadow-[0_4px_12px_rgba(79,70,229,0.4)] hover:-translate-y-0.5 transition-all duration-200 cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Perbarui</button>
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
</script>

