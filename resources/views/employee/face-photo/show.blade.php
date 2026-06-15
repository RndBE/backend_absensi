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

        <div class="relative bg-slate-950">
            <video id="cameraPreview" autoplay playsinline muted class="w-full h-[420px] object-cover bg-slate-950" style="transform: scaleX(-1);"></video>
            <canvas id="photoCanvas" class="hidden"></canvas>
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                <div class="w-[58%] max-w-[280px] aspect-[3/4] border-2 border-white rounded-2xl shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
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
        saveFacePhotoBtn.disabled = false;
        setAlert('Kamera siap.', 'success');
    } catch (error) {
        saveFacePhotoBtn.disabled = true;
        setAlert('Gagal membuka kamera. Izinkan akses kamera lalu coba lagi.', 'error');
    }
}

async function saveFacePhoto() {
    saveFacePhotoBtn.disabled = true;
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
        saveFacePhotoBtn.disabled = false;
        submitOverlay.classList.add('hidden');
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
