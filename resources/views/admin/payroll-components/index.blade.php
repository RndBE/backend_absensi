@extends('admin.layouts.app')
@section('title', 'Komponen Payroll')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">list_alt</span> Komponen Payroll</h3>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">add</span> Tambah Komponen
        </button>
    </div>
    <div class="p-5">
        {{-- Filter Tabs --}}
        <div class="flex gap-0 border-b-2 border-gray-200 mb-5">
            <a href="{{ route('admin.payroll-components.index', ['type' => 'all']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                      {{ $type === 'all' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">Semua</a>
            <a href="{{ route('admin.payroll-components.index', ['type' => 'earning']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                      {{ $type === 'earning' ? 'text-emerald-600 border-emerald-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">Earning</a>
            <a href="{{ route('admin.payroll-components.index', ['type' => 'deduction']) }}"
               class="px-5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-[2px] transition-all duration-200
                      {{ $type === 'deduction' ? 'text-red-600 border-red-600' : 'text-gray-500 border-transparent hover:text-gray-700' }}">Deduction</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Nama</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Tipe</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Kategori</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Default Nominal</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Taxable</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($components as $c)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100 text-[13px] font-semibold text-gray-800">{{ $c->name }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            @if($c->type === 'earning')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700">Earning</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700">Deduction</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700">{{ ucfirst($c->category) }}</span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-gray-800">Rp {{ number_format($c->default_amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            @if($c->is_taxable)
                                <span class="material-symbols-outlined text-[16px] text-amber-500">check_circle</span>
                            @else
                                <span class="material-symbols-outlined text-[16px] text-gray-300">cancel</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <form action="{{ route('admin.payroll-components.toggle', $c->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="cursor-pointer">
                                    @if($c->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700">Aktif</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">Nonaktif</span>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.payroll-components.employees', $c->id) }}"
                                   title="Kelola Karyawan"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                    <span class="material-symbols-outlined text-[13px]">group</span>
                                    <span>{{ $c->employee_components_count }}</span>
                                </a>
                                <button onclick="openEdit({{ $c->id }}, '{{ addslashes($c->name) }}', '{{ $c->type }}', '{{ $c->category }}', {{ $c->default_amount }}, {{ $c->is_taxable ? 1 : 0 }})" class="p-1.5 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">edit</span></button>
                                <form action="{{ route('admin.payroll-components.destroy', $c->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus komponen ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Belum ada komponen payroll</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900">Tambah Komponen Payroll</h3>
        </div>
        <form action="{{ route('admin.payroll-components.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nama Komponen *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tipe *</label>
                    <select name="type" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <option value="earning">Earning (Pendapatan)</option>
                        <option value="deduction">Deduction (Potongan)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Kategori *</label>
                    <select name="category" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <option value="fixed">Fixed</option>
                        <option value="recurring">Recurring</option>
                        <option value="one-time">One-time</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Default Nominal *</label>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[12px] text-gray-400 font-semibold">Rp</span>
                        <input type="hidden" name="default_amount" value="0">
                        <input type="text" data-target="default_amount" value="0" required class="currency-input w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    </div>
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_taxable" value="1" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                        <span class="text-[13px] font-medium text-gray-700">Kena Pajak (Taxable)</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900">Edit Komponen</h3>
        </div>
        <form id="editForm" method="POST" class="p-6 space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nama Komponen *</label>
                    <input type="text" name="name" id="editName" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tipe *</label>
                    <select name="type" id="editType" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <option value="earning">Earning (Pendapatan)</option>
                        <option value="deduction">Deduction (Potongan)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Kategori *</label>
                    <select name="category" id="editCategory" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <option value="fixed">Fixed</option>
                        <option value="recurring">Recurring</option>
                        <option value="one-time">One-time</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Default Nominal *</label>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[12px] text-gray-400 font-semibold">Rp</span>
                        <input type="hidden" name="default_amount" id="editAmountHidden" value="0">
                        <input type="text" data-target="default_amount" id="editAmount" value="0" required class="currency-input w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    </div>
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_taxable" id="editTaxable" value="1" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                        <span class="text-[13px] font-medium text-gray-700">Kena Pajak (Taxable)</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, type, category, amount, taxable) {
    document.getElementById('editForm').action = '{{ url("admin/payroll-components") }}/' + id;
    document.getElementById('editName').value = name;
    document.getElementById('editType').value = type;
    document.getElementById('editCategory').value = category;
    document.getElementById('editAmountHidden').value = amount;
    document.getElementById('editAmount').value = String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    document.getElementById('editTaxable').checked = taxable === 1;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
@endsection
