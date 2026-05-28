@extends('admin.layouts.app')

@section('title', 'Role')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Role</h1>
        <p class="text-sm text-gray-500 mt-1">Atur role karyawan yang tersimpan di tabel relasi employee_roles.</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-[15px] font-bold text-gray-900">Daftar Role Karyawan</h2>
            <p class="text-[12px] text-gray-500 mt-0.5">Klik edit untuk mengubah role karyawan lewat modal.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                        <th class="px-5 py-3">Karyawan</th>
                        <th class="px-5 py-3">Departemen</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3 w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($employees as $employee)
                        @php
                            $employeeRoleNames = $employee->roles->pluck('name')->all();
                            $roleLabel = $employeeRoleNames ? implode(', ', $employeeRoleNames) : ($roles[$employee->role] ?? ucfirst($employee->role));
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="px-5 py-3">
                                <div class="font-semibold text-gray-900">{{ $employee->full_name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $employee->email }}</div>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $employee->department?->name ?? '-' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach(explode(', ', $roleLabel) as $label)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 text-[10px] font-bold">{{ $label }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <button type="button"
                                        data-role-edit-trigger
                                        onclick="openEmployeeRoleModal('{{ $employee->id }}')"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition cursor-pointer">
                                    <span class="material-symbols-outlined text-[14px]">edit</span>
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="employeeRoleModal" class="hidden fixed inset-0 items-center justify-center px-4 py-6" style="z-index: 1000;">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" data-role-modal-close></div>
    <div class="relative w-full max-w-lg bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden" style="z-index: 1;">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-[15px] font-bold text-gray-900">Role Karyawan</h2>
                <p id="employeeRoleModalSubtitle" class="text-[12px] text-gray-500 mt-0.5">Centang role yang sesuai.</p>
            </div>
            <button type="button" data-role-modal-close class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500 cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        @foreach($employees as $employee)
            @php
                $selectedRoleSlugs = $employee->roles->pluck('slug')->all() ?: [$employee->role];
            @endphp
            <form method="POST"
                  action="{{ route('admin.roles.employees.update', $employee) }}"
                  class="hidden p-5 space-y-4"
                  data-employee-role-panel="{{ $employee->id }}"
                  data-employee-name="{{ $employee->full_name }}">
                @csrf
                @method('PUT')
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
                    <div class="text-[13px] font-bold text-gray-900">{{ $employee->full_name }}</div>
                    <div class="text-[12px] text-gray-500 mt-0.5">{{ $employee->department?->name ?? '-' }}</div>
                </div>
                <div class="grid grid-cols-1 gap-2">
                    @foreach($roles as $roleSlug => $roleLabel)
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-indigo-200 text-[13px] cursor-pointer">
                            <input type="checkbox" name="roles[]" value="{{ $roleSlug }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ in_array($roleSlug, $selectedRoleSlugs, true) ? 'checked' : '' }}>
                            <span class="font-medium text-gray-800">{{ $roleLabel }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-role-modal-close class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">Batal</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Simpan Role Karyawan
                    </button>
                </div>
            </form>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
function lockPageScrollForModal() {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.dataset.previousPaddingRight = document.body.style.paddingRight || '';
    document.body.style.overflow = 'hidden';
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = scrollbarWidth + 'px';
    }
}

function unlockPageScrollForModal() {
    document.body.style.overflow = '';
    document.body.style.paddingRight = document.body.dataset.previousPaddingRight || '';
    delete document.body.dataset.previousPaddingRight;
}

function openEmployeeRoleModal(employeeId) {
    const modal = document.getElementById('employeeRoleModal');
    const subtitle = document.getElementById('employeeRoleModalSubtitle');
    document.querySelectorAll('[data-employee-role-panel]').forEach(panel => {
        const isActive = panel.dataset.employeeRolePanel === String(employeeId);
        panel.classList.toggle('hidden', !isActive);
        if (isActive && subtitle) {
            subtitle.textContent = panel.dataset.employeeName;
        }
    });
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    lockPageScrollForModal();
}

function closeEmployeeRoleModal() {
    const modal = document.getElementById('employeeRoleModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    unlockPageScrollForModal();
}

document.addEventListener('click', function(event) {
    if (event.target.closest('[data-role-modal-close]')) {
        closeEmployeeRoleModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEmployeeRoleModal();
    }
});
</script>
@endpush
