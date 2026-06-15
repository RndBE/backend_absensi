@extends('employee.layouts.app')
@section('title', 'Bantuan Presensi')

@section('content')
<div class="space-y-5">
    <section class="rounded-xl bg-white border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 sm:p-6">
            <a href="{{ route('employee.dashboard') }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-gray-500 hover:text-gray-800 mb-3">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Dashboard
            </a>
            <div class="flex items-start gap-3">
                <div class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">support_agent</span>
                </div>
                <div>
                    <h1 class="text-[22px] sm:text-[26px] font-black text-gray-900 tracking-tight">Bantuan Presensi</h1>
                    <p class="text-[13px] text-gray-500 mt-1">Panduan cepat saat lokasi, kamera, atau GPS belum terbaca di browser.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">location_on</span>
            </div>
            <h2 class="mt-3 text-[15px] font-black text-gray-900">Izin Lokasi</h2>
            <ul class="mt-3 space-y-2 text-[12.5px] text-gray-600 leading-relaxed">
                <li>Android Chrome: buka ikon gembok di address bar, pilih Permissions, lalu izinkan Location.</li>
                <li>iPhone Chrome/Safari: buka Settings, pilih browser, lalu aktifkan Location saat digunakan.</li>
                <li>Kalau prompt tidak muncul, hapus izin situs lalu muat ulang halaman presensi.</li>
            </ul>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">photo_camera</span>
            </div>
            <h2 class="mt-3 text-[15px] font-black text-gray-900">Kamera</h2>
            <ul class="mt-3 space-y-2 text-[12.5px] text-gray-600 leading-relaxed">
                <li>Pastikan izin Camera untuk browser aktif.</li>
                <li>Tutup aplikasi lain yang sedang memakai kamera.</li>
                <li>Pakai kamera depan, hasil foto yang dikirim tetap dibuat tidak mirror.</li>
            </ul>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">gps_fixed</span>
            </div>
            <h2 class="mt-3 text-[15px] font-black text-gray-900">GPS</h2>
            <ul class="mt-3 space-y-2 text-[12.5px] text-gray-600 leading-relaxed">
                <li>Aktifkan GPS perangkat dan mode akurasi tinggi jika tersedia.</li>
                <li>Buka halaman dari koneksi HTTPS. Untuk local, gunakan ngrok ke port Laravel.</li>
                <li>Jika akurasi masih besar, tunggu beberapa detik lalu tekan coba ambil lokasi lagi.</li>
            </ul>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-[15px] font-black text-gray-900 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-blue-600">terminal</span>
            Akses Local untuk Test
        </h2>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="text-[12px] font-bold text-gray-500">Laravel</div>
                <code class="mt-2 block text-[12px] text-gray-800 break-all">php artisan serve --host=0.0.0.0 --port=8000</code>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="text-[12px] font-bold text-gray-500">ngrok</div>
                <code class="mt-2 block text-[12px] text-gray-800 break-all">ngrok http 8000</code>
            </div>
        </div>
    </section>
</div>
@endsection
