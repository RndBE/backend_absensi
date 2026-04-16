@extends('admin.layouts.app')
@section('title', 'Ajukan Cuti')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">event_busy</span> Ajukan Cuti Baru</h3>
        <a href="{{ route('admin.leaves.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">← Kembali</a>
    </div>
    <div class="p-5">

        @if($admin->role === 'admin')
        {{-- Super Admin: can pick employee --}}
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-[13px] text-emerald-700 font-medium">
            ⚡ Anda login sebagai Super Admin — cuti yang dibuat akan langsung disetujui.
        </div>
        @else
        {{-- Non-admin: show self info --}}
        <div class="mb-5 p-4 rounded-xl bg-gradient-to-r from-indigo-50 to-white border border-indigo-100 flex items-center gap-4">
            <div class="w-11 h-11 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-400 flex items-center justify-center text-white text-[15px] font-bold shrink-0">{{ substr($admin->full_name, 0, 1) }}</div>
            <div>
                <div class="text-[14px] font-bold text-gray-900">{{ $admin->full_name }}</div>
                <div class="text-[12px] text-gray-500">{{ $admin->position ?? 'Karyawan' }} · Level {{ $admin->job_level }} · {{ $admin->department->name ?? '' }}</div>
            </div>
        </div>
        @endif

        <form action="{{ route('admin.leaves.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($admin->role === 'admin' && $employees)
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Karyawan *</label>
                    <select name="employee_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        <option value="">Pilih Karyawan</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                @endif

                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Jenis Cuti *</label>
                    <select name="leave_type_id" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" required>
                        <option value="">Pilih Jenis Cuti</option>
                        @foreach($leaveTypes as $lt)
                            <option value="{{ $lt->id }}" {{ old('leave_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }} (maks {{ $lt->max_days }} hari)</option>
                        @endforeach
                    </select>
                    @error('leave_type_id')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Mulai *</label>
                    <input type="date" name="start_date" id="startDate" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('start_date') }}" required>
                    @error('start_date')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tanggal Selesai *</label>
                    <input type="date" name="end_date" id="endDate" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10" value="{{ old('end_date') }}" required>
                    @error('end_date')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Day count preview --}}
            <div id="dayPreview" class="mb-4 p-3 rounded-lg bg-indigo-50 border border-indigo-200 text-[13px] text-indigo-700 font-medium hidden">
                <span class="material-symbols-outlined text-[14px] align-text-bottom">date_range</span> Total: <span id="dayCount">0</span> hari kerja (tidak termasuk Sabtu/Minggu)
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Alasan *</label>
                    <textarea name="reason" rows="3" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 resize-none" placeholder="Alasan mengajukan cuti..." required>{{ old('reason') }}</textarea>
                    @error('reason')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Delegasi Tugas Ke</label>
                    <select name="delegate_to" class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                        <option value="">Tidak Ada</option>
                        @foreach($colleagues as $c)
                            <option value="{{ $c->id }}" {{ old('delegate_to') == $c->id ? 'selected' : '' }}>{{ $c->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-[0_2px_8px_rgba(79,70,229,0.3)] hover:shadow-[0_4px_12px_rgba(79,70,229,0.4)] hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                    @if($admin->role === 'admin')
                        ⚡ Buat & Setujui Langsung
                    @else
                        📤 Kirim Pengajuan
                    @endif
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const dayPreview = document.getElementById('dayPreview');
    const dayCount = document.getElementById('dayCount');

    function calcDays() {
        if (!startDate.value || !endDate.value) { dayPreview.classList.add('hidden'); return; }
        const s = new Date(startDate.value);
        const e = new Date(endDate.value);
        if (e < s) { dayPreview.classList.add('hidden'); return; }
        let count = 0;
        let cur = new Date(s);
        while (cur <= e) {
            const day = cur.getDay();
            if (day !== 0 && day !== 6) count++;
            cur.setDate(cur.getDate() + 1);
        }
        dayCount.textContent = count;
        dayPreview.classList.remove('hidden');
    }

    startDate.addEventListener('change', calcDays);
    endDate.addEventListener('change', calcDays);
    calcDays();
</script>
@endsection
