@extends('admin.layouts.app')
@section('title', 'Template Jadwal')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">content_paste</span> Template Jadwal Kerja</h3>
        <a href="{{ route('admin.schedules.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">← Kembali ke Jadwal</a>
    </div>
    <div class="p-5">
        <p class="text-[12px] text-gray-500 mb-3">Template = pola mingguan otomatis. Assign ke karyawan → jadwal otomatis berlaku untuk semua tanggal tanpa perlu generate.</p>

        <div class="mb-5 p-3 rounded-xl bg-amber-50 border border-amber-200">
            <p class="text-[12px] font-semibold text-amber-700 mb-1.5"><span class="material-symbols-outlined text-[14px] align-text-bottom">info</span> Tentang Hari Libur</p>
            <p class="text-[11px] text-amber-600 mb-2">Hari libur nasional otomatis <strong>menggantikan</strong> shift template. Karyawan yang di-assign template tetap akan tampil <strong>"Libur"</strong> pada tanggal libur nasional.</p>
            @php
                $admin = \App\Models\Employee::find(session('admin_id'));
                $upcomingHolidays = \App\Models\Holiday::where('company_id', $admin->company_id)
                    ->where('date', '>=', now()->startOfMonth())
                    ->orderBy('date')
                    ->take(10)
                    ->get();
            @endphp
            @if($upcomingHolidays->count())
                <div class="flex items-center gap-2 flex-wrap">
                    @foreach($upcomingHolidays as $h)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700">
                            🎉 {{ $h->date->format('d/m') }} — {{ $h->name }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-[10px] text-gray-400">Belum ada hari libur yang dikonfigurasi. Tambahkan di menu Hari Libur.</p>
            @endif
        </div>

        {{-- Existing Templates --}}
        @foreach($templates as $template)
        <div class="border border-gray-200 rounded-xl p-4 mb-4 hover:shadow-sm transition-all">
            <form action="{{ route('admin.schedule-templates.update', $template->id) }}" method="POST">
                @csrf @method('PUT')
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <input type="text" name="name" value="{{ $template->name }}" class="text-[14px] font-bold text-gray-900 border border-gray-300 rounded-lg px-3 py-1.5 w-[200px] outline-none focus:border-indigo-500">
                        <span class="text-[11px] text-gray-400">{{ $template->employees_count }} karyawan</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-3 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan</button>
                        <button type="button" onclick="document.getElementById('assignTpl{{ $template->id }}').classList.toggle('hidden')" class="px-3 py-1.5 text-[11px] font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">group_add</span> Assign</button>
                    </div>
                </div>
                <input type="text" name="description" value="{{ $template->description }}" placeholder="Deskripsi (opsional)" class="w-full text-[12px] text-gray-500 border border-gray-200 rounded-lg px-3 py-1.5 mb-3 outline-none focus:border-indigo-500">

                {{-- 7-day grid --}}
                <div class="grid grid-cols-7 gap-2">
                    @php $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']; @endphp
                    @for($i = 1; $i <= 7; $i++)
                        @php
                            $day = $template->days->firstWhere('day_of_week', $i);
                            $selectedShift = $day?->shift;
                        @endphp
                        <div class="text-center">
                            <div class="text-[10px] font-bold text-gray-500 uppercase mb-1">{{ $dayNames[$i-1] }}</div>
                            <select name="days[{{ $i }}]" class="w-full px-1 py-2 text-[11px] border rounded-lg outline-none text-center font-semibold transition-all focus:border-indigo-500"
                                onchange="this.style.backgroundColor = this.options[this.selectedIndex].dataset.color || '#f3f4f6'; this.style.color='white'"
                                style="background-color: {{ $selectedShift ? $selectedShift->color : '#f3f4f6' }}; color: {{ $selectedShift ? 'white' : '#374151' }}">
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}" data-color="{{ $shift->color }}" {{ $day && $day->shift_id == $shift->id ? 'selected' : '' }}>
                                        {{ $shift->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
            </form>

            {{-- Assign Employees Panel --}}
            <div id="assignTpl{{ $template->id }}" class="hidden mt-3 p-3 bg-emerald-50 rounded-lg border border-emerald-200">
                <form action="{{ route('admin.schedule-templates.assign') }}" method="POST">
                    @csrf
                    <input type="hidden" name="template_id" value="{{ $template->id }}">
                    <div class="text-[12px] font-semibold text-emerald-700 mb-2">Pilih karyawan untuk di-assign ke template ini:</div>
                    <div class="flex items-center gap-2 mb-2">
                        <button type="button" onclick="this.closest('form').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=true)" class="text-[10px] font-semibold text-emerald-600 underline cursor-pointer bg-transparent border-0">Semua</button>
                        <button type="button" onclick="this.closest('form').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=false)" class="text-[10px] font-semibold text-gray-400 underline cursor-pointer bg-transparent border-0">Batal</button>
                    </div>
                    <div class="max-h-[150px] overflow-y-auto space-y-1 mb-2">
                        @foreach(\App\Models\Employee::where('company_id', $template->company_id)->where('is_active', true)->orderBy('full_name')->get(['id','full_name']) as $emp)
                        <label class="flex items-center gap-2 text-[11px] cursor-pointer">
                            <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" class="accent-emerald-500">
                            {{ $emp->full_name }}
                        </label>
                        @endforeach
                    </div>
                    <button type="submit" class="px-3 py-1.5 text-[11px] font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">check_circle</span> Assign Template</button>
                </form>
            </div>

            {{-- Delete --}}
            <div class="mt-3 flex justify-end">
                <form action="{{ route('admin.schedule-templates.destroy', $template->id) }}" method="POST" onsubmit="return confirm('Hapus template {{ $template->name }}?')">
                    @csrf @method('DELETE')
                    <button class="text-[10px] font-semibold text-red-500 hover:text-red-700 transition-all cursor-pointer bg-transparent border-0"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span> Hapus Template</button>
                </form>
            </div>
        </div>
        @endforeach

        {{-- Add New Template --}}
        <div class="border-2 border-dashed border-gray-300 rounded-xl p-5 bg-gray-50">
            <h4 class="text-[13px] font-bold text-gray-700 mb-3"><span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Buat Template Baru</h4>
            <form action="{{ route('admin.schedule-templates.store') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama Template</label>
                        <input type="text" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" placeholder="5 Hari Kerja (Pagi)" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Deskripsi (opsional)</label>
                        <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" placeholder="Senin-Jumat shift pagi">
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-2 mb-4">
                    @for($i = 1; $i <= 7; $i++)
                        <div class="text-center">
                            <div class="text-[10px] font-bold text-gray-500 uppercase mb-1">{{ $dayNames[$i-1] }}</div>
                            <select name="days[{{ $i }}]" class="w-full px-1 py-2 text-[11px] border border-gray-300 rounded-lg outline-none text-center font-semibold focus:border-indigo-500" required>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}" {{ ($i >= 6 && $shift->is_off) ? 'selected' : (($i < 6 && $shift->sort_order == 1) ? 'selected' : '') }}>{{ $shift->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
                <button type="submit" class="inline-flex items-center gap-1 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">＋ Buat Template</button>
            </form>
        </div>
    </div>
</div>
@endsection
