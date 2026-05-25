@extends('admin.layouts.app')
@section('title', 'Audit Log')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <h2 class="text-[20px] font-bold text-gray-900">Audit Log</h2>
        <p class="text-[13px] text-gray-500">Riwayat aktivitas admin yang mengubah data.</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Route</label>
            <input type="text" name="route_name" value="{{ request('route_name') }}" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg" placeholder="admin.departments">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Method</label>
            <select name="method" class="px-3 py-2 text-[12px] border border-gray-300 rounded-lg">
                <option value="">Semua</option>
                @foreach(['POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                    <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-indigo-600 rounded-lg">Filter</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Waktu</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Admin</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Action</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Request</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase text-gray-500 border-b">Payload</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 border-b border-gray-100 text-[12px] text-gray-600 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3 border-b border-gray-100">
                            <div class="text-[13px] font-semibold text-gray-800">{{ $log->employee->full_name ?? '-' }}</div>
                            <div class="text-[11px] text-gray-400">{{ $log->employee->employee_code ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3 border-b border-gray-100">
                            <code class="text-[11px] bg-gray-100 px-1.5 py-0.5 rounded">{{ $log->route_name ?? $log->action }}</code>
                        </td>
                        <td class="px-4 py-3 border-b border-gray-100 text-[12px] text-gray-600">
                            <div><span class="font-bold">{{ $log->method }}</span> /{{ $log->path }}</div>
                            <div class="text-[11px] text-gray-400">Status {{ $log->status_code ?? '-' }} · {{ $log->ip_address ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3 border-b border-gray-100">
                            <details>
                                <summary class="text-[12px] font-semibold text-indigo-600 cursor-pointer">Lihat payload</summary>
                                <pre class="mt-2 max-w-md overflow-auto rounded-lg bg-gray-900 text-gray-100 p-3 text-[11px]">{{ json_encode($log->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-10 text-gray-400 text-sm">Belum ada audit log.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-4 py-3 border-t border-gray-100">
        {{ $logs->links() }}
    </div>
</div>
@endsection
