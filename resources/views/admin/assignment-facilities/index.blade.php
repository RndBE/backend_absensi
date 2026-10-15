@extends('admin.layouts.app')
@section('title', 'Kendaraan & HT')

@section('content')
@php
    $input = 'w-full mt-1 px-3 py-2 border border-gray-200 rounded-lg text-[13px] focus:ring-2 focus:ring-teal-300 focus:border-teal-400';
    $labelClass = 'text-[12px] font-bold text-gray-500 uppercase';
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 bg-gray-50';
    $addBtn = 'inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-semibold text-white bg-gradient-to-br from-teal-600 to-teal-400 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all duration-200 cursor-pointer';
    $editBtn = 'inline-flex items-center px-2 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-all cursor-pointer';
    $deleteBtn = 'inline-flex items-center px-2 py-1 text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all cursor-pointer';
@endphp

<p class="text-[12px] text-gray-500 mb-4">
    Daftar ini dipakai di form surat tugas bagian Perangkat Komunikasi & Transportasi. Kendaraan pribadi, rental sekali pakai,
    kereta, atau pesawat tidak perlu didaftarkan: cukup diketik manual di surat. Item yang pernah dipakai di surat tidak bisa dihapus, hanya dinonaktifkan.
</p>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    {{-- Kendaraan --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[20px] align-text-bottom">directions_car</span> Kendaraan</h3>
            <button type="button" onclick="openFacilityModal('vehicle')" class="{{ $addBtn }}">
                <span class="material-symbols-outlined text-[14px]">add</span> Tambah
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="{{ $th }}">Kendaraan</th>
                        <th class="{{ $th }}">Tertulis di surat</th>
                        <th class="{{ $th }} text-center w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($vehicles as $vehicle)
                    <tr class="border-b border-gray-100 {{ $vehicle->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3">
                            <div class="text-[13px] font-semibold text-gray-800">{{ $vehicle->name }} @unless($vehicle->is_active)<span class="text-[10px] font-semibold text-gray-500">(nonaktif)</span>@endunless</div>
                            <div class="text-[11px] text-gray-400">{{ $vehicle->plate_number ?: 'Tanpa plat' }}{{ $vehicle->notes ? ' · '.$vehicle->notes : '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-gray-600">{{ $vehicle->letterLabel() }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @php $payload = $vehicle->only(['id', 'name', 'plate_number', 'ownership', 'notes', 'is_active']); @endphp
                            <button type="button" class="{{ $editBtn }}" data-payload="{{ json_encode($payload) }}"
                                    onclick="openFacilityModal('vehicle', JSON.parse(this.dataset.payload))">Edit</button>
                            <form method="POST" action="{{ route('admin.assignment-facilities.vehicles.destroy', $vehicle->id) }}" class="inline" data-confirm="Hapus kendaraan ini? Kalau sudah pernah dipakai di surat, kendaraan hanya dinonaktifkan.">
                                @csrf @method('DELETE')
                                <button type="submit" class="{{ $deleteBtn }}">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400 text-sm">Belum ada kendaraan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Perangkat komunikasi --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-gray-900"><span class="material-symbols-outlined text-[20px] align-text-bottom">settings_remote</span> Perangkat Komunikasi (HT)</h3>
            <button type="button" onclick="openFacilityModal('device')" class="{{ $addBtn }}">
                <span class="material-symbols-outlined text-[14px]">add</span> Tambah
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="{{ $th }}">Kode Inventaris</th>
                        <th class="{{ $th }}">Nama / Tipe</th>
                        <th class="{{ $th }} text-center w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($devices as $device)
                    <tr class="border-b border-gray-100 {{ $device->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3 text-[13px] font-semibold text-gray-800 font-mono">{{ $device->code }} @unless($device->is_active)<span class="text-[10px] font-semibold text-gray-500 font-sans">(nonaktif)</span>@endunless</td>
                        <td class="px-4 py-3 text-[12.5px] text-gray-600">{{ $device->name ?: '-' }}{{ $device->notes ? ' · '.$device->notes : '' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @php $payload = $device->only(['id', 'code', 'name', 'notes', 'is_active']); @endphp
                            <button type="button" class="{{ $editBtn }}" data-payload="{{ json_encode($payload) }}"
                                    onclick="openFacilityModal('device', JSON.parse(this.dataset.payload))">Edit</button>
                            <form method="POST" action="{{ route('admin.assignment-facilities.devices.destroy', $device->id) }}" class="inline" data-confirm="Hapus perangkat ini? Kalau sudah pernah dipakai di surat, perangkat hanya dinonaktifkan.">
                                @csrf @method('DELETE')
                                <button type="submit" class="{{ $deleteBtn }}">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400 text-sm">Belum ada perangkat komunikasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal kendaraan --}}
<div id="vehicleModal" class="hidden fixed inset-0 z-[80] overflow-y-auto bg-slate-900/45 px-4 py-6">
    <div class="flex min-h-[calc(100vh-3rem)] items-start justify-center sm:items-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold text-gray-900" data-title>Tambah Kendaraan</h3>
                <button type="button" onclick="closeFacilityModal('vehicle')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.assignment-facilities.vehicles.store') }}" data-store-action="{{ route('admin.assignment-facilities.vehicles.store') }}" data-update-action="{{ url('admin/assignment-facilities/vehicles') }}" class="p-5 space-y-4">
                @csrf
                <input type="hidden" name="_method" value="POST" data-method>
                <div>
                    <label class="{{ $labelClass }}">Nama / Merek *</label>
                    <input type="text" name="name" required maxlength="100" placeholder="Alvez" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Nomor Plat</label>
                    <input type="text" name="plate_number" maxlength="20" placeholder="K 1734 AT" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Kepemilikan *</label>
                    {{-- Native: nilai diisi lewat JS saat edit, dan dropdown searchable-select tidak ikut
                         ter-update bila .value diubah programatik. --}}
                    <select name="ownership" data-no-search="1" class="{{ $input }}">
                        @foreach($ownerships as $key => $ownershipLabel)
                            <option value="{{ $key }}">{{ ucfirst($ownershipLabel) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Catatan</label>
                    <input type="text" name="notes" maxlength="255" class="{{ $input }}">
                </div>
                <label class="flex items-center gap-2 text-[13px] text-gray-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300"> Aktif (muncul di form surat tugas)
                </label>
                <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-all cursor-pointer">Simpan</button>
            </form>
        </div>
    </div>
</div>

{{-- Modal perangkat --}}
<div id="deviceModal" class="hidden fixed inset-0 z-[80] overflow-y-auto bg-slate-900/45 px-4 py-6">
    <div class="flex min-h-[calc(100vh-3rem)] items-start justify-center sm:items-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold text-gray-900" data-title>Tambah Perangkat</h3>
                <button type="button" onclick="closeFacilityModal('device')" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.assignment-facilities.devices.store') }}" data-store-action="{{ route('admin.assignment-facilities.devices.store') }}" data-update-action="{{ url('admin/assignment-facilities/devices') }}" class="p-5 space-y-4">
                @csrf
                <input type="hidden" name="_method" value="POST" data-method>
                <div>
                    <label class="{{ $labelClass }}">Kode Inventaris *</label>
                    <input type="text" name="code" required maxlength="100" placeholder="HT 009/ATC/VIII/2024" class="{{ $input }}">
                    <p class="text-[11px] text-gray-400 mt-1">Ditulis persis seperti ini di surat tugas.</p>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Nama / Tipe</label>
                    <input type="text" name="name" maxlength="100" placeholder="HT Motorola" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Catatan</label>
                    <input type="text" name="notes" maxlength="255" class="{{ $input }}">
                </div>
                <label class="flex items-center gap-2 text-[13px] text-gray-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300"> Aktif (muncul di form surat tugas)
                </label>
                <button type="submit" class="w-full px-4 py-2.5 text-[13px] font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-all cursor-pointer">Simpan</button>
            </form>
        </div>
    </div>
</div>

<script>
function openFacilityModal(kind, data = null) {
    const modal = document.getElementById(kind + 'Modal');
    const form = modal.querySelector('form');
    form.reset();
    form.action = data ? form.dataset.updateAction + '/' + data.id : form.dataset.storeAction;
    form.querySelector('[data-method]').value = data ? 'PUT' : 'POST';
    modal.querySelector('[data-title]').textContent = (data ? 'Edit ' : 'Tambah ') + (kind === 'vehicle' ? 'Kendaraan' : 'Perangkat');
    if (data) {
        Object.entries(data).forEach(([key, value]) => {
            if (key === 'id') return;
            if (key === 'is_active') {
                form.querySelector('input[type=checkbox][name=is_active]').checked = !!value;
                return;
            }
            const field = form.querySelector(`[name="${key}"]`);
            if (field) field.value = value ?? '';
        });
    }
    modal.classList.remove('hidden');
}

function closeFacilityModal(kind) {
    document.getElementById(kind + 'Modal').classList.add('hidden');
}
</script>
@endsection
