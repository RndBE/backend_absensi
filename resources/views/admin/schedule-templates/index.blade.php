@extends('admin.layouts.app')
@section('title', 'Template Jadwal')

@section('content')
@php
    $admin          = \App\Models\Employee::find(session('admin_id'));
    $upcomingHols   = \App\Models\Holiday::where('company_id', $admin->company_id)
                        ->where('date', '>=', now()->startOfMonth())
                        ->orderBy('date')->take(8)->get();
    // Semua karyawan aktif dengan info template saat ini
    $allEmployees   = \App\Models\Employee::where('company_id', $admin->company_id)
                        ->where('is_active', true)
                        ->with('scheduleTemplate:id,name')
                        ->orderBy('full_name')
                        ->get(['id', 'full_name', 'schedule_template_id']);
@endphp

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900">
                <span class="material-symbols-outlined text-[18px] align-text-bottom">content_paste</span>
                Template Jadwal Kerja
            </h3>
            <p class="text-[11px] text-gray-400 mt-0.5">Pola mingguan otomatis — assign ke karyawan, shift langsung tampil di grid jadwal.</p>
        </div>
        <a href="{{ route('admin.schedules.index') }}"
           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
            ← Kembali ke Jadwal
        </a>
    </div>

    {{-- Info hari libur --}}
    @if($upcomingHols->count())
    <div class="px-5 py-2.5 bg-amber-50 border-b border-amber-100 flex items-start gap-2">
        <span class="material-symbols-outlined text-[14px] text-amber-600 mt-0.5 shrink-0">info</span>
        <div class="flex-1 min-w-0">
            <span class="text-[11px] font-semibold text-amber-700">Hari Libur Nasional — shift template otomatis digantikan:</span>
            <div class="flex flex-wrap gap-1.5 mt-1">
                @foreach($upcomingHols as $h)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700">
                        {{ $h->date->format('d/m') }} — {{ $h->name }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="p-5">
        @if(session('success'))
        <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-[12px] font-semibold text-emerald-700">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-[12px] font-semibold text-red-700">
            <span class="material-symbols-outlined text-[16px]">error</span>
            {{ session('error') }}
        </div>
        @endif

        {{-- ── Existing Templates ── --}}
        @foreach($templates as $template)
        @php
            $assignedIds = $template->employees->pluck('id')->toArray();
        @endphp
        <div class="border border-gray-200 rounded-xl mb-5 overflow-hidden hover:shadow-sm transition-all" id="tpl-card-{{ $template->id }}">

            {{-- Header template --}}
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-600">
                        <span class="material-symbols-outlined text-[16px]">content_paste</span>
                    </div>
                    <div>
                        <div class="text-[13px] font-bold text-gray-900">{{ $template->name }}</div>
                        <div class="text-[10px] text-gray-400">
                            {{ $template->employees_count }} karyawan ter-assign
                            @if($template->description) · {{ $template->description }} @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                        onclick="toggleAssignPanel({{ $template->id }})"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300 rounded-lg hover:bg-emerald-100 transition-all cursor-pointer">
                        <span class="material-symbols-outlined text-[14px] align-text-bottom">group_add</span>
                        Kelola Assign
                    </button>
                    <button type="button"
                        onclick="toggleEditPanel({{ $template->id }})"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                        <span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span>
                        Edit Shift
                    </button>
                </div>
            </div>

            {{-- Shift 7-hari preview --}}
            <div class="px-4 py-3">
                @php $dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']; @endphp
                <div class="grid grid-cols-7 gap-1.5">
                    @for($i = 1; $i <= 7; $i++)
                        @php
                            $day = $template->days->firstWhere('day_of_week', $i);
                            $sh  = $day?->shift;
                        @endphp
                        <div class="text-center">
                            <div class="text-[9px] font-bold text-gray-400 uppercase mb-1">{{ $dayNames[$i-1] }}</div>
                            <div class="rounded-lg py-1.5 text-[10px] font-bold text-white truncate px-1"
                                 style="background-color: {{ $sh?->color ?? '#e5e7eb' }}; color: {{ $sh ? 'white' : '#9ca3af' }}">
                                {{ $sh?->is_off ? 'Off' : ($sh?->name ?? '—') }}
                                @if($sh && !$sh->is_off)
                                    <div class="text-[8px] font-normal opacity-80">{{ substr($sh->start_time, 0, 5) }}</div>
                                @endif
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Panel Edit Shift (tersembunyi) --}}
            <div id="editPanel-{{ $template->id }}" class="hidden border-t border-gray-100 bg-gray-50 px-4 py-4">
                <form action="{{ route('admin.schedule-templates.update', $template->id) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama Template</label>
                            <input type="text" name="name" value="{{ $template->name }}"
                                class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Deskripsi (opsional)</label>
                            <input type="text" name="description" value="{{ $template->description }}"
                                class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-2 mb-4">
                        @for($i = 1; $i <= 7; $i++)
                            @php
                                $day = $template->days->firstWhere('day_of_week', $i);
                                $selectedShift = $day?->shift;
                            @endphp
                            <div class="text-center">
                                <div class="text-[10px] font-bold text-gray-500 uppercase mb-1">{{ $dayNames[$i-1] }}</div>
                                <select name="days[{{ $i }}]"
                                    class="w-full px-1 py-2 text-[11px] border rounded-lg outline-none text-center font-semibold transition-all focus:border-indigo-500"
                                    onchange="this.style.backgroundColor=this.options[this.selectedIndex].dataset.color||'#f3f4f6';this.style.color='white'"
                                    style="background-color:{{ $selectedShift?->color ?? '#f3f4f6' }};color:{{ $selectedShift ? 'white' : '#374151' }}">
                                    @foreach($shifts as $shift)
                                        <option value="{{ $shift->id }}" data-color="{{ $shift->color }}"
                                            {{ $day && $day->shift_id == $shift->id ? 'selected' : '' }}>
                                            {{ $shift->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endfor
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit"
                            class="inline-flex items-center gap-1 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Perubahan
                        </button>
                        <form action="{{ route('admin.schedule-templates.destroy', $template->id) }}" method="POST"
                              onsubmit="return confirm('Hapus template {{ $template->name }}? Karyawan yang di-assign akan kehilangan jadwal otomatis.')">
                            @csrf @method('DELETE')
                            <button class="text-[11px] font-semibold text-red-500 hover:text-red-700 transition-all cursor-pointer bg-transparent border-0 inline-flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span> Hapus Template
                            </button>
                        </form>
                    </div>
                </form>
            </div>

            {{-- Panel Assign Karyawan (tersembunyi) --}}
            <div id="assignPanel-{{ $template->id }}" class="hidden border-t border-emerald-100 bg-emerald-50/40 px-4 py-4">
                <form action="{{ route('admin.schedule-templates.assign') }}" method="POST">
                    @csrf
                    <input type="hidden" name="template_id" value="{{ $template->id }}">

                    <div class="flex items-center justify-between mb-3">
                        <div class="text-[12px] font-bold text-gray-700">
                            Pilih karyawan untuk template ini
                            <span class="ml-1 text-[10px] font-normal text-gray-400">(centang = assign, hapus centang = lepas)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="selectAllTpl({{ $template->id }}, true)"
                                class="text-[10px] font-semibold text-emerald-600 underline cursor-pointer bg-transparent border-0">Pilih Semua</button>
                            <button type="button" onclick="selectAllTpl({{ $template->id }}, false)"
                                class="text-[10px] font-semibold text-gray-400 underline cursor-pointer bg-transparent border-0">Batal Semua</button>
                        </div>
                    </div>

                    {{-- Search --}}
                    <input type="text" placeholder="🔍 Cari nama karyawan..."
                        oninput="filterEmpList({{ $template->id }}, this.value)"
                        class="w-full mb-3 px-3 py-1.5 text-[12px] border border-gray-200 rounded-lg outline-none focus:border-emerald-400 bg-white">

                    {{-- Daftar karyawan --}}
                    <div class="max-h-[260px] overflow-y-auto rounded-lg border border-gray-200 bg-white divide-y divide-gray-50"
                         id="empList-{{ $template->id }}">
                        @foreach($allEmployees as $emp)
                            @php
                                $isAssignedHere   = in_array($emp->id, $assignedIds);
                                $otherTemplate    = (!$isAssignedHere && $emp->scheduleTemplate) ? $emp->scheduleTemplate->name : null;
                            @endphp
                            <label class="emp-row flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-emerald-50/60 transition-all"
                                   data-name="{{ strtolower($emp->full_name) }}">
                                <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}"
                                    class="accent-emerald-500 w-3.5 h-3.5 shrink-0"
                                    {{ $isAssignedHere ? 'checked' : '' }}>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12px] font-semibold text-gray-800 truncate">{{ $emp->full_name }}</div>
                                    @if($isAssignedHere)
                                        <div class="text-[10px] text-emerald-600 font-medium">✓ Template ini</div>
                                    @elseif($otherTemplate)
                                        <div class="text-[10px] text-amber-600">⚠ Template lain: {{ $otherTemplate }}</div>
                                    @else
                                        <div class="text-[10px] text-gray-400">Belum ada template</div>
                                    @endif
                                </div>
                                @if($isAssignedHere)
                                    <span class="shrink-0 w-2 h-2 rounded-full bg-emerald-400"></span>
                                @elseif($otherTemplate)
                                    <span class="shrink-0 w-2 h-2 rounded-full bg-amber-400"></span>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-all cursor-pointer shadow-sm">
                            <span class="material-symbols-outlined text-[14px] align-text-bottom">sync</span>
                            Simpan Assignment
                        </button>
                        <span class="text-[10px] text-gray-400">Perubahan langsung disimpan ke semua karyawan</span>
                    </div>
                </form>
            </div>

        </div>
        @endforeach

        {{-- ── Buat Template Baru ── --}}
        <div class="border-2 border-dashed border-gray-200 rounded-xl p-5 bg-gray-50/50">
            <h4 class="text-[13px] font-bold text-gray-700 mb-4 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[14px]">add_circle</span> Buat Template Baru
            </h4>
            <form action="{{ route('admin.schedule-templates.store') }}" method="POST">
                @csrf
                @php $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']; @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama Template</label>
                        <input type="text" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500"
                               placeholder="misal: 5 Hari Kerja (Pagi)" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Deskripsi (opsional)</label>
                        <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500"
                               placeholder="misal: Senin–Jumat shift pagi">
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-2 mb-4">
                    @for($i = 1; $i <= 7; $i++)
                        <div class="text-center">
                            <div class="text-[10px] font-bold text-gray-500 uppercase mb-1">{{ $dayNames[$i-1] }}</div>
                            <select name="days[{{ $i }}]"
                                class="w-full px-1 py-2 text-[11px] border border-gray-300 rounded-lg outline-none text-center font-semibold focus:border-indigo-500" required
                                onchange="this.style.backgroundColor=this.options[this.selectedIndex].dataset.color||'#f3f4f6';this.style.color='white'">
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}" data-color="{{ $shift->color }}"
                                        {{ ($i >= 6 && $shift->is_off) ? 'selected' : (($i < 6 && $shift->sort_order == 1) ? 'selected' : '') }}>
                                        {{ $shift->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
                <button type="submit"
                    class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Buat Template
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAssignPanel(id) {
    const ap = document.getElementById('assignPanel-' + id);
    const ep = document.getElementById('editPanel-' + id);
    ep.classList.add('hidden');
    ap.classList.toggle('hidden');
    if (!ap.classList.contains('hidden')) {
        ap.querySelector('input[type=text]')?.focus();
    }
}

function toggleEditPanel(id) {
    const ap = document.getElementById('assignPanel-' + id);
    const ep = document.getElementById('editPanel-' + id);
    ap.classList.add('hidden');
    ep.classList.toggle('hidden');
}

function selectAllTpl(id, checked) {
    document.querySelectorAll('#empList-' + id + ' input[type=checkbox]').forEach(c => {
        if (!c.closest('.emp-row').classList.contains('hidden')) {
            c.checked = checked;
        }
    });
}

function filterEmpList(id, q) {
    const term = q.toLowerCase().trim();
    document.querySelectorAll('#empList-' + id + ' .emp-row').forEach(row => {
        const name = row.dataset.name || '';
        row.classList.toggle('hidden', term !== '' && !name.includes(term));
    });
}
</script>
@endsection
