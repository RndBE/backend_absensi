<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/title.ico') }}">
    <title>Tautan Tidak Berlaku — Tinjauan Rantai Kerja</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-5">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 w-full max-w-[440px] text-center">
            <span class="material-symbols-outlined text-[40px] text-amber-500">link_off</span>
            <h1 class="text-[17px] font-bold text-gray-900 mt-2">Tautan tidak berlaku</h1>
            <p class="text-[13px] text-gray-500 mt-2">{{ $reason }}</p>
            <p class="text-[12px] text-gray-400 mt-5">
                Hubungi HRD atau tim Software untuk minta tautan baru. Tautan tinjauan berlaku
                terbatas dan hanya berisi peta rantai kerja — bukan data gaji atau absensi.
            </p>
        </div>
    </div>
</body>
</html>
