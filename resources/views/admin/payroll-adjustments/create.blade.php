@extends('admin.layouts.app')
@section('title', 'Tambah Adjustment')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.payroll-adjustments.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- Form Adjustment --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">tune</span> Adjustment Manual</h3>
        </div>
        <form action="{{ route('admin.payroll-adjustments.store') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Karyawan *</label>
                <select name="employee_id" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">— Pilih Karyawan —</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->employee_code }} — {{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tipe *</label>
                    <select name="type" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="adjustment">Adjustment</option>
                        <option value="correction">Correction</option>
                        <option value="backpay">Backpay</option>
                        <option value="arrears">Arrears</option>
                        <option value="retroactive">Retroactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Earning/Deduction *</label>
                    <select name="earning_type" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="earning">Earning (+)</option>
                        <option value="deduction">Deduction (−)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Keterangan *</label>
                <input type="text" name="name" required placeholder="Backpay selisih gaji Maret 2026" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Nominal *</label>
                <div class="flex items-center gap-1.5">
                    <span class="text-[12px] text-gray-400 font-semibold">Rp</span>
                    <input type="hidden" name="amount" value="0">
                    <input type="text" data-target="amount" value="" required placeholder="5.000.000" class="currency-input w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Periode Referensi</label>
                    <input type="month" name="reference_period" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <span class="text-[10px] text-gray-400">Opsional. Untuk backpay/retroactive.</span>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Target Periode *</label>
                    <input type="month" name="target_period" required value="{{ date('Y-m') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Catatan</label>
                <textarea name="notes" rows="2" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>

    {{-- Generate Backpay --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">currency_exchange</span> Generate Backpay Otomatis</h3>
        </div>
        <form action="{{ route('admin.payroll-adjustments.generate-backpay') }}" method="POST" class="p-5 space-y-4">
            @csrf
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-[12px] text-blue-700">Otomatis hitung selisih gaji antara payroll periode sebelumnya dan gaji saat ini. Cocok untuk kenaikan gaji retroaktif.</p>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Karyawan *</label>
                <select name="employee_id" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">— Pilih Karyawan —</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->employee_code }} — {{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Periode Referensi *</label>
                    <input type="month" name="reference_period" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <span class="text-[10px] text-gray-400">Periode yang gajinya berubah</span>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Bayarkan di Periode *</label>
                    <input type="month" name="target_period" required value="{{ date('Y-m') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Generate Backpay</button>
            </div>
        </form>
    </div>
</div>
@endsection
