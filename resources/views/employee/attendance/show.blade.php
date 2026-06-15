@extends('employee.layouts.app')
@section('title', $title)

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">{{ $title }}</h1>
            <p class="text-[13px] text-gray-500 mt-1">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</p>
        </div>
        <div class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-lg bg-white border border-gray-200">
            <span class="material-symbols-outlined text-[18px] text-indigo-500">badge</span>
            <span class="text-[12px] font-bold text-gray-700">{{ $employee->employee_code }}</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span id="stepIcon" class="material-symbols-outlined text-[18px] text-indigo-500">location_on</span>
                <div>
                    <div id="stepTitle" class="text-[14px] font-bold text-gray-900">Langkah 1 dari 2 - Konfirmasi Lokasi</div>
                    <div id="stepHint" class="text-[11px] text-gray-400">Pastikan lokasi sudah akurat sebelum lanjut.</div>
                </div>
            </div>
            <span id="statusBadge" class="hidden sm:inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">Menunggu GPS</span>
        </div>

        <div id="locationStep">
            <div id="map" class="w-full h-[360px] bg-gray-100"></div>
            <div class="p-5 space-y-4">
                <div id="locationAlert" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-[13px] text-gray-600">
                    Mengambil lokasi perangkat...
                </div>

                <button id="retryLocationBtn" type="button"
                        class="hidden w-full items-center justify-center gap-2 px-4 py-2.5 text-[12px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                    <span class="material-symbols-outlined text-[17px]">my_location</span>
                    Coba Ambil Lokasi Lagi
                </button>

                @if($type === 'clock-in' && $settings['allow_remote_clockin'])
                    <div id="remoteNotesWrap" class="hidden">
                        <label class="block text-[12px] font-semibold text-gray-600 mb-1.5">Catatan Remote</label>
                        <textarea id="notesInput" rows="3" maxlength="500"
                                  class="w-full px-3 py-2.5 text-[13px] border border-gray-300 rounded-lg outline-none focus:border-indigo-500 transition-all"
                                  placeholder="Isi alasan jika berada di luar radius kantor"></textarea>
                    </div>
                @endif

                <button id="continueBtn" type="button"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:hover:translate-y-0 disabled:cursor-not-allowed"
                        disabled>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    Lanjutkan
                </button>
            </div>
        </div>

        <div id="cameraStep" class="hidden">
            <div class="relative bg-slate-950 overflow-hidden">
                <video id="cameraPreview" autoplay playsinline muted class="w-full h-[420px] object-cover bg-slate-950" style="transform: scaleX(-1);"></video>
                <canvas id="photoCanvas" class="hidden"></canvas>
                <div id="cameraOverlay" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                    <div class="w-[58%] max-w-[280px] aspect-[3/4] border-2 border-white rounded-2xl shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                </div>
                <div id="submitOverlay" class="hidden absolute inset-0 bg-black/65 text-white flex flex-col items-center justify-center gap-3">
                    <div class="w-9 h-9 border-2 border-white/40 border-t-white rounded-full animate-spin"></div>
                    <div class="text-[13px] font-bold">Memproses presensi...</div>
                </div>
            </div>
            <div class="p-5 space-y-3">
                <div id="cameraAlert" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-[13px] text-gray-600">
                    Posisikan wajah dalam bingkai dan pastikan pencahayaan cukup.
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button id="backBtn" type="button"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Kembali
                    </button>
                    <button id="captureBtn" type="button"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all">
                        <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                        Ambil Foto
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const endpoint = @json($endpoint);
const settings = @json($settings);
const attendanceType = @json($type);
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

let map;
let userMarker;
let officeMarker;
let officeCircle;
let currentPosition = null;
let cameraStream = null;

const locationStep = document.getElementById('locationStep');
const cameraStep = document.getElementById('cameraStep');
const locationAlert = document.getElementById('locationAlert');
const cameraAlert = document.getElementById('cameraAlert');
const statusBadge = document.getElementById('statusBadge');
const continueBtn = document.getElementById('continueBtn');
const captureBtn = document.getElementById('captureBtn');
const backBtn = document.getElementById('backBtn');
const stepIcon = document.getElementById('stepIcon');
const stepTitle = document.getElementById('stepTitle');
const stepHint = document.getElementById('stepHint');
const remoteNotesWrap = document.getElementById('remoteNotesWrap');
const notesInput = document.getElementById('notesInput');
const submitOverlay = document.getElementById('submitOverlay');
const retryLocationBtn = document.getElementById('retryLocationBtn');

initMap();
initLocation();

continueBtn.addEventListener('click', async () => {
    locationStep.classList.add('hidden');
    cameraStep.classList.remove('hidden');
    stepIcon.textContent = 'face';
    stepTitle.textContent = 'Langkah 2 dari 2 - Verifikasi Wajah';
    stepHint.textContent = 'Ambil selfie untuk menyelesaikan presensi.';
    await startCamera();
});

backBtn.addEventListener('click', () => {
    stopCamera();
    cameraStep.classList.add('hidden');
    locationStep.classList.remove('hidden');
    stepIcon.textContent = 'location_on';
    stepTitle.textContent = 'Langkah 1 dari 2 - Konfirmasi Lokasi';
    stepHint.textContent = 'Pastikan lokasi sudah akurat sebelum lanjut.';
});

captureBtn.addEventListener('click', submitAttendance);
retryLocationBtn.addEventListener('click', initLocation);

function initMap() {
    const officeLat = Number(settings.office_latitude || 0);
    const officeLng = Number(settings.office_longitude || 0);
    const radius = Number(settings.office_radius_meters || 100);
    const center = [officeLat || 0, officeLng || 0];

    map = L.map('map').setView(center, officeLat && officeLng ? 16 : 4);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (officeLat && officeLng) {
        officeMarker = L.marker(center).addTo(map).bindPopup('Lokasi Kantor');
        officeCircle = L.circle(center, {
            radius,
            color: '#6366f1',
            fillColor: '#6366f1',
            fillOpacity: 0.1,
            weight: 2,
        }).addTo(map);
        map.fitBounds(officeCircle.getBounds().pad(0.35));
    }
}

function initLocation() {
    retryLocationBtn.classList.add('hidden');
    retryLocationBtn.classList.remove('inline-flex');
    continueBtn.disabled = true;
    statusBadge.className = 'hidden sm:inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600';
    statusBadge.textContent = 'Menunggu GPS';
    locationAlert.className = 'rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-[13px] text-gray-600';
    locationAlert.textContent = 'Mengambil lokasi perangkat...';

    if (!navigator.geolocation) {
        setLocationError('Browser tidak mendukung GPS.');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        position => {
            currentPosition = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
                timestamp: new Date(position.timestamp).toISOString(),
            };

            const latLng = [currentPosition.latitude, currentPosition.longitude];
            if (userMarker) userMarker.remove();
            userMarker = L.marker(latLng).addTo(map).bindPopup('Lokasi Anda').openPopup();
            map.setView(latLng, 17);

            const distance = distanceToOffice(currentPosition.latitude, currentPosition.longitude);
            renderLocationStatus(distance);
            continueBtn.disabled = false;
        },
        error => setLocationError(locationErrorMessage(error)),
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

function renderLocationStatus(distance) {
    const accuracy = Math.round(currentPosition.accuracy || 0);
    const radius = Number(settings.office_radius_meters || 100);
    const outside = distance !== null && distance > radius;
    const distanceText = distance === null ? '-' : `${Math.round(distance)}m`;

    statusBadge.classList.remove('hidden', 'bg-gray-100', 'text-gray-600', 'bg-emerald-100', 'text-emerald-700', 'bg-amber-100', 'text-amber-800');

    if (outside) {
        statusBadge.classList.add('bg-amber-100', 'text-amber-800');
        statusBadge.textContent = 'Di luar radius';
        locationAlert.className = 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800';
        locationAlert.textContent = `Lokasi terdeteksi ${distanceText} dari kantor. Akurasi GPS ${accuracy}m.`;
        if (remoteNotesWrap) remoteNotesWrap.classList.remove('hidden');
    } else {
        statusBadge.classList.add('bg-emerald-100', 'text-emerald-700');
        statusBadge.textContent = 'Dalam radius';
        locationAlert.className = 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] text-emerald-800';
        locationAlert.textContent = `Lokasi valid. Jarak dari kantor ${distanceText}, akurasi GPS ${accuracy}m.`;
        if (remoteNotesWrap) remoteNotesWrap.classList.add('hidden');
    }
}

function setLocationError(message) {
    const gpsRequired = Boolean(settings.require_gps);

    statusBadge.classList.remove('hidden', 'bg-gray-100', 'text-gray-600', 'bg-emerald-100', 'text-emerald-700', 'bg-amber-100', 'text-amber-800', 'bg-red-100', 'text-red-800');
    retryLocationBtn.classList.remove('hidden');
    retryLocationBtn.classList.add('inline-flex');

    if (gpsRequired) {
        statusBadge.classList.add('bg-red-100', 'text-red-800');
        statusBadge.textContent = 'GPS gagal';
        locationAlert.className = 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-800';
        locationAlert.textContent = message;
        continueBtn.disabled = true;
        return;
    }

    statusBadge.classList.add('bg-amber-100', 'text-amber-800');
    statusBadge.textContent = 'GPS opsional';
    locationAlert.className = 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800';
    locationAlert.textContent = message + ' GPS tidak diwajibkan, jadi Anda tetap bisa lanjut tanpa data lokasi.';
    continueBtn.disabled = false;
}

function locationErrorMessage(error) {
    if (!error) return 'Gagal mengambil lokasi.';

    if (error.code === error.PERMISSION_DENIED) {
        return 'Izin lokasi ditolak. Klik ikon lokasi di address bar browser, izinkan akses lokasi, lalu coba lagi.';
    }

    if (error.code === error.POSITION_UNAVAILABLE) {
        return 'Lokasi perangkat tidak tersedia. Pastikan Location Service/GPS aktif di perangkat.';
    }

    if (error.code === error.TIMEOUT) {
        return 'Pengambilan lokasi terlalu lama. Dekatkan perangkat ke area dengan sinyal lokasi lebih baik lalu coba lagi.';
    }

    return 'Gagal mengambil lokasi. Aktifkan izin lokasi lalu coba lagi.';
}

function distanceToOffice(lat, lng) {
    const officeLat = Number(settings.office_latitude || 0);
    const officeLng = Number(settings.office_longitude || 0);
    if (!officeLat || !officeLng) return null;

    const earthRadius = 6371000;
    const dLat = toRad(officeLat - lat);
    const dLng = toRad(officeLng - lng);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
        + Math.cos(toRad(lat)) * Math.cos(toRad(officeLat))
        * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return earthRadius * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
}

function toRad(value) {
    return value * Math.PI / 180;
}

async function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        cameraAlert.className = 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-800';
        cameraAlert.textContent = 'Browser tidak mendukung akses kamera.';
        captureBtn.disabled = true;
        return;
    }

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false,
        });
        document.getElementById('cameraPreview').srcObject = cameraStream;
        captureBtn.disabled = false;
    } catch (error) {
        cameraAlert.className = 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-800';
        cameraAlert.textContent = 'Gagal membuka kamera. Izinkan akses kamera lalu coba lagi.';
        captureBtn.disabled = true;
    }
}

function stopCamera() {
    if (!cameraStream) return;
    cameraStream.getTracks().forEach(track => track.stop());
    cameraStream = null;
}

async function submitAttendance() {
    if (!currentPosition && settings.require_gps) return;

    captureBtn.disabled = true;
    submitOverlay.classList.remove('hidden');

    try {
        const photoBase64 = capturePhotoBase64();
        const payload = { photo_base64: photoBase64 };

        if (currentPosition) {
            payload.latitude = currentPosition.latitude;
            payload.longitude = currentPosition.longitude;
            payload.location_accuracy = currentPosition.accuracy;
            payload.location_timestamp = currentPosition.timestamp;
        }

        if (notesInput && notesInput.value.trim()) {
            payload.notes = notesInput.value.trim();
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });
        const body = await response.json();

        if (!response.ok || !body.success) {
            throw new Error(body.message || 'Presensi gagal diproses.');
        }

        stopCamera();
        window.location.href = @json(route('employee.dashboard'));
    } catch (error) {
        cameraAlert.className = 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-800';
        cameraAlert.textContent = error.message;
        captureBtn.disabled = false;
        submitOverlay.classList.add('hidden');
    }
}

function capturePhotoBase64() {
    const video = document.getElementById('cameraPreview');
    const canvas = document.getElementById('photoCanvas');
    const width = video.videoWidth || 640;
    const height = video.videoHeight || 480;

    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    context.translate(width, 0);
    context.scale(-1, 1);
    context.drawImage(video, 0, 0, width, height);

    return canvas.toDataURL('image/jpeg', 0.86).split(',')[1];
}
</script>
@endpush
