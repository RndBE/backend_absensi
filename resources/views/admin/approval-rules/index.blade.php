@extends('admin.layouts.app')
@section('title', 'Pengaturan Approval')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">settings</span> Pengaturan Approval</h3>
            <p class="text-[12px] text-gray-400 mt-0.5">Rekap pengaturan approval seluruh karyawan. Klik tombol edit untuk mengubah.</p>
        </div>
    </div>
    <div class="p-5">
        {{-- Stats --}}
        <div class="flex gap-4 mb-5">
            <div class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-xl">
                <span class="material-symbols-outlined text-[18px] text-emerald-600">check_circle</span>
                <div>
                    <div class="text-[18px] font-bold text-emerald-700">{{ $configured }}</div>
                    <div class="text-[11px] text-emerald-600 font-medium">Sudah diatur</div>
                </div>
            </div>
            <div class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-xl">
                <span class="material-symbols-outlined text-[18px] text-amber-600">warning</span>
                <div>
                    <div class="text-[18px] font-bold text-amber-700">{{ $unconfigured }}</div>
                    <div class="text-[11px] text-amber-600 font-medium">Belum diatur</div>
                </div>
            </div>
        </div>

        {{-- Type tabs --}}
        <div class="flex gap-0 border-b-2 border-gray-200 mb-5">
            @foreach($types as $typeKey => $typeLabel)
                <a href="{{ route('admin.approval-rules.index', ['type' => $typeKey]) }}"
                   class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                          {{ $activeType === $typeKey ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">
                    {{ $typeLabel }}
                </a>
            @endforeach
        </div>

        {{-- Employee approval table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                        <th class="pb-3 pr-3">Karyawan</th>
                        <th class="pb-3 pr-3">Departemen</th>
                        <th class="pb-3 pr-3">Approval Chain ({{ $types[$activeType] }})</th>
                        <th class="pb-3 w-20 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($employees as $emp)
                    @php $chain = $allChains[$emp->id] ?? collect(); @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-3 pr-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[11px] font-bold shrink-0 overflow-hidden">
                                    @if($emp->photo)
                                        <img src="{{ asset('storage/' . $emp->photo) }}" class="w-full h-full object-cover">
                                    @else
                                        {{ substr($emp->full_name, 0, 1) }}
                                    @endif
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-800">{{ $emp->full_name }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $emp->position ?? '-' }} · Lv{{ $emp->job_level ?? '?' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 pr-3 text-gray-600">{{ $emp->department?->name ?? '-' }}</td>
                        <td class="py-3 pr-3">
                            @if($chain->count())
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @foreach($chain->sortBy('step_order') as $step)
                                        @if(!$loop->first)
                                            <span class="text-gray-300 text-[11px]">→</span>
                                        @endif
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-600">
                                            <span class="w-4 h-4 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[9px]">{{ $step->step_order }}</span>
                                            {{ $step->approver?->full_name ?? '?' }}
                                        </span>
                                    @endforeach
                                    <span class="text-gray-300 text-[11px]">→</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-600">✓</span>
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-amber-50 text-amber-600 border border-amber-200">
                                    <span class="material-symbols-outlined text-[12px]">warning</span> Belum diatur
                                </span>
                            @endif
                        </td>
                        <td class="py-3 text-center">
                            <a href="{{ route('admin.employees.edit', $emp->id) }}#approvalSection"
                               class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[14px]">edit</span> Edit
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Bulk Assign --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mt-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between cursor-pointer" onclick="document.getElementById('bulkPanel').classList.toggle('hidden')">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">group_add</span> Bulk Assign Approval</h3>
            <p class="text-[12px] text-gray-400 mt-0.5">Terapkan approval chain yang sama ke beberapa karyawan sekaligus.</p>
        </div>
        <span class="material-symbols-outlined text-gray-400">expand_more</span>
    </div>
    <div class="p-5 hidden" id="bulkPanel">
        <form action="{{ route('admin.approval-rules.bulk-assign') }}" method="POST" onsubmit="return validateBulk(this)">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Left: Employee Selection --}}
                <div>
                    <label class="block text-[13px] font-bold text-gray-700 mb-2">1. Pilih Karyawan</label>
                    <div class="flex items-center gap-2 mb-2">
                        <input type="text" id="empSearch" placeholder="Cari karyawan..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" oninput="filterEmployees()">
                        <button type="button" onclick="toggleAllEmployees()" class="px-3 py-2 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 cursor-pointer">Pilih Semua</button>
                    </div>
                    <div class="border border-gray-200 rounded-lg max-h-[300px] overflow-y-auto divide-y divide-gray-100" id="empList">
                        @foreach($employees as $emp)
                        <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 cursor-pointer emp-item" data-name="{{ strtolower($emp->full_name) }}">
                            <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" class="accent-indigo-600 w-4 h-4 emp-checkbox">
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-medium text-gray-800 truncate">{{ $emp->full_name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $emp->department?->name ?? '-' }} · {{ $emp->position ?? '-' }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    <div class="text-[11px] text-gray-400 mt-1"><span id="selectedCount">0</span> karyawan dipilih</div>
                </div>

                {{-- Right: Chain + Type --}}
                <div>
                    <label class="block text-[13px] font-bold text-gray-700 mb-2">2. Tentukan Approval Chain</label>
                    <div id="bulkChain" class="space-y-2 mb-3">
                        {{-- Steps will be added here --}}
                    </div>
                    <button type="button" onclick="addBulkStep()" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 cursor-pointer mb-5">
                        <span class="material-symbols-outlined text-[14px]">add</span> Tambah Step
                    </button>

                    {{-- Flow preview --}}
                    <div class="flex items-center gap-1.5 py-2 px-3 bg-gray-50 rounded-lg mb-5" id="bulkFlow">
                        <span class="text-[11px] font-semibold text-gray-500">Flow:</span>
                        <span class="text-[11px] text-gray-400">Tambahkan step di atas</span>
                    </div>

                    <label class="block text-[13px] font-bold text-gray-700 mb-2">3. Terapkan untuk Tipe</label>
                    <div class="flex gap-3 mb-5">
                        <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="apply_types[]" value="leave" checked class="accent-indigo-600 w-4 h-4"> <span class="text-[13px]">Cuti</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="apply_types[]" value="overtime" checked class="accent-indigo-600 w-4 h-4"> <span class="text-[13px]">Lembur</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="apply_types[]" value="attendance" checked class="accent-indigo-600 w-4 h-4"> <span class="text-[13px]">Presensi</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="apply_types[]" value="budget" checked class="accent-indigo-600 w-4 h-4"> <span class="text-[13px]">Anggaran</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="apply_types[]" value="travel_report" checked class="accent-indigo-600 w-4 h-4"> <span class="text-[13px]">LHP</span>
                        </label>
                    </div>

                    <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[14px] align-text-bottom">bolt</span> Terapkan ke Semua yang Dipilih
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const bulkManagers = @json($employees->map(fn($m) => ['id' => $m->id, 'name' => $m->full_name, 'label' => 'Lv'.($m->job_level ?? '?').' — '.$m->full_name.' ('.($m->position ?? '-').')']));

function addBulkStep() {
    const chain = document.getElementById('bulkChain');
    const count = chain.querySelectorAll('.bulk-step').length;
    const opts = bulkManagers.map(m => `<option value="${m.id}">${m.label}</option>`).join('');
    chain.insertAdjacentHTML('beforeend', `<div class="flex items-center gap-3 bulk-step">
        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center text-[12px] font-bold shrink-0 bs-num">${count + 1}</div>
        <select name="approver_ids[]" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-[13px] outline-none focus:border-indigo-500" onchange="updateBulkFlow()">
            <option value="">Pilih Approver</option>${opts}
        </select>
        <button type="button" onclick="removeBulkStep(this)" class="w-8 h-8 flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all cursor-pointer"><span class="material-symbols-outlined text-[18px]">close</span></button>
    </div>`);
    // Init searchable select on the newly added select
    const newSelect = chain.querySelector('.bulk-step:last-child select');
    if (newSelect && typeof initSearchableSelect === 'function') {
        initSearchableSelect(newSelect);
    }
    updateBulkFlow();
}

function removeBulkStep(btn) {
    const step = btn.closest('.bulk-step');
    step.remove();
    document.querySelectorAll('#bulkChain .bulk-step').forEach((r, i) => r.querySelector('.bs-num').textContent = i + 1);
    updateBulkFlow();
}

function updateBulkFlow() {
    const flow = document.getElementById('bulkFlow');
    const selects = document.querySelectorAll('#bulkChain select');
    let html = '<span class="text-[11px] font-semibold text-gray-500">Flow:</span><span class="text-[11px] text-gray-600">Submit</span>';
    selects.forEach(s => {
        if (s.value) {
            const name = bulkManagers.find(m => m.id == s.value)?.name || '?';
            html += `<span class="text-gray-400">→</span><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-600">${name}</span>`;
        }
    });
    html += '<span class="text-gray-400">→</span><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-600">✓ Approved</span>';
    flow.innerHTML = html;
}

function filterEmployees() {
    const q = document.getElementById('empSearch').value.toLowerCase();
    document.querySelectorAll('.emp-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}

function toggleAllEmployees() {
    const boxes = document.querySelectorAll('.emp-checkbox');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
    updateSelectedCount();
}

function updateSelectedCount() {
    document.getElementById('selectedCount').textContent = document.querySelectorAll('.emp-checkbox:checked').length;
}

document.querySelectorAll('.emp-checkbox').forEach(b => b.addEventListener('change', updateSelectedCount));

function validateBulk(form) {
    if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return true;
    }

    const emps = document.querySelectorAll('.emp-checkbox:checked').length;
    const steps = document.querySelectorAll('#bulkChain select').length;
    const types = document.querySelectorAll('input[name="apply_types[]"]:checked').length;
    if (!emps) { alert('Pilih minimal 1 karyawan'); return false; }
    if (!steps) { alert('Tambahkan minimal 1 step approval'); return false; }
    if (!types) { alert('Pilih minimal 1 tipe pengajuan'); return false; }

    showAdminConfirm(`Terapkan approval chain ke ${emps} karyawan x ${types} tipe? Ini akan menimpa pengaturan lama.`, function() {
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    return false;
}

// Auto-add first step
addBulkStep();
</script>
@endpush
