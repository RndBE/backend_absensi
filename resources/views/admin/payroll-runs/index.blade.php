@extends('admin.layouts.app')
@section('title', 'Run Payroll')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">payments</span> Run Payroll</h3>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">add</span> Buat Payroll Run
        </button>
    </div>
    <div class="p-5">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Periode</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Group</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Karyawan</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Earning</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Total Deduction</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Net Salary</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 border-b border-gray-100">
                            <div class="text-[13px] font-bold text-gray-800">{{ \Carbon\Carbon::parse($run->period . '-01')->translatedFormat('F Y') }}</div>
                            <div class="text-[11px] text-gray-400">Dibuat oleh: {{ $run->creator->full_name ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-[13px] text-gray-600">
                            @if($run->payrollGroup)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700">{{ $run->payrollGroup->name }}</span>
                            @else
                                <span class="text-gray-400">Semua</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">{{ $run->details_count }}</span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-emerald-700">Rp {{ number_format($run->total_earning, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-red-600">Rp {{ number_format($run->total_deduction, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13.5px] font-bold text-gray-900">Rp {{ number_format($run->total_net, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            @if($run->status === 'finalized')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700">Finalized</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.payroll-runs.show', $run->id) }}" class="p-1.5 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors" title="Detail"><span class="material-symbols-outlined text-[16px]">visibility</span></a>
                                @if($run->status === 'draft')
                                <form action="{{ route('admin.payroll-runs.destroy', $run->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus payroll run ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-12 text-gray-400 text-sm">Belum ada payroll run</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    </div>
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-[15px] font-bold text-gray-900">Buat Payroll Run Baru</h3>
        </div>
        <form action="{{ route('admin.payroll-runs.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Periode *</label>
                <input type="month" name="period" value="{{ date('Y-m') }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-600 mb-1">Payroll Group</label>
                <select name="payroll_group_id" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Semua Group</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-[12px] text-gray-500">Sistem akan otomatis mengambil data gaji pokok dan komponen aktif setiap karyawan.</p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Proses</button>
            </div>
        </form>
    </div>
</div>
@endsection
