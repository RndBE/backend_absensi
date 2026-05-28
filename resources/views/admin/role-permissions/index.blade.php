@extends('admin.layouts.app')

@section('title', 'Role Permission')

@section('content')
@php
    $totalPermissionCount = collect($groups)->flatten()->count();
@endphp

<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Role Permission</h1>
        <p class="text-sm text-gray-500 mt-1">Atur akses default per role dan override khusus untuk admin tertentu.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-5">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($roles as $role => $roleLabel)
                @php
                    $activeCount = collect($roleStates[$role] ?? [])->filter()->count();
                    $isEditable = in_array($role, $editableRoles, true);
                    $roleIcon = match ($role) {
                        'superadmin' => 'admin_panel_settings',
                        'hr_admin' => 'badge',
                        'payroll_admin' => 'payments',
                        'finance_admin' => 'account_balance_wallet',
                        'manager' => 'supervisor_account',
                        default => 'person',
                    };
                    $roleColor = match ($role) {
                        'superadmin' => 'bg-red-50 text-red-600',
                        'hr_admin' => 'bg-indigo-50 text-indigo-600',
                        'payroll_admin' => 'bg-emerald-50 text-emerald-600',
                        'finance_admin' => 'bg-amber-50 text-amber-600',
                        'manager' => 'bg-sky-50 text-sky-600',
                        default => 'bg-gray-50 text-gray-600',
                    };
                @endphp
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="w-10 h-10 rounded-lg {{ $roleColor }} flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[22px]">{{ $roleIcon }}</span>
                            </div>
                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-[11px] font-semibold">{{ $activeCount }}/{{ $totalPermissionCount }} aktif</span>
                        </div>
                        <h2 class="mt-4 text-[16px] font-bold text-gray-900">{{ $roleLabel }}</h2>
                        <p class="mt-1 text-[12px] leading-5 text-gray-500">
                            {{ $role === 'superadmin' ? 'Selalu punya semua akses dan tidak perlu diubah.' : 'Permission default untuk role ' . $roleLabel . '.' }}
                        </p>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                        @if($isEditable)
                            <button type="button"
                                    data-role-modal-trigger
                                    data-role="{{ $role }}"
                                    onclick="openRolePermissionModal('{{ $role }}')"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 text-white rounded-lg text-[12px] font-semibold hover:bg-indigo-700 transition cursor-pointer">
                                <span class="material-symbols-outlined text-[16px]">tune</span>
                                Atur Permission
                            </button>
                        @else
                            <button type="button"
                                    data-role-modal-trigger
                                    data-role="{{ $role }}"
                                    onclick="openRolePermissionModal('{{ $role }}')"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg text-[12px] font-semibold hover:bg-gray-50 transition cursor-pointer">
                                <span class="material-symbols-outlined text-[16px]">visibility</span>
                                Lihat Permission
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- <div class="space-y-5">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-[15px] font-bold text-gray-900">Override Per Admin</h2>
                    <p class="text-[12px] text-gray-500 mt-0.5">Pilih admin, lalu atur izin atau larangan khusus lewat modal.</p>
                </div>
                <div class="p-5 space-y-4">
                    <form method="GET" action="{{ route('admin.role-permissions.index') }}">
                        <select name="employee_id" onchange="this.form.submit()" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] bg-white">
                            <option value="">Pilih admin...</option>
                            @foreach($admins as $adminUser)
                                @php $adminRoleLabel = $adminUser->roles->pluck('name')->join(', ') ?: ($roles[$adminUser->role] ?? ucfirst($adminUser->role)); @endphp
                                <option value="{{ $adminUser->id }}" {{ $selectedEmployee?->id === $adminUser->id ? 'selected' : '' }}>
                                    {{ $adminUser->full_name }} ({{ $adminRoleLabel }})
                                </option>
                            @endforeach
                        </select>
                    </form>

                    @if($selectedEmployee)
                        @php
                            $overrideCounts = collect($selectedOverrides);
                            $allowCount = $overrideCounts->filter(fn($state) => $state === 'allow')->count();
                            $denyCount = $overrideCounts->filter(fn($state) => $state === 'deny')->count();
                            $selectedRoleLabel = $selectedEmployee->roles->pluck('name')->join(', ') ?: ($roles[$selectedEmployee->role] ?? ucfirst($selectedEmployee->role));
                        @endphp
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="text-[13px] font-bold text-gray-900">{{ $selectedEmployee->full_name }}</div>
                            <div class="text-[12px] text-gray-500 mt-0.5">Role: {{ $selectedRoleLabel }}</div>
                            <div class="mt-3 flex gap-2 text-[11px] font-semibold">
                                <span class="px-2 py-1 rounded bg-emerald-50 text-emerald-700">{{ $allowCount }} allow</span>
                                <span class="px-2 py-1 rounded bg-red-50 text-red-700">{{ $denyCount }} deny</span>
                            </div>
                        </div>
                        <button type="button"
                                data-override-modal-trigger
                                onclick="openEmployeeOverrideModal()"
                                class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 bg-indigo-600 text-white rounded-lg text-[12px] font-semibold hover:bg-indigo-700 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">rule_settings</span>
                            Atur Override
                        </button>
                    @else
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-5 text-center text-[13px] text-gray-500">
                            Pilih admin terlebih dahulu untuk mengatur override.
                        </div>
                    @endif
                </div>
            </div>
        </div> --}}
    </div>
</div>

{{-- <div id="rolePermissionModal" class="role-permission-modal-shell hidden fixed inset-0 items-center justify-center p-4 sm:p-6" style="z-index: 1000;">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" data-role-modal-close></div>
    <div class="role-permission-dialog relative bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col" style="width: min(1040px, calc(100vw - 32px)); max-height: calc(100vh - 80px); z-index: 1;">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
            <div>
                <h3 class="text-[16px] font-bold text-gray-900">Atur Permission Role</h3>
                <p id="rolePermissionModalSubtitle" class="text-[12px] text-gray-500 mt-0.5">Pilih permission yang aktif untuk role.</p>
            </div>
            <button type="button" data-role-modal-close class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500 cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="overflow-y-auto" style="max-height: calc(100vh - 190px);">
            @foreach($roles as $role => $roleLabel)
                @php $isEditable = in_array($role, $editableRoles, true); @endphp
                <form method="POST"
                      action="{{ $isEditable ? route('admin.role-permissions.roles.update', $role) : '#' }}"
                      class="role-permission-panel hidden"
                      data-role-panel="{{ $role }}">
                    @csrf
                    @if($isEditable)
                        @method('PUT')
                    @endif
                    <div class="p-5 space-y-5">
                        @foreach($groups as $groupLabel => $items)
                            @php $groupKey = \Illuminate\Support\Str::slug($groupLabel); @endphp
                            <section class="rounded-lg border border-gray-200 overflow-hidden">
                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between gap-3">
                                    <div class="text-[12px] font-bold uppercase tracking-wider text-gray-500 truncate">{{ $groupLabel }}</div>
                                    @if($isEditable)
                                        <div class="flex gap-2">
                                            <button type="button" onclick="setPermissionGroup('{{ $role }}', '{{ $groupKey }}', true)" class="px-2.5 py-1 text-[11px] font-semibold rounded bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer">Pilih Semua</button>
                                            <button type="button" onclick="setPermissionGroup('{{ $role }}', '{{ $groupKey }}', false)" class="px-2.5 py-1 text-[11px] font-semibold rounded bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer">Kosongkan</button>
                                        </div>
                                    @endif
                                </div>
                                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($items as $permission => $label)
                                        <label class="flex items-start gap-2.5 p-3 rounded-lg border border-gray-200 hover:border-indigo-200 transition {{ !$isEditable ? 'bg-gray-50' : 'bg-white' }}">
                                            <input type="checkbox"
                                                   name="permissions[]"
                                                   value="{{ $permission }}"
                                                   data-role="{{ $role }}"
                                                   data-permission-group="{{ $groupKey }}"
                                                   class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                   {{ !empty($roleStates[$role][$permission]) ? 'checked' : '' }}
                                                   {{ !$isEditable ? 'disabled' : '' }}>
                                            <span>
                                                <span class="block text-[13px] font-semibold text-gray-800">{{ $label }}</span>
                                                <span class="block text-[11px] text-gray-400 font-mono mt-0.5">{{ $permission }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2 sticky bottom-0">
                        <button type="button" data-role-modal-close class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">Batal</button>
                        @if($isEditable)
                            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">Simpan Permission</button>
                        @endif
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</div>

@if($selectedEmployee)
<div id="employeeOverrideModal" class="role-permission-modal-shell hidden fixed inset-0 items-center justify-center p-4 sm:p-6" style="z-index: 1000;">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" data-override-modal-close></div>
    <form method="POST" action="{{ route('admin.role-permissions.employees.update', $selectedEmployee) }}" class="role-permission-dialog relative bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col" style="width: min(1040px, calc(100vw - 32px)); max-height: calc(100vh - 80px); z-index: 1;">
        @csrf
        @method('PUT')
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
            <div>
                <h3 class="text-[16px] font-bold text-gray-900">Atur Override Admin</h3>
                <p class="text-[12px] text-gray-500 mt-0.5">{{ $selectedEmployee->full_name }} - Role {{ $selectedRoleLabel }}</p>
            </div>
            <button type="button" data-override-modal-close class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500 cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="p-5 space-y-5 overflow-y-auto" style="max-height: calc(100vh - 205px);">
            @foreach($groups as $groupLabel => $items)
                <section class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                        <div class="text-[12px] font-bold uppercase tracking-wider text-gray-500">{{ $groupLabel }}</div>
                    </div>
                    <div class="p-4 space-y-2">
                        @foreach($items as $permission => $label)
                            @php
                                $state = $selectedOverrides[$permission] ?? 'inherit';
                                $employeeRoles = $selectedEmployee->roles->pluck('slug')->all() ?: [$selectedEmployee->role];
                                $baseAllowed = collect($employeeRoles)->contains(fn ($roleSlug) => !empty($roleStates[$roleSlug][$permission]));
                            @endphp
                            <div class="p-3 rounded-lg border border-gray-200">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                    <div>
                                        <div class="text-[13px] font-semibold text-gray-800">{{ $label }}</div>
                                        <div class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $permission }}</div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 text-[11px] min-w-[300px]">
                                        <label class="flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg border border-gray-200 cursor-pointer {{ $state === 'inherit' ? 'bg-gray-100 text-gray-800' : 'text-gray-600' }}">
                                            <input type="radio" name="overrides[{{ $permission }}]" value="inherit" {{ $state === 'inherit' ? 'checked' : '' }}>
                                            Role {{ $baseAllowed ? 'allow' : 'deny' }}
                                        </label>
                                        <label class="flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg border border-emerald-200 cursor-pointer {{ $state === 'allow' ? 'bg-emerald-50 text-emerald-700' : 'text-emerald-700' }}">
                                            <input type="radio" name="overrides[{{ $permission }}]" value="allow" {{ $state === 'allow' ? 'checked' : '' }}>
                                            Allow
                                        </label>
                                        <label class="flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg border border-red-200 cursor-pointer {{ $state === 'deny' ? 'bg-red-50 text-red-700' : 'text-red-700' }}">
                                            <input type="radio" name="overrides[{{ $permission }}]" value="deny" {{ $state === 'deny' ? 'checked' : '' }}>
                                            Deny
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
            <button type="button" data-override-modal-close class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">Batal</button>
            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition cursor-pointer">Simpan Override</button>
        </div>
    </form>
</div>
@endif --}}
@endsection

@push('scripts')
<script>
function lockRolePermissionModalScroll() {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.dataset.previousPaddingRight = document.body.style.paddingRight || '';
    document.body.style.overflow = 'hidden';
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = scrollbarWidth + 'px';
    }
}

function unlockRolePermissionModalScroll() {
    document.body.style.overflow = '';
    document.body.style.paddingRight = document.body.dataset.previousPaddingRight || '';
    delete document.body.dataset.previousPaddingRight;
}

function openRolePermissionModal(role) {
    const modal = document.getElementById('rolePermissionModal');
    const subtitle = document.getElementById('rolePermissionModalSubtitle');
    document.querySelectorAll('[data-role-panel]').forEach(panel => {
        panel.classList.toggle('hidden', panel.dataset.rolePanel !== role);
    });
    if (subtitle) subtitle.textContent = 'Role: ' + role.charAt(0).toUpperCase() + role.slice(1);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    lockRolePermissionModalScroll();
}

function closeRolePermissionModal() {
    const modal = document.getElementById('rolePermissionModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    unlockRolePermissionModalScroll();
}

function setPermissionGroup(role, group, checked) {
    document.querySelectorAll('input[data-role="' + role + '"][data-permission-group="' + group + '"]').forEach(input => {
        if (!input.disabled) input.checked = checked;
    });
}

function openEmployeeOverrideModal() {
    const modal = document.getElementById('employeeOverrideModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    lockRolePermissionModalScroll();
}

function closeEmployeeOverrideModal() {
    const modal = document.getElementById('employeeOverrideModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    unlockRolePermissionModalScroll();
}

document.addEventListener('click', function(event) {
    if (event.target.closest('[data-role-modal-close]')) {
        closeRolePermissionModal();
    }
    if (event.target.closest('[data-override-modal-close]')) {
        closeEmployeeOverrideModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key !== 'Escape') return;
    closeRolePermissionModal();
    closeEmployeeOverrideModal();
});
</script>
@endpush
