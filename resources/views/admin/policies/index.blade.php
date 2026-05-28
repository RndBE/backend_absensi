@extends('admin.layouts.app')
@section('title', 'Kebijakan Reimbursement')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">

    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[20px] align-text-bottom">policy</span> Kebijakan Reimbursement
        </h3>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[14px]">add</span> Tambah Kebijakan
        </button>
    </div>

    <div class="p-5">
    @if(session('success'))
    <div class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-[13px] font-medium">{{ session('success') }}</div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Key</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Nama</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Nilai</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Keterangan</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($policies as $policy)
                <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                    <td class="px-4 py-3 text-[12px] font-mono font-semibold text-indigo-600">{{ $policy->key }}</td>
                    <td class="px-4 py-3 text-[13px] font-medium text-gray-800">{{ $policy->name }}</td>
                    <td class="px-4 py-3 text-right text-[13px] font-bold text-gray-900">Rp {{ number_format($policy->value, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-[12px] text-gray-500">{{ $policy->description ?? '-' }}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <button onclick="editPolicy({{ $policy->id }}, '{{ $policy->key }}', '{{ addslashes($policy->name) }}', {{ $policy->value }}, '{{ addslashes($policy->description ?? '') }}')"
                                class="inline-flex items-center px-2 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">Edit</button>
                        <form method="POST" action="{{ route('admin.policies.destroy', $policy->id) }}" class="inline" data-confirm="Hapus kebijakan ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-2 py-1 text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all cursor-pointer">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Belum ada kebijakan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900">Tambah Kebijakan</h3>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.policies.store') }}" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Key *</label>
                <input type="text" name="key" required placeholder="HOTEL_WEEKDAY_MAX" pattern="[A-Z0-9_]+" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                <p class="text-[10px] text-gray-400 mt-1">Huruf besar, angka, underscore saja</p>
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nama *</label>
                <input type="text" name="name" required placeholder="Maks Hotel Weekday" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nilai (Rp) *</label>
                <input type="number" name="value" required min="0" step="1000" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Keterangan</label>
                <input type="text" name="description" placeholder="Opsional" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer">Simpan</button>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900">Edit Kebijakan</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
        </div>
        <form id="editForm" method="POST" class="p-5 space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Key *</label>
                <input type="text" name="key" id="edit_key" required pattern="[A-Z0-9_]+" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nama *</label>
                <input type="text" name="name" id="edit_name" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nilai (Rp) *</label>
                <input type="number" name="value" id="edit_value" required min="0" step="1000" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Keterangan</label>
                <input type="text" name="description" id="edit_description" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all cursor-pointer">Perbarui</button>
        </form>
    </div>
</div>

<script>
function editPolicy(id, key, name, value, desc) {
    document.getElementById('editForm').action = '/admin/policies/' + id;
    document.getElementById('edit_key').value = key;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_value').value = value;
    document.getElementById('edit_description').value = desc;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
@endsection
