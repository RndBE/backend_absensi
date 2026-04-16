@extends('admin.layouts.app')
@section('title', 'Pengaturan Presensi')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<form action="{{ route('admin.attendance-settings.update') }}" method="POST">
    @csrf @method('PUT')

    <div class="flex items-center justify-between mb-5">
        <h3 class="text-[15px] font-bold text-gray-900 flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">settings</span> Pengaturan Presensi
        </h3>
        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-[13px] font-semibold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">save</span> Simpan Pengaturan
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- ═══════════ LOKASI KANTOR ═══════════ --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-indigo-500">location_on</span>
                <h4 class="text-[14px] font-bold text-gray-900">Lokasi Kantor</h4>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Alamat Kantor</label>
                    <input type="text" name="office_address" value="{{ $settings['office_address'] }}" placeholder="Jl. Contoh No. 123, Kota" class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 transition-all">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Latitude</label>
                        <input type="text" name="office_latitude" id="latInput" value="{{ $settings['office_latitude'] }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 font-mono">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Longitude</label>
                        <input type="text" name="office_longitude" id="lngInput" value="{{ $settings['office_longitude'] }}" required class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 font-mono">
                    </div>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">
                        Radius Clock-In (meter)
                        <span class="text-gray-400 font-normal">— min 50, max 1000</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="range" name="office_radius_meters" id="radiusSlider" min="50" max="1000" step="10" value="{{ $settings['office_radius_meters'] }}" class="flex-1 accent-indigo-500" oninput="updateRadius(this.value)">
                        <span id="radiusVal" class="min-w-[50px] text-center px-3 py-1.5 bg-indigo-50 text-indigo-700 text-[14px] font-black rounded-lg">{{ $settings['office_radius_meters'] }}</span>
                        <span class="text-[12px] text-gray-400">m</span>
                    </div>
                </div>
                {{-- Leaflet Map --}}
                <div class="rounded-lg overflow-hidden border border-gray-200" style="position: relative; z-index: 0;">
                    <div id="officeMap" style="height: 280px; width: 100%;"></div>
                </div>
                <p class="text-[10px] text-gray-400 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">info</span>
                    Drag marker untuk mengubah lokasi kantor. Lingkaran biru menunjukkan radius clock-in.
                </p>
            </div>
        </div>

        {{-- ═══════════ ATURAN CLOCK-IN ═══════════ --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-emerald-500">fingerprint</span>
                <h4 class="text-[14px] font-bold text-gray-900">Aturan Clock-In</h4>
            </div>
            <div class="p-5 space-y-1">
                {{-- Photo Required --}}
                <label class="flex items-center justify-between p-3.5 rounded-lg border border-gray-100 hover:bg-gray-50 transition-all cursor-pointer group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px] text-gray-400 group-hover:text-indigo-500 transition-colors">photo_camera</span>
                        <div>
                            <div class="text-[13px] font-semibold text-gray-800">Wajib Selfie</div>
                            <div class="text-[11px] text-gray-400">Karyawan harus foto selfie saat clock in/out</div>
                        </div>
                    </div>
                    <input type="checkbox" name="require_photo" value="1" {{ $settings['require_photo'] == '1' ? 'checked' : '' }}
                        class="w-5 h-5 accent-indigo-500 rounded cursor-pointer">
                </label>

                {{-- GPS Required --}}
                <label class="flex items-center justify-between p-3.5 rounded-lg border border-gray-100 hover:bg-gray-50 transition-all cursor-pointer group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px] text-gray-400 group-hover:text-indigo-500 transition-colors">gps_fixed</span>
                        <div>
                            <div class="text-[13px] font-semibold text-gray-800">Wajib GPS</div>
                            <div class="text-[11px] text-gray-400">Lokasi karyawan harus terdeteksi saat presensi</div>
                        </div>
                    </div>
                    <input type="checkbox" name="require_gps" value="1" {{ $settings['require_gps'] == '1' ? 'checked' : '' }}
                        class="w-5 h-5 accent-indigo-500 rounded cursor-pointer">
                </label>

                {{-- Face Verification --}}
                <label class="flex items-center justify-between p-3.5 rounded-lg border border-gray-100 hover:bg-gray-50 transition-all cursor-pointer group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px] text-gray-400 group-hover:text-indigo-500 transition-colors">face</span>
                        <div>
                            <div class="text-[13px] font-semibold text-gray-800">Verifikasi Wajah</div>
                            <div class="text-[11px] text-gray-400">Bandingkan selfie clock-in dengan foto profil karyawan</div>
                        </div>
                    </div>
                    <input type="checkbox" name="face_verification_enabled" value="1" {{ $settings['face_verification_enabled'] == '1' ? 'checked' : '' }}
                        class="w-5 h-5 accent-indigo-500 rounded cursor-pointer">
                </label>

                {{-- Allow Remote --}}
                <label class="flex items-center justify-between p-3.5 rounded-lg border border-gray-100 hover:bg-gray-50 transition-all cursor-pointer group" id="remoteToggle">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px] text-gray-400 group-hover:text-indigo-500 transition-colors">share_location</span>
                        <div>
                            <div class="text-[13px] font-semibold text-gray-800">Izinkan Clock-In Remote</div>
                            <div class="text-[11px] text-gray-400">Karyawan bisa clock in di luar radius kantor</div>
                        </div>
                    </div>
                    <input type="checkbox" name="allow_remote_clockin" value="1" {{ $settings['allow_remote_clockin'] == '1' ? 'checked' : '' }}
                        class="w-5 h-5 accent-indigo-500 rounded cursor-pointer" id="allowRemoteCheck" onchange="toggleRemoteOptions()">
                </label>

                {{-- Remote Sub-options --}}
                <div id="remoteOptions" class="{{ $settings['allow_remote_clockin'] == '1' ? '' : 'hidden' }} ml-8 space-y-1">
                    <label class="flex items-center justify-between p-3 rounded-lg border border-dashed border-gray-200 hover:bg-gray-50 transition-all cursor-pointer group">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-gray-400 group-hover:text-amber-500 transition-colors">approval</span>
                            <div>
                                <div class="text-[12px] font-semibold text-gray-700">Butuh Approval</div>
                                <div class="text-[10px] text-gray-400">Clock-in remote harus disetujui atasan</div>
                            </div>
                        </div>
                        <input type="checkbox" name="remote_requires_approval" value="1" {{ $settings['remote_requires_approval'] == '1' ? 'checked' : '' }}
                            class="w-4.5 h-4.5 accent-amber-500 rounded cursor-pointer">
                    </label>
                    <label class="flex items-center justify-between p-3 rounded-lg border border-dashed border-gray-200 hover:bg-gray-50 transition-all cursor-pointer group">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-gray-400 group-hover:text-amber-500 transition-colors">notes</span>
                            <div>
                                <div class="text-[12px] font-semibold text-gray-700">Wajib Catatan / Alasan</div>
                                <div class="text-[10px] text-gray-400">Karyawan harus isi alasan clock-in remote</div>
                            </div>
                        </div>
                        <input type="checkbox" name="remote_requires_notes" value="1" {{ $settings['remote_requires_notes'] == '1' ? 'checked' : '' }}
                            class="w-4.5 h-4.5 accent-amber-500 rounded cursor-pointer">
                    </label>
                </div>
            </div>
        </div>

        {{-- ═══════════ NOTIFIKASI & OTOMATIS ═══════════ --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm lg:col-span-2">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-amber-500">notifications</span>
                <h4 class="text-[14px] font-bold text-gray-900">Notifikasi & Otomatis</h4>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Clock-in Reminder --}}
                    <div class="p-4 rounded-lg border border-gray-100 hover:bg-gray-50 transition-all">
                        <label class="flex items-center justify-between cursor-pointer mb-3">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] text-gray-400">alarm</span>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">Reminder Clock-In</div>
                                    <div class="text-[11px] text-gray-400">Push notification pengingat clock-in</div>
                                </div>
                            </div>
                            <input type="checkbox" name="clockin_reminder_enabled" value="1" {{ $settings['clockin_reminder_enabled'] == '1' ? 'checked' : '' }}
                                class="w-5 h-5 accent-indigo-500 rounded cursor-pointer" id="reminderCheck" onchange="toggleReminderTime()">
                        </label>
                        <div id="reminderTimeWrap" class="{{ $settings['clockin_reminder_enabled'] == '1' ? '' : 'opacity-40 pointer-events-none' }}">
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">Jam Reminder</label>
                            <input type="time" name="clockin_reminder_time" value="{{ $settings['clockin_reminder_time'] }}" class="px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 w-full">
                        </div>
                    </div>

                    {{-- Auto Clock-out --}}
                    <div class="p-4 rounded-lg border border-gray-100 hover:bg-gray-50 transition-all">
                        <label class="flex items-center justify-between cursor-pointer mb-3">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] text-gray-400">timer_off</span>
                                <div>
                                    <div class="text-[13px] font-semibold text-gray-800">Auto Clock-Out</div>
                                    <div class="text-[11px] text-gray-400">Otomatis clock-out jika karyawan lupa</div>
                                </div>
                            </div>
                            <input type="checkbox" name="auto_clockout_enabled" value="1" {{ $settings['auto_clockout_enabled'] == '1' ? 'checked' : '' }}
                                class="w-5 h-5 accent-indigo-500 rounded cursor-pointer" id="autoClockoutCheck" onchange="toggleAutoClockoutTime()">
                        </label>
                        <div id="autoClockoutTimeWrap" class="{{ $settings['auto_clockout_enabled'] == '1' ? '' : 'opacity-40 pointer-events-none' }}">
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">Jam Auto Clock-Out</label>
                            <input type="time" name="auto_clockout_time" value="{{ $settings['auto_clockout_time'] }}" class="px-3 py-2 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 w-full">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Toggle helpers
function toggleRemoteOptions() {
    document.getElementById('remoteOptions').classList.toggle('hidden', !document.getElementById('allowRemoteCheck').checked);
}
function toggleReminderTime() {
    const w = document.getElementById('reminderTimeWrap');
    const c = document.getElementById('reminderCheck').checked;
    w.classList.toggle('opacity-40', !c);
    w.classList.toggle('pointer-events-none', !c);
}
function toggleAutoClockoutTime() {
    const w = document.getElementById('autoClockoutTimeWrap');
    const c = document.getElementById('autoClockoutCheck').checked;
    w.classList.toggle('opacity-40', !c);
    w.classList.toggle('pointer-events-none', !c);
}

// ═══════════ LEAFLET MAP ═══════════
const initLat = parseFloat(document.getElementById('latInput').value) || 0;
const initLng = parseFloat(document.getElementById('lngInput').value) || 0;
const initRadius = parseInt(document.getElementById('radiusSlider').value) || 100;

const map = L.map('officeMap').setView([initLat, initLng], 16);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
}).addTo(map);

// Office marker (draggable)
const marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);
marker.bindPopup('<b>Lokasi Kantor</b>').openPopup();

// Radius circle
const circle = L.circle([initLat, initLng], {
    radius: initRadius,
    color: '#6366f1',
    fillColor: '#6366f1',
    fillOpacity: 0.12,
    weight: 2,
}).addTo(map);

// Fit map to circle bounds
map.fitBounds(circle.getBounds().pad(0.2));

// Update lat/lng when marker is dragged
marker.on('dragend', function (e) {
    const pos = e.target.getLatLng();
    document.getElementById('latInput').value = pos.lat.toFixed(6);
    document.getElementById('lngInput').value = pos.lng.toFixed(6);
    circle.setLatLng(pos);
});

// Update radius circle when slider changes
function updateRadius(val) {
    document.getElementById('radiusVal').textContent = val;
    circle.setRadius(parseInt(val));
    map.fitBounds(circle.getBounds().pad(0.2));
}

// Update marker + circle when lat/lng inputs change manually
document.getElementById('latInput').addEventListener('change', syncMapFromInputs);
document.getElementById('lngInput').addEventListener('change', syncMapFromInputs);

function syncMapFromInputs() {
    const lat = parseFloat(document.getElementById('latInput').value);
    const lng = parseFloat(document.getElementById('lngInput').value);
    if (!isNaN(lat) && !isNaN(lng)) {
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        map.setView([lat, lng]);
    }
}
</script>
@endsection
