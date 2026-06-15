<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/title.ico') }}">
    <title>@yield('title', 'Employee Portal') - HRIS Beacon</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .material-symbols-outlined { font-size: 20px; }
    </style>
    @stack('head')
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased min-h-screen text-sm">
    <header class="sticky top-0 z-40 h-14 bg-white/95 backdrop-blur-sm border-b border-gray-200">
        <div class="max-w-5xl mx-auto h-full px-4 sm:px-5 flex items-center justify-between gap-3">
            <a href="{{ route('employee.dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 bg-gradient-to-br from-indigo-600 to-cyan-500 rounded-lg flex items-center justify-center text-white text-[14px] font-extrabold shrink-0">AB</div>
                <div class="min-w-0">
                    <div class="text-[14px] font-extrabold text-gray-900 leading-tight truncate">HRIS Beacon</div>
                    <div class="text-[11px] text-gray-400 leading-tight">Employee Portal</div>
                </div>
            </a>

            <div class="flex items-center gap-2">
                <div class="hidden sm:block text-right">
                    <div class="text-[12px] font-bold text-gray-800 leading-tight">{{ $currentEmployee->full_name ?? 'Employee' }}</div>
                    <div class="text-[11px] text-gray-400 leading-tight">{{ $currentEmployee->position ?? 'Karyawan' }}</div>
                </div>
                <form action="{{ route('employee.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">logout</span>
                        <span class="hidden sm:inline">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-5 py-5 sm:py-7">
        @if(session('success'))
            <div class="flex items-center gap-2.5 px-4 py-3.5 rounded-lg text-[13.5px] font-medium mb-4 bg-emerald-50 text-emerald-800 border border-emerald-200 animate-slide-down">
                <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="flex items-center gap-2.5 px-4 py-3.5 rounded-lg text-[13.5px] font-medium mb-4 bg-red-50 text-red-800 border border-red-200 animate-slide-down">
                <span class="material-symbols-outlined text-[18px]">error</span> {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
