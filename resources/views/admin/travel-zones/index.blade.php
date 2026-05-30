@extends('admin.layouts.app')
@section('title', 'Zona Perjalanan')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 shadow-sm">

    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-gray-900">
            <span class="material-symbols-outlined text-[20px] align-text-bottom">flight_takeoff</span> Zona Perjalanan
        </h3>
        <button onclick="openZoneModal('createModal')"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-teal-600 to-teal-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <span class="material-symbols-outlined text-[14px]">add</span> Tambah Zona
        </button>
    </div>

    <div class="p-5">
    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-[13px] font-medium">{{ session('success') }}</div>
    @endif

    <p class="text-[12px] text-gray-400 mb-4">Zona ditentukan otomatis berdasarkan jarak km perjalanan. Zona dengan <strong>Max KM kosong</strong> berarti tidak ada batas atas (≥ min km).</p>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 w-16">Zona</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Nama Zona</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Rentang KM</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50">Uang Makan / Hari</th>
                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50 w-32">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($zones as $zone)
                <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-teal-100 text-teal-700 text-[13px] font-bold">{{ $zone->zone }}</span>
                    </td>
                    <td class="px-4 py-3 text-[13px] font-medium text-gray-800">{{ $zone->name }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-blue-50 text-blue-700">
                            <span class="material-symbols-outlined text-[12px]">route</span>
                            {{ $zone->km_range_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-[13px] font-bold text-gray-900">Rp {{ number_format($zone->meal_allowance, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        <button onclick="editZone({{ $zone->id }}, {{ $zone->zone }}, '{{ addslashes($zone->name) }}', {{ $zone->min_km }}, {{ $zone->max_km ?? 'null' }}, {{ $zone->meal_allowance }})"
                                class="inline-flex items-center px-2 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer">Edit</button>
                        <form method="POST" action="{{ route('admin.travel-zones.destroy', $zone->id) }}" class="inline" data-confirm="Hapus zona ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-2 py-1 text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all cursor-pointer">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Belum ada zona perjalanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 z-[80] overflow-y-auto bg-slate-900/45 px-4 py-6">
    <div class="flex min-h-[calc(100vh-3rem)] items-start justify-center sm:items-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900">Tambah Zona</h3>
            <button type="button" onclick="closeZoneModal('createModal')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.travel-zones.store') }}" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nomor Zona *</label>
                <input type="number" name="zone" required min="1" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nama Zona *</label>
                <input type="text" name="name" required placeholder="Dalam Kota / Luar Kota / Luar Pulau" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Rentang KM *</label>
                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 mt-1">
                    <div class="relative min-w-0">
                        <input type="number" name="min_km" required min="0" value="0" placeholder="Min" class="w-full px-3 py-2 pr-10 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 font-semibold">km</span>
                    </div>
                    <span class="text-gray-400 text-[13px] font-medium shrink-0">—</span>
                    <div class="relative min-w-0">
                        <input type="number" name="max_km" min="1" placeholder="Maks" class="w-full px-3 py-2 pr-10 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 font-semibold">km</span>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mt-1">Kosongkan Maks jika zona ini tidak punya batas atas (≥ min km).</p>
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Uang Makan (Rp per hari) *</label>
                <input type="number" name="meal_allowance" required min="0" step="1000" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-all cursor-pointer">Simpan</button>
        </form>
    </div>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="hidden fixed inset-0 z-[80] overflow-y-auto bg-slate-900/45 px-4 py-6">
    <div class="flex min-h-[calc(100vh-3rem)] items-start justify-center sm:items-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900">Edit Zona</h3>
            <button type="button" onclick="closeZoneModal('editModal')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
        </div>
        <form id="editForm" method="POST" class="p-5 space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nomor Zona *</label>
                <input type="number" name="zone" id="edit_zone" required min="1" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Nama Zona *</label>
                <input type="text" name="name" id="edit_name" required class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Rentang KM *</label>
                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 mt-1">
                    <div class="relative min-w-0">
                        <input type="number" name="min_km" id="edit_min_km" required min="0" placeholder="Min" class="w-full px-3 py-2 pr-10 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 font-semibold">km</span>
                    </div>
                    <span class="text-gray-400 text-[13px] font-medium shrink-0">—</span>
                    <div class="relative min-w-0">
                        <input type="number" name="max_km" id="edit_max_km" min="1" placeholder="Maks" class="w-full px-3 py-2 pr-10 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 font-semibold">km</span>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mt-1">Kosongkan Maks jika zona ini tidak punya batas atas (≥ min km).</p>
            </div>
            <div>
                <label class="text-[12px] font-bold text-gray-500 uppercase">Uang Makan (Rp per hari) *</label>
                <input type="number" name="meal_allowance" id="edit_meal" required min="0" step="1000" class="w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-all cursor-pointer">Perbarui</button>
        </form>
    </div>
    </div>
</div>

<script>
function openZoneModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

function closeZoneModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function editZone(id, zone, name, minKm, maxKm, meal) {
    document.getElementById('editForm').action = '/admin/travel-zones/' + id;
    document.getElementById('edit_zone').value = zone;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_min_km').value = minKm;
    document.getElementById('edit_max_km').value = maxKm !== null ? maxKm : '';
    document.getElementById('edit_meal').value = meal;
    openZoneModal('editModal');
}
</script>
@endsection
