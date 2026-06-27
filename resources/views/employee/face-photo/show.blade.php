@extends('employee.layouts.app')
@section('title', 'Verifikasi Wajah')

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-indigo-600 mb-2">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <h1 class="text-[22px] font-black text-gray-900">Daftarkan Wajah</h1>
            <p class="text-[13px] text-gray-500 mt-1">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <span class="hidden sm:inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold {{ $employee->face_photo ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">
            {{ $employee->face_photo ? 'Terdaftar' : 'Belum Terdaftar' }}
        </span>
    </div>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" data-store-url="{{ route('employee.face-photo.store') }}">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-indigo-500">face</span>
                <div>
                    <div class="text-[14px] font-bold text-gray-900">Foto Referensi Wajah</div>
                    <div class="text-[11px] text-gray-400">{{ $faceVerificationEnabled ? 'Aktif untuk presensi' : 'Tersimpan sebagai data referensi' }}</div>
                </div>
            </div>
        </div>

        @if($employee->face_photo)
            <div class="p-5 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <img id="currentFacePhoto" src="{{ asset('storage/'.$employee->face_photo) }}" alt="Foto wajah" class="w-16 h-16 rounded-xl object-cover border border-gray-200 bg-gray-100">
                    <div>
                        <div class="text-[13px] font-bold text-gray-900">Foto wajah tersimpan</div>
                        <div class="text-[12px] text-gray-500 mt-1">Update foto jika wajah atau pencahayaan referensi berubah.</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="relative bg-slate-950 overflow-hidden">
            <video id="cameraPreview" autoplay playsinline muted class="w-full h-[420px] object-cover bg-slate-950" style="transform: scaleX(-1);"></video>
            <canvas id="photoCanvas" class="hidden"></canvas>
            <canvas id="qualityCanvas" class="hidden"></canvas>
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                <div id="faceFrame" class="w-[58%] max-w-[280px] aspect-[3/4] border-2 border-white rounded-2xl shadow-[0_0_0_9999px_rgba(0,0,0,0.35)] transition-colors"></div>
            </div>
            <div id="qualityBadge" class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-black/55 text-white backdrop-blur-sm">
                <span class="material-symbols-outlined text-[14px]">hourglass_empty</span>
                <span id="qualityBadgeText">Memeriksa kualitas...</span>
            </div>
            <div id="submitOverlay" class="hidden absolute inset-0 bg-black/65 text-white flex flex-col items-center justify-center gap-3">
                <div class="w-9 h-9 border-2 border-white/40 border-t-white rounded-full animate-spin"></div>
                <div class="text-[13px] font-bold">Menyimpan foto wajah...</div>
            </div>
        </div>

        <div class="p-5 space-y-3">
            <div id="facePhotoAlert" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-[13px] text-gray-600">
                Posisikan wajah dalam bingkai.
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button id="startCameraBtn" type="button"
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                    <span class="material-symbols-outlined text-[18px]">videocam</span>
                    Buka Kamera
                </button>
                <button id="saveFacePhotoBtn" type="button"
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 text-[13px] font-bold text-white bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:hover:translate-y-0 disabled:cursor-not-allowed"
                        disabled>
                    <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                    Simpan Foto
                </button>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
const facePhotoStoreEndpoint = @json(route('employee.face-photo.store'));
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let cameraStream = null;

const cameraPreview = document.getElementById('cameraPreview');
const photoCanvas = document.getElementById('photoCanvas');
const alertBox = document.getElementById('facePhotoAlert');
const startCameraBtn = document.getElementById('startCameraBtn');
const saveFacePhotoBtn = document.getElementById('saveFacePhotoBtn');
const submitOverlay = document.getElementById('submitOverlay');
const qualityCanvas = document.getElementById('qualityCanvas');
const qualityBadge = document.getElementById('qualityBadge');
const qualityBadgeText = document.getElementById('qualityBadgeText');
const faceFrame = document.getElementById('faceFrame');

// Ambang batas filter kualitas foto wajah.
const QUALITY = {
    darkMax: 55,    // rata-rata kecerahan di bawah ini = terlalu gelap
    brightMin: 215, // rata-rata kecerahan di atas ini = terlalu terang
    blurMin: 8,     // variance Laplacian di bawah ini = kurang fokus (peringatan)
};
let qualityTimer = null;
let qualityOk = false;

startCameraBtn.addEventListener('click', startCamera);
saveFacePhotoBtn.addEventListener('click', saveFacePhoto);

async function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setAlert('Browser tidak mendukung akses kamera.', 'error');
        return;
    }

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false,
        });
        cameraPreview.srcObject = cameraStream;
        saveFacePhotoBtn.disabled = true;
        startQualityMonitor();
    } catch (error) {
        saveFacePhotoBtn.disabled = true;
        setAlert('Gagal membuka kamera. Izinkan akses kamera lalu coba lagi.', 'error');
    }
}

function startQualityMonitor() {
    stopQualityMonitor();
    qualityTimer = window.setInterval(checkQuality, 450);
}

function stopQualityMonitor() {
    if (qualityTimer) {
        window.clearInterval(qualityTimer);
        qualityTimer = null;
    }
}

function checkQuality() {
    if (!cameraPreview.videoWidth) return;

    const w = 160;
    const h = 120;
    qualityCanvas.width = w;
    qualityCanvas.height = h;
    const ctx = qualityCanvas.getContext('2d');
    ctx.drawImage(cameraPreview, 0, 0, w, h);
    const data = ctx.getImageData(0, 0, w, h).data;

    // Konversi ke grayscale + hitung kecerahan rata-rata.
    const gray = new Float32Array(w * h);
    let sum = 0;
    for (let i = 0, p = 0; i < data.length; i += 4, p++) {
        const g = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        gray[p] = g;
        sum += g;
    }
    const brightness = sum / (w * h);

    // Ketajaman = variance dari Laplacian (semakin kecil = semakin buram).
    let lapSum = 0;
    let lapSqSum = 0;
    let count = 0;
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const idx = y * w + x;
            const lap = 4 * gray[idx] - gray[idx - 1] - gray[idx + 1] - gray[idx - w] - gray[idx + w];
            lapSum += lap;
            lapSqSum += lap * lap;
            count++;
        }
    }
    const mean = lapSum / count;
    const sharpness = lapSqSum / count - mean * mean;

    applyQuality(brightness, sharpness);
}

function applyQuality(brightness, sharpness) {
    let blocking = false;
    let icon = 'check_circle';
    let badgeText = 'Kualitas baik';
    let badgeColor = 'bg-emerald-500/90 text-white';
    let alertClass = 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] text-emerald-800';
    let alertText = 'Pencahayaan cukup. Wajah siap difoto.';
    let frameColor = 'border-emerald-400';

    if (brightness < QUALITY.darkMax) {
        blocking = true;
        icon = 'dark_mode';
        badgeText = 'Terlalu gelap';
        alertText = 'Pencahayaan terlalu gelap. Cari tempat yang lebih terang.';
    } else if (brightness > QUALITY.brightMin) {
        blocking = true;
        icon = 'light_mode';
        badgeText = 'Terlalu terang';
        alertText = 'Cahaya terlalu silau. Hindari cahaya langsung di belakang Anda.';
    } else if (sharpness < QUALITY.blurMin) {
        // Hanya peringatan, foto tetap boleh diambil.
        icon = 'blur_on';
        badgeText = 'Kurang fokus';
        alertText = 'Gambar kurang fokus. Tahan perangkat agar stabil.';
    }

    if (blocking) {
        badgeColor = 'bg-rose-500/90 text-white';
        alertClass = 'rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] text-rose-800';
        frameColor = 'border-rose-400';
    } else if (icon === 'blur_on') {
        badgeColor = 'bg-amber-500/90 text-white';
        alertClass = 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800';
        frameColor = 'border-amber-400';
    }

    qualityBadge.className = 'absolute top-3 left-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold backdrop-blur-sm ' + badgeColor;
    qualityBadge.querySelector('.material-symbols-outlined').textContent = icon;
    qualityBadgeText.textContent = badgeText;

    faceFrame.className = 'w-[58%] max-w-[280px] aspect-[3/4] border-2 rounded-2xl shadow-[0_0_0_9999px_rgba(0,0,0,0.35)] transition-colors ' + frameColor;

    alertBox.className = alertClass;
    alertBox.textContent = alertText;

    qualityOk = !blocking;
    saveFacePhotoBtn.disabled = !qualityOk;
}

async function saveFacePhoto() {
    if (!qualityOk) return;

    saveFacePhotoBtn.disabled = true;
    stopQualityMonitor();
    submitOverlay.classList.remove('hidden');

    try {
        const response = await fetch(facePhotoStoreEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                photo_base64: capturePhotoBase64(),
            }),
        });

        const body = await response.json();

        if (!response.ok || !body.success) {
            throw new Error(body.message || 'Foto wajah gagal disimpan.');
        }

        setAlert(body.message, 'success');
        window.setTimeout(() => window.location.href = @json(route('employee.dashboard')), 700);
    } catch (error) {
        setAlert(error.message, 'error');
        submitOverlay.classList.add('hidden');
        startQualityMonitor();
    }
}

function capturePhotoBase64() {
    const width = cameraPreview.videoWidth || 640;
    const height = cameraPreview.videoHeight || 480;

    photoCanvas.width = width;
    photoCanvas.height = height;

    const context = photoCanvas.getContext('2d');
    context.translate(width, 0);
    context.scale(-1, 1);
    context.drawImage(cameraPreview, 0, 0, width, height);

    return photoCanvas.toDataURL('image/jpeg', 0.88).split(',')[1];
}

function setAlert(message, type) {
    const color = type === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-red-200 bg-red-50 text-red-800';

    alertBox.className = 'rounded-lg border px-4 py-3 text-[13px] ' + color;
    alertBox.textContent = message;
}

window.addEventListener('beforeunload', () => {
    if (!cameraStream) return;
    cameraStream.getTracks().forEach(track => track.stop());
});
</script>
@endpush
