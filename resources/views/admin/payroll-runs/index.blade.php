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
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">{{ $run->details_count }}</span>
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-emerald-700">Rp {{ number_format($run->total_earning, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13px] font-semibold text-red-600">Rp {{ number_format($run->total_deduction, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-right text-[13.5px] font-bold text-gray-900">Rp {{ number_format($run->total_net, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            @if($run->status === 'locked')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-700 text-white">Locked</span>
                            @elseif($run->status === 'published')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700">Published</span>
                            @elseif($run->status === 'finalized')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700">Finalized</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 border-b border-gray-100 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.payroll-runs.show', $run->id) }}" class="p-1.5 rounded-lg hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-colors" title="Detail"><span class="material-symbols-outlined text-[16px]">visibility</span></a>
                                @if($run->status === 'draft')
                                <form action="{{ route('admin.payroll-runs.destroy', $run->id) }}" method="POST" class="inline" data-confirm="Hapus payroll run ini?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors cursor-pointer"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">Belum ada payroll run</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    </div>
</div>

{{-- Create Modal with Employee Picker --}}
<div id="createModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
            <h3 class="text-[15px] font-bold text-gray-900">Buat Payroll Run Baru</h3>
            <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <form action="{{ route('admin.payroll-runs.store') }}" method="POST" class="flex flex-col flex-1 overflow-hidden" id="createPayrollForm">
            @csrf
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1">Periode *</label>
                    <input type="month" name="period" value="{{ date('Y-m') }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                {{-- Employee Selector --}}
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Pilih Karyawan *</label>

                    {{-- Quick Actions --}}
                    <div class="flex items-center gap-2 mb-2">
                        <button type="button" onclick="toggleAllEmployees(true)" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[13px]">select_all</span> Pilih Semua
                        </button>
                        <button type="button" onclick="toggleAllEmployees(false)" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[13px]">deselect</span> Hapus Semua
                        </button>
                        <span class="text-[11px] text-gray-400 ml-auto" id="selectedCount">0 dipilih</span>
                    </div>

                    {{-- Search --}}
                    <div class="relative mb-2">
                        <span class="material-symbols-outlined text-[16px] text-gray-400 absolute left-3 top-1/2 -translate-y-1/2">search</span>
                        <input type="text" id="empSearchInput" placeholder="Cari nama atau kode karyawan..."
                               class="w-full pl-9 pr-3 py-2 text-[12px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" autocomplete="off">
                    </div>

                    {{-- Employee List --}}
                    <div class="border border-gray-200 rounded-lg overflow-hidden max-h-[280px] overflow-y-auto" id="employeeList">
                        @foreach($employees as $emp)
                        <label class="emp-item flex items-center gap-3 px-3 py-2.5 hover:bg-indigo-50/50 transition-colors cursor-pointer border-b border-gray-100 last:border-0"
                               data-name="{{ strtolower($emp->full_name) }}" data-code="{{ strtolower($emp->employee_code) }}">
                            <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}"
                                   class="emp-checkbox w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer"
                                   onchange="updateSelectedCount()">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white text-[10px] font-bold shrink-0">{{ substr($emp->full_name, 0, 1) }}</div>
                                <div class="min-w-0">
                                    <div class="text-[12.5px] font-semibold text-gray-800 truncate">{{ $emp->full_name }}</div>
                                    <div class="text-[10.5px] text-gray-400 truncate">{{ $emp->employee_code }} · {{ $emp->department->name ?? '-' }}</div>
                                </div>
                            </div>
                        </label>
                        @endforeach

                        @if($employees->isEmpty())
                        <div class="text-center py-8 text-gray-400 text-[12px]">Tidak ada karyawan dengan data payroll aktif</div>
                        @endif
                    </div>
                </div>

                <p class="text-[12px] text-gray-500">Sistem akan otomatis mengambil data gaji pokok dan komponen aktif setiap karyawan yang dipilih.</p>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 shrink-0">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-[12.5px] font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-[12.5px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">Proses</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function toggleAllEmployees(select) {
    document.querySelectorAll('.emp-checkbox').forEach(cb => {
        const item = cb.closest('.emp-item');
        if (!item.classList.contains('hidden')) {
            cb.checked = select;
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.emp-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' dipilih';
}

document.getElementById('empSearchInput')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.emp-item').forEach(item => {
        const name = item.dataset.name || '';
        const code = item.dataset.code || '';
        const match = name.includes(q) || code.includes(q);
        item.classList.toggle('hidden', !match);
    });
});

document.getElementById('createPayrollForm')?.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.emp-checkbox:checked').length;
    if (checked === 0) {
        e.preventDefault();
        alert('Pilih minimal 1 karyawan untuk menjalankan payroll.');
    }
});
</script>
@endpush
@endsection
