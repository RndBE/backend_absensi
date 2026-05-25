@extends('admin.layouts.app')
@section('title', 'Role Permission')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-[20px] font-bold text-gray-900">Role Permission</h2>
        <p class="text-[13px] text-gray-500">Atur akses menu dan aksi untuk role admin.</p>
    </div>
</div>

<form action="{{ route('admin.role-permissions.update') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    @csrf
    @method('PUT')
    <input type="hidden" name="role" value="{{ $role }}">

    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <div>
            <div class="text-[13px] font-bold text-gray-800">Role: {{ ucfirst($role) }}</div>
            <div class="text-[12px] text-gray-500">Superadmin otomatis memiliki semua akses dan tidak perlu diatur.</div>
        </div>
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200">
            <span class="material-symbols-outlined text-[16px]">save</span> Simpan Permission
        </button>
    </div>

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-0">
        @foreach($permissionGroups as $group => $permissions)
            <div class="p-5 border-b border-r border-gray-100">
                <h3 class="text-[12px] font-bold uppercase tracking-wider text-gray-500 mb-3">{{ $group }}</h3>
                <div class="space-y-2">
                    @foreach($permissions as $permission)
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-200 hover:bg-indigo-50/40 transition-colors cursor-pointer">
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="{{ $permission->key }}"
                                   class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   {{ in_array($permission->key, $selectedPermissions, true) ? 'checked' : '' }}>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-gray-800">{{ $permission->name }}</span>
                                <span class="block text-[11px] text-gray-400 font-mono">{{ $permission->key }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</form>
@endsection
