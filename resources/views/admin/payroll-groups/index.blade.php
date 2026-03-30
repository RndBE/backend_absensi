@extends('admin.layouts.app')
@section('title', 'Payroll Group')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">workspaces</span> Payroll Group</h3>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">add</span> Tambah Group
        </button>
    </div>
    <div class="p-5">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Nama</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Deskripsi</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $g)
                    <tr class="hover:bg-gray-50 transition-colors group" id="row-{{ $g->id }}">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <span class="view-mode text-[13px] font-semibold text-gray-800">{{ $g->name }}</span>
                            <input type="text" class="edit-mode hidden w-full px-2 py-1 text-[13px] border border-gray-300 rounded-lg" value="{{ $g->name }}" name="name">
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <span class="view-mode text-[13px] text-gray-600">{{ $g->description ?? '-' }}</span>
                            <input type="text" class="edit-mode hidden w-full px-2 py-1 text-[13px] border border-gray-300 rounded-lg" value="{{ $g->description }}" name="description">
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700">{{ $g->employee_payrolls_count }}</span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <form action="{{ route('admin.payroll-groups.toggle', $g->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="cursor-pointer">
                                    @if($g->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700">Aktif</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">Nonaktif</span>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick="toggleEdit({{ $g->id }})" class="view-mode p-1.5 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">edit</span></button>
                                <button onclick="saveEdit({{ $g->id }})" class="edit-mode hidden p-1.5 rounded-lg hover:bg-emerald-50 text-emerald-600 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">check</span></button>
                                <button onclick="toggleEdit({{ $g->id }})" class="edit-mode hidden p-1.5 rounded-lg hover:bg-red-50 text-red-400 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">close</span></button>
                                <form action="{{ route('admin.payroll-groups.destroy', $g->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus group ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="view-mode p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-12 text-gray-400 text-sm">Belum ada payroll group</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900">Tambah Payroll Group</h3>
        </div>
        <form action="{{ route('admin.payroll-groups.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nama Group *</label>
                <input type="text" name="name" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Deskripsi</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleEdit(id) {
    const row = document.getElementById('row-' + id);
    row.querySelectorAll('.view-mode').forEach(el => el.classList.toggle('hidden'));
    row.querySelectorAll('.edit-mode').forEach(el => el.classList.toggle('hidden'));
}

function saveEdit(id) {
    const row = document.getElementById('row-' + id);
    const name = row.querySelector('input[name="name"]').value;
    const description = row.querySelector('input[name="description"]').value;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ url("admin/payroll-groups") }}/' + id;
    form.innerHTML = `@csrf @method('PUT')<input type="hidden" name="name" value="${name}"><input type="hidden" name="description" value="${description}">`;
    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection
