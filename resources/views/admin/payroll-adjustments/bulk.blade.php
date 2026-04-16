@extends('admin.layouts.app')
@section('title', 'Import Adjustment CSV')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.payroll-adjustments.index') }}" class="inline-flex items-center gap-1 text-[13px] text-gray-500 hover:text-indigo-600 transition-colors">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">upload_file</span> Import CSV</h3>
        </div>
        <form action="{{ route('admin.payroll-adjustments.bulk-store') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Tipe Adjustment *</label>
                    <select name="type" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="adjustment">Adjustment</option>
                        <option value="correction">Correction</option>
                        <option value="backpay">Backpay</option>
                        <option value="arrears">Arrears</option>
                        <option value="retroactive">Retroactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Target Periode *</label>
                    <input type="month" name="target_period" required value="{{ date('Y-m') }}" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">File CSV *</label>
                <input type="file" name="csv_file" required accept=".csv,.txt" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Import</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">info</span> Format CSV</h3>
        </div>
        <div class="p-5">
            <p class="text-[13px] text-gray-600 mb-3">File CSV harus memiliki header berikut:</p>
            <div class="bg-gray-50 rounded-lg p-4 font-mono text-[12px] text-gray-700 mb-4">
                <div class="text-indigo-600 font-bold mb-1">employee_code,name,earning_type,amount,notes</div>
                <div>EMP001,Backpay Maret,earning,500000,Selisih kenaikan</div>
                <div>EMP002,Denda Kerapian,deduction,100000,Pelanggaran minggu 1</div>
                <div>EMP003,Koreksi Lembur,earning,250000,Kekurangan Feb</div>
            </div>
            <table class="w-full text-[12px]">
                <thead>
                    <tr>
                        <th class="text-left py-1 text-gray-500 font-bold">Kolom</th>
                        <th class="text-left py-1 text-gray-500 font-bold">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td class="py-1 font-mono text-indigo-600">employee_code</td><td class="py-1 text-gray-600">Kode karyawan (wajib)</td></tr>
                    <tr><td class="py-1 font-mono text-indigo-600">name</td><td class="py-1 text-gray-600">Keterangan adjustment</td></tr>
                    <tr><td class="py-1 font-mono text-indigo-600">earning_type</td><td class="py-1 text-gray-600">earning / deduction</td></tr>
                    <tr><td class="py-1 font-mono text-indigo-600">amount</td><td class="py-1 text-gray-600">Nominal (angka)</td></tr>
                    <tr><td class="py-1 font-mono text-indigo-600">notes</td><td class="py-1 text-gray-600">Catatan (opsional)</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
