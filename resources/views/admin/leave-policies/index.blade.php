@extends('admin.layouts.app')
@section('title', 'Kebijakan Cuti')

@section('content')
<style>
.policy-employee-scroll {
    max-height: 220px;
    overflow-y: auto;
}
.policy-employee-scroll::-webkit-scrollbar {
    width: 5px;
}
.policy-employee-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.policy-employee-scroll::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 999px;
}
</style>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[14px] align-text-bottom">add</span> Tambah Kebijakan</h3>
        </div>
        <form action="{{ route('admin.leave-policies.store') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Tipe Cuti</label>
                <select name="leave_type_id" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_8px_center] bg-no-repeat bg-[length:14px] pr-8 focus:border-indigo-500">
                    <option value="">Pilih Tipe Cuti</option>
                    @foreach($leaveTypes as $lt)
                        <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Hari / Tahun</label>
                    <input type="number" name="days_per_year" value="12" min="1" max="365" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Min. Masa Kerja (bln)</label>
                    <input type="number" name="min_tenure_months" value="12" min="0" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                </div>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Max Carry Over (hari)</label>
                <input type="number" name="max_carry_over" value="0" min="0" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                <p class="text-[10px] text-gray-400 mt-1">Sisa cuti yang bisa dibawa ke tahun depan. 0 = hangus.</p>
            </div>
            <label class="flex items-center gap-2.5 cursor-pointer p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-all">
                <input type="checkbox" name="is_prorated" value="1" class="accent-indigo-500 w-4 h-4">
                <div>
                    <span class="text-[12px] font-semibold text-gray-700">Prorata</span>
                    <p class="text-[10px] text-gray-400">Hitung proporsional untuk karyawan baru</p>
                </div>
            </label>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Berlaku Untuk</label>
                <select name="eligibility_type" id="createEligibilityType" onchange="togglePolicyScope(this, 'createEmployeeScope')" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_8px_center] bg-no-repeat bg-[length:14px] pr-8 focus:border-indigo-500">
                    <option value="all">Semua karyawan</option>
                    <option value="selected">Karyawan tertentu</option>
                </select>
            </div>
            <div id="createEmployeeScope" class="hidden rounded-lg border border-gray-200 bg-gray-50 p-3">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-[12px] font-bold text-gray-700">Pilih Karyawan</span>
                    <span class="text-[10px] text-gray-400">{{ $employees->count() }} karyawan aktif</span>
                </div>
                <input type="search" oninput="filterPolicyEmployees(this, 'createEmployeeList')" placeholder="Cari karyawan..." class="w-full px-3 py-2 text-[12px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 mb-2">
                <div id="createEmployeeList" class="policy-employee-scroll space-y-1 pr-1">
                    @foreach($employees as $employee)
                        <label class="policy-employee-option flex items-center gap-2 px-2.5 py-2 rounded-lg bg-white border border-gray-100 hover:border-indigo-200 cursor-pointer" data-search="{{ strtolower($employee->full_name . ' ' . $employee->employee_code) }}">
                            <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="accent-indigo-600 w-4 h-4">
                            <span class="text-[12px] font-semibold text-gray-700">{{ $employee->full_name }}</span>
                            <span class="ml-auto text-[10px] text-gray-400">{{ $employee->employee_code }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg hover:from-indigo-700 hover:to-indigo-600 transition-all cursor-pointer shadow-sm">
                <span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Kebijakan
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">list_alt</span> Daftar Kebijakan Cuti</h3>
            <a href="{{ route('admin.leave-balances.index') }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all"><span class="material-symbols-outlined text-[14px] align-text-bottom">analytics</span> Lihat Saldo Cuti</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="py-3 px-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Tipe Cuti</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Hari/Tahun</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Min Tenure</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Carry Over</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Prorata</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Cakupan</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($policies as $policy)
                    @php
                        $selectedEmployeeIds = $policy->eligibleEmployees->pluck('id')->map(fn($id) => (int) $id)->all();
                    @endphp
                    <tr class="border-b border-gray-50 hover:bg-gray-50/30 transition-all">
                        <td class="py-3 px-4">
                            <span class="text-[13px] font-semibold text-gray-800">{{ $policy->leaveType->name }}</span>
                        </td>
                        <td class="py-3 px-4 text-center text-[13px] font-bold text-gray-800">{{ $policy->days_per_year }}</td>
                        <td class="py-3 px-4 text-center text-[13px] text-gray-600">{{ $policy->min_tenure_months }} bln</td>
                        <td class="py-3 px-4 text-center text-[13px] text-gray-600">{{ $policy->max_carry_over > 0 ? $policy->max_carry_over . ' hari' : 'Hangus' }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $policy->is_prorated ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-gray-100 text-gray-400' }}">
                                {{ $policy->is_prorated ? 'Ya' : 'Tidak' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($policy->eligibility_type === 'selected')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">{{ $policy->eligibleEmployees->count() }} karyawan</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-200">Semua</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $policy->is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-500 border border-red-200' }}">
                                {{ $policy->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button type="button" onclick="togglePolicyEdit('policyEdit{{ $policy->id }}')" class="px-2 py-1 text-[10px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 cursor-pointer transition-all"><span class="material-symbols-outlined text-[14px] align-text-bottom">tune</span></button>
                                <form action="{{ route('admin.leave-policies.destroy', $policy) }}" method="POST" class="inline" data-confirm="Hapus kebijakan ini?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2 py-1 text-[10px] font-semibold text-red-500 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 cursor-pointer transition-all"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr id="policyEdit{{ $policy->id }}" class="hidden border-b border-gray-100 bg-gray-50/80">
                        <td colspan="8" class="px-4 py-4">
                            <form action="{{ route('admin.leave-policies.update', $policy) }}" method="POST" class="space-y-4">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">Hari / Tahun</label>
                                        <input type="number" name="days_per_year" value="{{ $policy->days_per_year }}" min="1" max="365" required class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">Min. Masa Kerja</label>
                                        <input type="number" name="min_tenure_months" value="{{ $policy->min_tenure_months }}" min="0" required class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">Carry Over</label>
                                        <input type="number" name="max_carry_over" value="{{ $policy->max_carry_over }}" min="0" required class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">Cakupan</label>
                                        <select name="eligibility_type" onchange="togglePolicyScope(this, 'policyEmployees{{ $policy->id }}')" class="w-full px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none bg-white focus:border-indigo-500">
                                            <option value="all" {{ $policy->eligibility_type === 'all' ? 'selected' : '' }}>Semua karyawan</option>
                                            <option value="selected" {{ $policy->eligibility_type === 'selected' ? 'selected' : '' }}>Karyawan tertentu</option>
                                        </select>
                                    </div>
                                    <div class="flex items-end gap-3">
                                        <label class="flex items-center gap-2 text-[12px] font-semibold text-gray-700">
                                            <input type="checkbox" name="is_prorated" value="1" {{ $policy->is_prorated ? 'checked' : '' }} class="accent-indigo-500 w-4 h-4"> Prorata
                                        </label>
                                        <label class="flex items-center gap-2 text-[12px] font-semibold text-gray-700">
                                            <input type="checkbox" name="is_active" value="1" {{ $policy->is_active ? 'checked' : '' }} class="accent-emerald-500 w-4 h-4"> Aktif
                                        </label>
                                    </div>
                                </div>
                                <div id="policyEmployees{{ $policy->id }}" class="{{ $policy->eligibility_type === 'selected' ? '' : 'hidden' }} rounded-lg border border-gray-200 bg-white p-3">
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <span class="text-[12px] font-bold text-gray-700">Pilih Karyawan</span>
                                        <span class="text-[10px] text-gray-400">{{ $policy->eligibleEmployees->count() }} dipilih</span>
                                    </div>
                                    <input type="search" oninput="filterPolicyEmployees(this, 'policyEmployeeList{{ $policy->id }}')" placeholder="Cari karyawan..." class="w-full px-3 py-2 text-[12px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 mb-2">
                                    <div id="policyEmployeeList{{ $policy->id }}" class="policy-employee-scroll grid grid-cols-1 md:grid-cols-2 gap-1 pr-1">
                                        @foreach($employees as $employee)
                                            <label class="policy-employee-option flex items-center gap-2 px-2.5 py-2 rounded-lg bg-gray-50 border border-gray-100 hover:border-indigo-200 cursor-pointer" data-search="{{ strtolower($employee->full_name . ' ' . $employee->employee_code) }}">
                                                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" {{ in_array($employee->id, $selectedEmployeeIds, true) ? 'checked' : '' }} class="accent-indigo-600 w-4 h-4">
                                                <span class="text-[12px] font-semibold text-gray-700">{{ $employee->full_name }}</span>
                                                <span class="ml-auto text-[10px] text-gray-400">{{ $employee->employee_code }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center gap-1 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px]">save</span> Simpan Perubahan</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-10 text-center text-gray-400 text-sm">Belum ada kebijakan cuti. Tambahkan di form sebelah.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function togglePolicyScope(selectEl, targetId) {
    const target = document.getElementById(targetId);
    if (!target) return;
    target.classList.toggle('hidden', selectEl.value !== 'selected');
}

function togglePolicyEdit(targetId) {
    const target = document.getElementById(targetId);
    if (!target) return;
    target.classList.toggle('hidden');
}

function filterPolicyEmployees(inputEl, listId) {
    const list = document.getElementById(listId);
    if (!list) return;

    const query = inputEl.value.trim().toLowerCase();
    list.querySelectorAll('.policy-employee-option').forEach(function(option) {
        const text = option.dataset.search || option.textContent.toLowerCase();
        option.classList.toggle('hidden', query && !text.includes(query));
    });
}
</script>
@endsection
