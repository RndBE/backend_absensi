<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Absensi Beacon</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>.material-symbols-outlined { font-size: 20px; }</style>
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased min-h-screen text-sm">
    <!-- Sidebar -->
    <aside class="fixed top-0 left-0 w-[260px] h-screen sidebar-gradient z-50 flex flex-col transition-all duration-300 overflow-hidden">
        <!-- Brand -->
        <div class="flex items-center gap-3 px-5 py-5 border-b border-white/[0.08]">
            <div class="w-9 h-9 bg-gradient-to-br from-cyan-400 to-indigo-400 rounded-lg flex items-center justify-center text-white text-lg font-extrabold shrink-0">AB</div>
            <div>
                <div class="text-white text-[15px] font-bold tracking-tight">Absensi Beacon</div>
                <div class="text-white/50 text-[11px]">Admin Dashboard</div>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-2.5 py-3 overflow-y-auto sidebar-scroll">
            <div class="mb-5">
                <div class="text-white/35 text-[10px] font-bold uppercase tracking-widest px-3.5 mb-1.5">Menu Utama</div>
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.dashboard') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">dashboard</span>
                    Dashboard
                </a>
                <a href="{{ route('admin.employees.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.employees.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">group</span>
                    Karyawan
                </a>
                <a href="{{ route('admin.departments.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.departments.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">apartment</span>
                    Departemen
                </a>
            </div>

            <div class="mb-5">
                <div class="text-white/35 text-[10px] font-bold uppercase tracking-widest px-3.5 mb-1.5">Presensi</div>
                <a href="{{ route('admin.attendance.realtime') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.attendance.realtime') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">location_on</span>
                    Realtime Hari Ini
                </a>
                <a href="{{ route('admin.attendance.history') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.attendance.history') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">history</span>
                    Riwayat Absensi
                </a>
                <a href="{{ route('admin.attendance-recap.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.attendance-recap.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">summarize</span>
                    Rekap Presensi
                </a>
                <a href="{{ route('admin.leaves.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.leaves.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">event_busy</span>
                    Pengajuan Cuti
                </a>
                <a href="{{ route('admin.leave-policies.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.leave-policies.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">tune</span>
                    Kebijakan Cuti
                </a>
                <a href="{{ route('admin.leave-balances.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.leave-balances.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">account_balance_wallet</span>
                    Saldo Cuti
                </a>
                <a href="{{ route('admin.schedules.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.schedules.*') || request()->routeIs('admin.shifts.*') || request()->routeIs('admin.schedule-templates.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">calendar_month</span>
                    Jadwal Kerja
                </a>
                <a href="{{ route('admin.holidays.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.holidays.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">event_busy</span>
                    Hari Libur
                </a>
            </div>

            <div class="mb-5">
                <div class="text-white/35 text-[10px] font-bold uppercase tracking-widest px-3.5 mb-1.5">Persetujuan</div>
                <a href="{{ route('admin.approvals.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.approvals.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">task_alt</span>
                    Persetujuan
                    @php
                        $pendingCount = \App\Models\LeaveRequest::whereIn('status',['pending','in_review'])->count()
                            + \App\Models\OvertimeRequest::whereIn('status',['pending','in_review'])->count()
                            + \App\Models\AttendanceRequest::whereIn('status',['pending','in_review'])->count();
                    @endphp
                    @if($pendingCount > 0)
                        <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.approval-rules.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.approval-rules.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">settings</span>
                    Aturan Approval
                </a>
                <a href="{{ route('admin.attendance-settings.index') }}"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition-all duration-200 mb-0.5 relative
                          {{ request()->routeIs('admin.attendance-settings.*') ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/65 hover:bg-white/[0.08] hover:text-white' }}">
                    <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0">tune</span>
                    Pengaturan Presensi
                </a>
            </div>
        </nav>

        <!-- Footer -->
        <div class="px-3.5 py-3.5 border-t border-white/[0.08]">
            <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white/[0.08] transition-all duration-200">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-cyan-400 flex items-center justify-center text-white font-bold text-[13px] shrink-0">
                    {{ substr($currentAdmin->full_name ?? 'A', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white text-[13px] font-semibold truncate">{{ $currentAdmin->full_name ?? 'Admin' }}</div>
                    <div class="text-white/45 text-[11px]">{{ ucfirst($currentAdmin->role ?? 'admin') }}</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Header -->
    <header class="fixed top-0 left-[260px] right-0 h-16 bg-white/90 backdrop-blur-sm border-b border-gray-200 flex items-center justify-between px-7 z-40">
        <h1 class="text-lg font-bold text-gray-900 tracking-tight">@yield('title', 'Dashboard')</h1>
        <div class="flex items-center gap-3">
            <span class="text-[13px] text-gray-500">{{ now()->translatedFormat('l, d F Y') }}</span>
            <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 cursor-pointer">Logout</button>
            </form>
        </div>
    </header>

    <!-- Main Content -->
    <main class="ml-[260px] mt-16 p-7 min-h-[calc(100vh-64px)]">
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
</body>
</html>
