@extends('admin.layouts.app')
@section('title', 'Saldo Cuti')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">
    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[18px] align-text-bottom">account_balance_wallet</span> Saldo Cuti Karyawan</h3>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.leave-policies.index') }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all"><span class="material-symbols-outlined text-[14px] align-text-bottom">list_alt</span> Kebijakan Cuti</a>
            <form action="{{ route('admin.leave-balances.generate') }}" method="POST" onsubmit="return confirm('Generate saldo cuti untuk semua karyawan yang memenuhi syarat?')" class="inline">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] font-semibold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">sync</span> Generate Saldo {{ $year }}</button>
            </form>
        </div>
    </div>

    {{-- Filters --}}
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.leave-balances.index', ['year' => $year - 1]) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">←</a>
            <span class="px-3 py-1.5 text-[14px] font-black text-gray-800 bg-indigo-50 rounded-lg">{{ $year }}</span>
            <a href="{{ route('admin.leave-balances.index', ['year' => $year + 1]) }}"
               class="px-2.5 py-1.5 text-[12px] font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all">→</a>
        </div>
        <form method="GET" action="{{ route('admin.leave-balances.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="year" value="{{ $year }}">
            <select name="department_id" onchange="this.form.submit()" class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none appearance-none bg-white bg-[url('data:image/svg+xml,%3csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20fill=%27none%27%20viewBox=%270%200%2020%2020%27%3e%3cpath%20stroke=%27%236b7280%27%20stroke-linecap=%27round%27%20stroke-linejoin=%27round%27%20stroke-width=%271.5%27%20d=%27M6%208l4%204%204-4%27/%3e%3c/svg%3e')] bg-[position:right_6px_center] bg-no-repeat bg-[length:14px] pr-7 focus:border-indigo-500">
                <option value="">Semua Departemen</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama..." class="px-3 py-1.5 text-[12px] border border-gray-300 rounded-lg outline-none w-[140px] focus:border-indigo-500">
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-3 px-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                    <th class="py-3 px-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Departemen</th>
                    @foreach($leaveTypes as $lt)
                    <th class="py-3 px-2 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider" colspan="1">
                        {{ $lt->name }}
                        <div class="text-[8px] text-gray-400 font-normal normal-case mt-0.5">Jatah / Pakai / Sisa</div>
                    </th>
                    @endforeach
                    <th class="py-3 px-4 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $empId => $empBalances)
                @php $emp = $empBalances->first()->employee; @endphp
                <tr class="border-b border-gray-50 hover:bg-gray-50/30 transition-all">
                    <td class="py-2.5 px-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-500 flex items-center justify-center text-white text-[11px] font-bold shrink-0">{{ substr($emp->full_name, 0, 1) }}</div>
                            <div>
                                <div class="text-[12px] font-semibold text-gray-800">{{ $emp->full_name }}</div>
                                <div class="text-[10px] text-gray-400">{{ $emp->employee_code }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="py-2.5 px-4 text-[12px] text-gray-500">{{ $emp->department->name ?? '-' }}</td>
                    @foreach($leaveTypes as $lt)
                    @php $bal = $empBalances->where('leave_type_id', $lt->id)->first(); @endphp
                    <td class="py-2.5 px-2 text-center">
                        @if($bal)
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-[11px] font-semibold text-gray-600">{{ $bal->total_days + $bal->carry_over }}</span>
                                <span class="text-[9px] text-gray-300">/</span>
                                <span class="text-[11px] font-semibold text-amber-600">{{ $bal->used_days }}</span>
                                <span class="text-[9px] text-gray-300">/</span>
                                <span class="text-[11px] font-bold {{ $bal->remaining_days > 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ $bal->remaining_days }}</span>
                            </div>
                            @if($bal->carry_over > 0)
                                <div class="text-[8px] text-indigo-500 font-medium">+{{ $bal->carry_over }} carry</div>
                            @endif
                        @else
                            <span class="text-[10px] text-gray-300">-</span>
                        @endif
                    </td>
                    @endforeach
                    <td class="py-2.5 px-4 text-center">
                        <button onclick="openEditBalance({{ $empId }}, '{{ $emp->full_name }}', {{ json_encode($empBalances->map(fn($b) => ['id' => $b->id, 'leave_type_id' => $b->leave_type_id, 'total_days' => $b->total_days, 'carry_over' => $b->carry_over, 'used_days' => $b->used_days])->values()) }})"
                            class="px-2.5 py-1.5 text-[10px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span> Adjust
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ 3 + count($leaveTypes) }}" class="py-10 text-center text-gray-400 text-sm">
                        Belum ada saldo cuti untuk tahun {{ $year }}.
                        <br><span class="text-[11px]">Klik tombol <strong>"Generate Saldo"</strong> di atas untuk membuat.</span>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Edit Balance Offcanvas --}}
<div id="editBalanceOff" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 transition-opacity" onclick="closeEditBalance()"></div>
    <div id="editBalancePanel" class="absolute top-0 right-0 h-full w-[400px] max-w-full bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out overflow-y-auto">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[14px] align-text-bottom">edit</span> Adjust Saldo Cuti</h3>
                <p class="text-[12px] text-gray-400 mt-0.5" id="balEmpName"></p>
            </div>
            <button onclick="closeEditBalance()" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-all cursor-pointer text-[16px]">✕</button>
        </div>
        <div class="p-6 space-y-4" id="balanceForms"></div>
    </div>
</div>

<script>
const leaveTypes = @json($leaveTypes->pluck('name', 'id'));

function openEditBalance(empId, name, balances) {
    document.getElementById('balEmpName').textContent = name;
    const container = document.getElementById('balanceForms');
    container.innerHTML = '';

    balances.forEach(bal => {
        const typeName = leaveTypes[bal.leave_type_id] || 'Unknown';
        container.innerHTML += `
            <form action="/admin/leave-balances/${bal.id}" method="POST" class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="PUT">
                <h4 class="text-[13px] font-bold text-gray-800 mb-3">${typeName}</h4>
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1">Jatah</label>
                        <input type="number" name="total_days" value="${bal.total_days}" min="0" class="w-full px-2 py-2 text-[13px] text-center border border-gray-300 rounded-md outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1">Carry Over</label>
                        <input type="number" name="carry_over" value="${bal.carry_over}" min="0" class="w-full px-2 py-2 text-[13px] text-center border border-gray-300 rounded-md outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1">Terpakai</label>
                        <input type="number" name="used_days" value="${bal.used_days}" min="0" class="w-full px-2 py-2 text-[13px] text-center border border-gray-300 rounded-md outline-none focus:border-indigo-500">
                    </div>
                </div>
                <button type="submit" class="w-full px-3 py-2 text-[11px] font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition-all cursor-pointer"><span class="material-symbols-outlined text-[14px] align-text-bottom">save</span> Simpan</button>
            </form>
        `;
    });

    const offcanvas = document.getElementById('editBalanceOff');
    const panel = document.getElementById('editBalancePanel');
    offcanvas.classList.remove('hidden');
    requestAnimationFrame(() => {
        panel.classList.remove('translate-x-full');
        panel.classList.add('translate-x-0');
    });
}

function closeEditBalance() {
    const panel = document.getElementById('editBalancePanel');
    panel.classList.remove('translate-x-0');
    panel.classList.add('translate-x-full');
    setTimeout(() => document.getElementById('editBalanceOff').classList.add('hidden'), 300);
}
</script>
@endsection
