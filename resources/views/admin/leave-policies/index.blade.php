@extends('admin.layouts.app')
@section('title', 'Kebijakan Cuti')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Add/Edit Form --}}
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
                <p class="text-[10px] text-gray-400 mt-1">Sisa cuti yg bisa dibawa ke tahun depan. 0 = hangus.</p>
            </div>
            <div>
                <label class="flex items-center gap-2.5 cursor-pointer p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-all">
                    <input type="checkbox" name="is_prorated" value="1" class="accent-indigo-500 w-4 h-4">
                    <div>
                        <span class="text-[12px] font-semibold text-gray-700">Prorata</span>
                        <p class="text-[10px] text-gray-400">Hitung proporsional untuk karyawan baru</p>
                    </div>
                </label>
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg hover:from-indigo-700 hover:to-indigo-600 transition-all cursor-pointer shadow-sm">
                <span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan Kebijakan
            </button>
        </form>
    </div>

    {{-- Policy List --}}
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
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($policies as $policy)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/30 transition-all" id="policyRow{{ $policy->id }}">
                        <td class="py-3 px-4">
                            <span class="text-[13px] font-semibold text-gray-800">{{ $policy->leaveType->name }}</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <form action="{{ route('admin.leave-policies.update', $policy) }}" method="POST" class="inline-edit-form flex items-center justify-center gap-1">
                                @csrf @method('PUT')
                                <input type="number" name="days_per_year" value="{{ $policy->days_per_year }}" class="w-14 px-2 py-1 text-[13px] text-center font-bold text-gray-800 border border-gray-200 rounded-md outline-none focus:border-indigo-500">
                                <input type="hidden" name="min_tenure_months" value="{{ $policy->min_tenure_months }}">
                                <input type="hidden" name="max_carry_over" value="{{ $policy->max_carry_over }}">
                                <input type="hidden" name="is_prorated" value="{{ $policy->is_prorated ? '1' : '0' }}">
                                <input type="hidden" name="is_active" value="{{ $policy->is_active ? '1' : '0' }}">
                                <button type="submit" class="text-[10px] text-indigo-500 hover:text-indigo-700 cursor-pointer bg-transparent border-0">✓</button>
                            </form>
                        </td>
                        <td class="py-3 px-4 text-center text-[13px] text-gray-600">{{ $policy->min_tenure_months }} bln</td>
                        <td class="py-3 px-4 text-center text-[13px] text-gray-600">{{ $policy->max_carry_over > 0 ? $policy->max_carry_over . ' hari' : 'Hangus' }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $policy->is_prorated ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-gray-100 text-gray-400' }}">
                                {{ $policy->is_prorated ? 'Ya' : 'Tidak' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $policy->is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-500 border border-red-200' }}">
                                {{ $policy->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <form action="{{ route('admin.leave-policies.destroy', $policy) }}" method="POST" class="inline" onsubmit="return confirm('Hapus kebijakan ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-2 py-1 text-[10px] font-semibold text-red-500 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 cursor-pointer transition-all"><span class="material-symbols-outlined text-[14px] align-text-bottom">delete</span></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-gray-400 text-sm">Belum ada kebijakan cuti. Tambahkan di form sebelah.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
