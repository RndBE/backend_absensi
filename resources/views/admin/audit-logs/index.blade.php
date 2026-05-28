@extends('admin.layouts.app')

@section('title', 'Audit Log')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Audit Log Aktivitas Admin</h1>
        <p class="text-sm text-gray-500 mt-1">Riwayat aksi admin yang mengubah data sistem.</p>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 grid grid-cols-1 md:grid-cols-6 gap-3">
        <select name="employee_id" class="px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] bg-white">
            <option value="">Semua admin</option>
            @foreach($admins as $adminUser)
                <option value="{{ $adminUser->id }}" {{ request('employee_id') == $adminUser->id ? 'selected' : '' }}>{{ $adminUser->full_name }}</option>
            @endforeach
        </select>
        <select name="module" class="px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] bg-white">
            <option value="">Semua modul</option>
            @foreach($modules as $module)
                <option value="{{ $module }}" {{ request('module') === $module ? 'selected' : '' }}>{{ $module }}</option>
            @endforeach
        </select>
        <select name="action" class="px-3 py-2.5 border border-gray-300 rounded-lg text-[13px] bg-white">
            <option value="">Semua aksi</option>
            @foreach($actions as $action)
                <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="px-3 py-2.5 border border-gray-300 rounded-lg text-[13px]">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="px-3 py-2.5 border border-gray-300 rounded-lg text-[13px]">
        <button class="px-3.5 py-2.5 bg-indigo-600 text-white rounded-lg text-[12px] font-semibold hover:bg-indigo-700 transition cursor-pointer">Filter</button>
    </form>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-gray-500">Waktu</th>
                        <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-gray-500">Admin</th>
                        <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-gray-500">Modul</th>
                        <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-gray-500">Aksi</th>
                        <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-gray-500">Route</th>
                        <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-gray-500">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-[12px] text-gray-600 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-4 py-3">
                                <div class="text-[13px] font-semibold text-gray-800">{{ $log->employee->full_name ?? 'System' }}</div>
                                <div class="text-[11px] text-gray-400">{{ $log->employee->employee_code ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-[13px] text-gray-700">{{ $log->module }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 rounded bg-indigo-50 text-indigo-700 text-[11px] font-semibold">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-[12px] font-mono text-gray-700">{{ $log->route_name ?? '-' }}</div>
                                <div class="text-[11px] text-gray-400">{{ $log->method }} /{{ $log->path }}</div>
                            </td>
                            <td class="px-4 py-3 text-[12px] text-gray-500">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada aktivitas tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
