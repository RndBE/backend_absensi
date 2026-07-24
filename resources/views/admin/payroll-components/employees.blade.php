@extends('admin.layouts.app')
@section('title', 'Karyawan — ' . $component->name)

@section('content')
@php
    $adminPermission = app(\App\Support\AdminPermission::class);
    $canManagePayrollMaster = $adminPermission->can($currentAdmin, 'payroll.master.manage');
@endphp

{{-- ── BREADCRUMB + HEADER ── --}}
<div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.payroll-components.index') }}"
           class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span> Komponen Gaji
        </a>
        <span class="text-gray-300">/</span>
        <span class="text-[13px] font-semibold text-gray-700">{{ $component->name }}</span>
    </div>
    <div class="flex items-center gap-2">
        <span class="px-3 py-1 text-[11px] font-bold rounded-full
            {{ $component->type === 'earning' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
            {{ ucfirst($component->type) }}
        </span>
        <span class="px-3 py-1 text-[11px] font-semibold text-gray-600 bg-gray-100 rounded-full">
            Default: Rp {{ number_format($component->default_amount, 0, ',', '.') }}
        </span>
    </div>
</div>

@if(session('success'))
<div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-[13px] font-medium">
    <span class="material-symbols-outlined text-[16px]">check_circle</span> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-[13px] font-medium">
    <span class="material-symbols-outlined text-[16px]">error</span> {{ session('error') }}
</div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ══════════════════════════════════════════ --}}
    {{-- PANEL KIRI: Form assign karyawan baru      --}}
    {{-- ══════════════════════════════════════════ --}}
    @if($canManagePayrollMaster)
    <div class="xl:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-[13.5px] font-bold text-gray-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[17px] text-indigo-500">person_add</span>
                    Tambah Karyawan
                </h3>
                <p class="text-[11px] text-gray-400 mt-0.5">Pilih satu atau lebih karyawan, atur nominal</p>
            </div>
            <div class="p-5">
                <form action="{{ route('admin.payroll-components.assign-employee', $component->id) }}" method="POST">
                    @csrf

                    {{-- Search + multi-select karyawan --}}
                    <div class="mb-4">
                        <label class="block text-[11.5px] font-semibold text-gray-600 mb-1.5">Pilih Karyawan</label>
                        <div class="relative mb-2">
                            <input type="text" id="empSearch" placeholder="Cari nama / kode..."
                                class="w-full px-3 py-2 text-[12px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            {{-- Select All --}}
                            <label class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 border-b border-gray-100 cursor-pointer hover:bg-gray-100 text-[11.5px] font-semibold text-gray-600">
                                <input type="checkbox" id="selectAll" class="w-3.5 h-3.5 rounded accent-indigo-600">
                                Pilih Semua ({{ $unassigned->count() }})
                            </label>
                            <div id="empList" class="max-h-56 overflow-y-auto divide-y divide-gray-50">
                                @forelse($unassigned as $emp)
                                <label class="emp-item flex items-center gap-2.5 px-3 py-2.5 cursor-pointer hover:bg-indigo-50/60 transition-colors"
                                       data-name="{{ strtolower($emp->full_name) }} {{ strtolower($emp->employee_code) }}">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}"
                                           class="emp-check w-3.5 h-3.5 rounded accent-indigo-600">
                                    <div class="min-w-0">
                                        <div class="text-[12px] font-semibold text-gray-800 truncate">{{ $emp->full_name }}</div>
                                        <div class="text-[10.5px] text-gray-400">{{ $emp->employee_code }} &bull; {{ $emp->department->name ?? '-' }}</div>
                                    </div>
                                </label>
                                @empty
                                <div class="px-3 py-6 text-center text-[12px] text-gray-400">
                                    <span class="material-symbols-outlined text-[28px] block mb-1 text-gray-300">group_off</span>
                                    Semua karyawan sudah di-assign
                                </div>
                                @endforelse
                            </div>
                        </div>
                        <p class="text-[10.5px] text-gray-400 mt-1" id="selectedCount">0 karyawan dipilih</p>
                    </div>

                    {{-- Nominal --}}
                    <div class="mb-4">
                        <label class="block text-[11.5px] font-semibold text-gray-600 mb-1.5">
                            Nominal
                            <span class="font-normal text-gray-400">(kosong = default)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 font-semibold">Rp</span>
                            <input type="hidden" name="amount" id="amountRaw" value="{{ $component->default_amount }}">
                            <input type="text" id="amountDisplay" value="{{ number_format($component->default_amount, 0, ',', '.') }}"
                                inputmode="numeric" autocomplete="off"
                                class="currency-fmt w-full pl-8 pr-3 py-2 text-[12px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                        </div>
                    </div>

                    {{-- Tanggal mulai --}}
                    <div class="mb-5">
                        <label class="block text-[11.5px] font-semibold text-gray-600 mb-1.5">Tanggal Mulai</label>
                        <input type="date" name="start_date" value="{{ date('Y-m-d') }}"
                            class="w-full px-3 py-2 text-[12px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                    </div>

                    <button type="submit" id="assignBtn" disabled
                        class="w-full py-2.5 text-[12.5px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        <span class="material-symbols-outlined text-[15px] align-text-bottom">person_add</span>
                        Assign Karyawan
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════ --}}
    {{-- PANEL KANAN: Daftar karyawan ter-assign   --}}
    {{-- ══════════════════════════════════════════ --}}
    <div class="{{ $canManagePayrollMaster ? 'xl:col-span-2' : 'xl:col-span-3' }}">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-[13.5px] font-bold text-gray-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[17px] text-emerald-500">group</span>
                        Karyawan Ter-assign
                        <span class="text-[11px] font-normal text-gray-400">({{ $assignments->count() }} karyawan)</span>
                    </h3>
                </div>
                <form action="{{ route('admin.payroll-components.employees', $component->id) }}" method="GET">
                    <div class="relative">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-[14px] text-gray-400">search</span>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari karyawan..."
                            class="pl-7 pr-3 py-1.5 text-[12px] border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 outline-none w-52">
                    </div>
                </form>
            </div>

            <div class="divide-y divide-gray-50">
                @forelse($assignments as $assign)
                <div class="px-5 py-3.5 flex items-center justify-between gap-3 hover:bg-gray-50/60 transition-colors
                            {{ !$assign->is_active ? 'opacity-50' : '' }}" id="row-{{ $assign->id }}">

                    {{-- Avatar + Info --}}
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">
                            {{ strtoupper(substr($assign->employee->full_name ?? '?', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-[12.5px] font-semibold text-gray-800 truncate">{{ $assign->employee->full_name ?? '-' }}</div>
                            <div class="text-[10.5px] text-gray-400">
                                {{ $assign->employee->employee_code ?? '-' }}
                                &bull; {{ $assign->employee->department->name ?? '-' }}
                                @if(!$assign->is_active)
                                <span class="ml-1 text-red-400 font-semibold">· nonaktif</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Amount + actions --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @if($canManagePayrollMaster)
                        {{-- Inline edit form --}}
                        <form action="{{ route('admin.payroll-components.update-assignment', [$component->id, $assign->id]) }}"
                              method="POST" class="flex flex-wrap items-center justify-end gap-1.5 edit-form hidden" id="edit-{{ $assign->id }}">
                            @csrf @method('PUT')
                            <div class="relative">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">Rp</span>
                                <input type="hidden" name="amount" id="raw-{{ $assign->id }}" value="{{ $assign->amount }}">
                                <input type="text" id="disp-{{ $assign->id }}"
                                    value="{{ number_format($assign->amount, 0, ',', '.') }}"
                                    inputmode="numeric" autocomplete="off"
                                    data-raw="raw-{{ $assign->id }}"
                                    class="inline-fmt pl-6 pr-2 py-1.5 text-[12px] border border-indigo-300 rounded-lg w-32 focus:ring-2 focus:ring-indigo-300 outline-none">
                            </div>
                            <input type="date" name="start_date" value="{{ optional($assign->start_date)->format('Y-m-d') }}"
                                title="Tanggal mulai"
                                class="px-2 py-1.5 text-[12px] border border-indigo-300 rounded-lg w-36 focus:ring-2 focus:ring-indigo-300 outline-none">
                            <input type="hidden" name="is_active" value="{{ $assign->is_active ? 1 : 0 }}">
                            <button type="submit" class="px-2.5 py-1.5 text-[11px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                Simpan
                            </button>
                            <button type="button" onclick="cancelEdit({{ $assign->id }})"
                                class="px-2 py-1.5 text-[11px] text-gray-500 hover:text-gray-700 cursor-pointer">
                                Batal
                            </button>
                        </form>

                        {{-- Display amount --}}
                        <div class="amount-display text-right" id="amt-{{ $assign->id }}">
                            <div class="text-[13px] font-bold {{ $component->type === 'earning' ? 'text-emerald-600' : 'text-red-500' }}">
                                Rp {{ number_format($assign->amount, 0, ',', '.') }}
                            </div>
                            <div class="text-[10.5px] text-gray-400">
                                Mulai {{ optional($assign->start_date)->format('d/m/Y') ?? '-' }}
                            </div>
                        </div>

                        {{-- Edit button --}}
                        <button onclick="openEdit({{ $assign->id }})"
                            class="edit-btn p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-[15px]">edit</span>
                        </button>

                        {{-- Toggle active --}}
                        <form action="{{ route('admin.payroll-components.update-assignment', [$component->id, $assign->id]) }}"
                              method="POST">
                            @csrf @method('PUT')
                            <input type="hidden" name="amount" value="{{ $assign->amount }}">
                            <input type="hidden" name="is_active" value="{{ $assign->is_active ? 0 : 1 }}">
                            <button type="submit" title="{{ $assign->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                class="p-1.5 rounded-lg transition-colors cursor-pointer
                                       {{ $assign->is_active ? 'text-emerald-500 hover:bg-emerald-50' : 'text-gray-400 hover:bg-gray-100' }}">
                                <span class="material-symbols-outlined text-[15px]">{{ $assign->is_active ? 'toggle_on' : 'toggle_off' }}</span>
                            </button>
                        </form>

                        {{-- Remove --}}
                        <form action="{{ route('admin.payroll-components.remove-assignment', [$component->id, $assign->id]) }}"
                              method="POST" data-confirm="Hapus karyawan ini dari komponen?">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="p-1.5 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]">delete</span>
                            </button>
                        </form>
                        @else
                        <div class="text-right">
                            <div class="text-[13px] font-bold {{ $component->type === 'earning' ? 'text-emerald-600' : 'text-red-500' }}">
                                Rp {{ number_format($assign->amount, 0, ',', '.') }}
                            </div>
                            <div class="text-[10.5px] text-gray-400">
                                Mulai {{ optional($assign->start_date)->format('d/m/Y') ?? '-' }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="py-16 text-center">
                    <span class="material-symbols-outlined text-[40px] text-gray-300 block mb-2">group_off</span>
                    <p class="text-[13px] text-gray-400">Belum ada karyawan yang di-assign</p>
                    <p class="text-[11px] text-gray-300 mt-1">Tambah karyawan via panel sebelah kiri</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

</div>

<script>
// ── Currency formatter helpers ──
function rawNum(str)    { return parseInt(str.replace(/\./g, '').replace(/[^\d]/g, '')) || 0; }
function fmtNum(n)      { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

// Assign form — big amount input
document.getElementById('amountDisplay')?.addEventListener('input', function () {
    const raw = rawNum(this.value);
    this.value = fmtNum(raw);
    document.getElementById('amountRaw').value = raw;
});

// Inline edit forms — .inline-fmt inputs
document.querySelectorAll('.inline-fmt').forEach(function (el) {
    el.addEventListener('input', function () {
        const raw = rawNum(this.value);
        this.value = fmtNum(raw);
        document.getElementById(this.dataset.raw).value = raw;
    });
});

// ── Search filter dalam list karyawan ──
document.getElementById('empSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.emp-item').forEach(item => {
        item.style.display = item.dataset.name.includes(q) ? '' : 'none';
    });
});

// ── Select All ──
document.getElementById('selectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.emp-check').forEach(cb => {
        if (cb.closest('.emp-item').style.display !== 'none') cb.checked = this.checked;
    });
    updateCount();
});

// ── Count selected ──
function updateCount() {
    const n = document.querySelectorAll('.emp-check:checked').length;
    const selectedCount = document.getElementById('selectedCount');
    const assignBtn = document.getElementById('assignBtn');
    if (selectedCount) selectedCount.textContent = n + ' karyawan dipilih';
    if (assignBtn) assignBtn.disabled = n === 0;
}
document.querySelectorAll('.emp-check').forEach(cb => cb.addEventListener('change', updateCount));

// ── Inline edit ──
function openEdit(id) {
    document.getElementById('edit-' + id).classList.remove('hidden');
    document.getElementById('amt-' + id).classList.add('hidden');
    document.querySelector('#row-' + id + ' .edit-btn').classList.add('hidden');
}
function cancelEdit(id) {
    document.getElementById('edit-' + id).classList.add('hidden');
    document.getElementById('amt-' + id).classList.remove('hidden');
    document.querySelector('#row-' + id + ' .edit-btn').classList.remove('hidden');
}
</script>
@endsection
