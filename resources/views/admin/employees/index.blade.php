@extends('admin.layouts.app')
@section('title', 'Manajemen Karyawan')

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canExportEmployee = $adminPermission->can($currentAdmin, 'employees.view');
    $canCreateEmployee = $adminPermission->can($currentAdmin, 'employees.create');
    $canUpdateEmployee = $adminPermission->can($currentAdmin, 'employees.update');
    $canDeleteEmployee = $adminPermission->can($currentAdmin, 'employees.delete');
    // Manager: sembunyikan kolom status kepegawaian.
    $hideStatus = ($currentAdmin?->role ?? '') === 'manager';
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">group</span> Daftar Karyawan</h3>
        <div class="flex items-center gap-2 flex-wrap">
            @if($canUpdateEmployee)
                <form method="POST" action="{{ route('admin.employees.portal-link.send-all') }}" class="inline">
                    @csrf
                    <button data-confirm="Kirim link portal ke semua karyawan aktif yang memiliki email?" data-confirm-text="Kirim Semua" data-confirm-variant="primary" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-cyan-700 bg-cyan-50 border border-cyan-200 rounded-lg hover:bg-cyan-100 transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">outgoing_mail</span> Kirim Link Portal ke Semua
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.employees.portal-link.whatsapp.send-all') }}" class="inline">
                    @csrf
                    <button data-confirm="Kirim link portal via WhatsApp ke semua karyawan aktif yang memiliki nomor HP?" data-confirm-text="Kirim WhatsApp" data-confirm-variant="primary" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">chat</span> Kirim WhatsApp ke Semua
                    </button>
                </form>
            @endif
            @if($canExportEmployee)
            <a href="{{ route('admin.employees.export', request()->query()) }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                <span class="material-symbols-outlined text-[16px]">download</span> Export Excel
            </a>
            @endif
            @if($canCreateEmployee)
            <a href="{{ route('admin.employees.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-[0_2px_8px_rgba(79,70,229,0.3)] hover:shadow-[0_4px_12px_rgba(79,70,229,0.4)] hover:-translate-y-0.5 transition-all duration-200">+ Tambah Karyawan</a>
            @endif
        </div>
    </div>
    <div class="p-5">
        {{-- Filters --}}
        <form method="GET" id="employeeFilterForm" class="flex items-center gap-3 mb-5 flex-wrap">
            <input type="search" id="employeeSearch"
                   class="w-full max-w-[280px] h-[42px] px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 bg-white outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400"
                   placeholder="Cari nama, kode, email...">
            <select name="department_id" onchange="document.getElementById('employeeFilterForm').submit()" class="w-full max-w-[280px] h-[42px] px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 bg-white outline-none appearance-none bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                <option value="">Semua Departemen</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
            @unless($hideStatus)
            <select name="status" onchange="document.getElementById('employeeFilterForm').submit()" class="w-full max-w-[280px] h-[42px] px-3.5 py-2.5 border border-gray-300 rounded-lg text-[13.5px] text-gray-800 bg-white outline-none appearance-none bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_10px_center] bg-no-repeat bg-[length:16px] pr-9 transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10">
                <option value="">Semua Status</option>
                <option value="permanent" {{ request('status') === 'permanent' ? 'selected' : '' }}>Tetap</option>
                <option value="contract" {{ request('status') === 'contract' ? 'selected' : '' }}>Kontrak</option>
                <option value="intern" {{ request('status') === 'intern' ? 'selected' : '' }}>Magang</option>
                <option value="probation" {{ request('status') === 'probation' ? 'selected' : '' }}>Probation</option>
                <option value="outsourcing" {{ request('status') === 'outsourcing' ? 'selected' : '' }}>Outsourcing</option>
            </select>
            @endunless
            @if(request()->filled('department_id') || request()->filled('status'))
                <a href="{{ route('admin.employees.index') }}" class="inline-flex items-center px-3 py-2.5 text-xs font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all duration-200">Reset</a>
            @endif
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Karyawan</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Kode</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Departemen</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Posisi</th>
                        @unless($hideStatus)
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Status</th>
                        @endunless
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Bergabung</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr class="hover:bg-gray-50 transition-colors" data-fuse-row="employee" data-search="{{ e($emp->full_name . ' ' . $emp->employee_code . ' ' . $emp->email . ' ' . ($emp->department->name ?? '') . ' ' . ($emp->position ?? '') . ' ' . $emp->employment_status) }}">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <a href="{{ route('admin.employees.show', $emp->id) }}" class="flex items-center gap-2.5 group">
                                @if($emp->photo)
                                    <img src="{{ asset('storage/' . $emp->photo) }}" alt="{{ $emp->full_name }}" class="w-9 h-9 rounded-full object-cover shrink-0 border border-gray-200">
                                @else
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[13px] font-bold shrink-0">{{ substr($emp->full_name, 0, 1) }}</div>
                                @endif
                                <div>
                                    <div class="text-[13.5px] font-semibold text-gray-800 group-hover:text-indigo-600 transition-colors">{{ $emp->full_name }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $emp->email }}</div>
                                </div>
                            </a>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $emp->employee_code }}</code></td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $emp->department->name ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-[13.5px] text-gray-700 border-b border-gray-100">{{ $emp->position ?? '-' }}</td>
                        @unless($hideStatus)
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            @if($emp->employment_status === 'permanent')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-100 text-emerald-800">Tetap</span>
                            @elseif($emp->employment_status === 'contract')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-blue-100 text-blue-800">Kontrak</span>
                            @elseif($emp->employment_status === 'intern')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-gray-100 text-gray-600">Magang</span>
                            @elseif($emp->employment_status === 'probation')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-amber-100 text-amber-800">Probation</span>
                            @elseif($emp->employment_status === 'outsourcing')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-cyan-100 text-cyan-800">Outsourcing</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-slate-100 text-slate-700">{{ ucfirst($emp->employment_status ?? '-') }}</span>
                            @endif
                        </td>
                        @endunless
                        <td class="px-4 py-3.5 text-[13px] text-gray-700 border-b border-gray-100">{{ $emp->join_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex gap-1.5">
                                <a href="{{ route('admin.employees.show', $emp->id) }}" class="inline-flex items-center justify-center w-8 h-8 text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all duration-200" title="Detail"><span class="material-symbols-outlined text-[16px]">visibility</span></a>
                                @if($canUpdateEmployee)
                                    <a href="{{ route('admin.employees.edit', $emp->id) }}" class="inline-flex items-center justify-center w-8 h-8 text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 transition-all duration-200" title="Edit"><span class="material-symbols-outlined text-[16px]">edit</span></a>
                                    @if($emp->is_active && $emp->email)
                                        <form method="POST" action="{{ route('admin.employees.portal-link.send', $emp->id) }}" class="inline">
                                            @csrf
                                            <button data-confirm="Kirim link portal ke {{ $emp->email }}?" data-confirm-text="Kirim" data-confirm-variant="primary" class="inline-flex items-center justify-center w-8 h-8 text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-cyan-50 hover:text-cyan-600 hover:border-cyan-200 transition-all duration-200 cursor-pointer" title="Kirim Link Portal">
                                                <span class="material-symbols-outlined text-[16px]">outgoing_mail</span>
                                            </button>
                                        </form>
                                    @endif
                                    @if($emp->is_active && $emp->phone)
                                        <form method="POST" action="{{ route('admin.employees.portal-link.whatsapp.send', $emp->id) }}" class="inline">
                                            @csrf
                                            <button data-confirm="Kirim link portal via WhatsApp ke {{ $emp->phone }}?" data-confirm-text="Kirim WhatsApp" data-confirm-variant="primary" class="inline-flex items-center justify-center w-8 h-8 text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-200 transition-all duration-200 cursor-pointer" title="Kirim Link Portal WhatsApp">
                                                <span class="material-symbols-outlined text-[16px]">chat</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                @if($canDeleteEmployee)
                                    <a href="{{ route('admin.employees.resign', $emp->id) }}" class="inline-flex items-center justify-center w-8 h-8 text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all duration-200" title="Proses Resign"><span class="material-symbols-outlined text-[16px]">person_remove</span></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $hideStatus ? 6 : 7 }}" class="text-center py-12 text-gray-400 text-sm">Tidak ada karyawan ditemukan</td>
                    </tr>
                    @endforelse
                    <tr id="employeeFuseEmpty" class="hidden">
                        <td colspan="{{ $hideStatus ? 6 : 7 }}" class="text-center py-12 text-gray-400 text-sm">Tidak ada karyawan yang cocok dengan pencarian</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0"></script>
<script>
const employeeSearch = document.getElementById('employeeSearch');
const employeeEmpty = document.getElementById('employeeFuseEmpty');
const employeeItems = Array.from(document.querySelectorAll('[data-fuse-row="employee"]')).map((row, index) => ({
    index,
    row,
    text: row.dataset.search || '',
}));
const employeeFuse = window.Fuse ? new Fuse(employeeItems, {
    keys: ['text'],
    threshold: 0.45,
    ignoreLocation: true,
}) : null;

function applyEmployeeSearch() {
    if (!employeeSearch) return;

    const keyword = employeeSearch.value.trim();
    const matched = keyword && employeeFuse
        ? new Set(employeeFuse.search(keyword).map((result) => result.item.index))
        : new Set(employeeItems.map((item) => item.index));
    let visibleCount = 0;

    employeeItems.forEach((item) => {
        const isVisible = matched.has(item.index);
        item.row.classList.toggle('hidden', !isVisible);
        if (isVisible) visibleCount++;
    });

    if (employeeEmpty) {
        employeeEmpty.classList.toggle('hidden', !keyword || visibleCount > 0);
    }
}

if (employeeSearch) {
    employeeSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    employeeSearch.addEventListener('input', applyEmployeeSearch);
    applyEmployeeSearch();
}
</script>
@endsection
