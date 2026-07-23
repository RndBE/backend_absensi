<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/title.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/title.ico') }}">
    <title>@yield('title', 'Dashboard') — Absensi Beacon</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .material-symbols-outlined { font-size: 20px; }
        /* Sidebar */
        #sidebar { width: 260px; transition: transform 0.3s cubic-bezier(.4,0,.2,1), width 0.3s; }
        #sidebar.collapsed { transform: translateX(-260px); }
        #main-content { margin-left: 260px; transition: margin-left 0.3s cubic-bezier(.4,0,.2,1); }
        #main-content.expanded { margin-left: 0; }
        #top-header { left: 260px; transition: left 0.3s cubic-bezier(.4,0,.2,1); }
        #top-header.expanded { left: 0; }
        /* Overlay for mobile */
        #sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 45; opacity: 0; transition: opacity 0.3s; }
        #sidebar-overlay.show { display: block; opacity: 1; }
        /* Accordion */
        .nav-group-items { max-height: 0; overflow: hidden; transition: max-height 0.3s cubic-bezier(.4,0,.2,1); }
        .nav-group-items.open { max-height: 600px; }
        .nav-group-toggle .acc-arrow { transition: transform 0.25s; }
        .nav-group-toggle.open .acc-arrow { transform: rotate(180deg); }
        /* Scrollbar */
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }
        @media (max-width: 1023px) {
            #sidebar { position: fixed; z-index: 50; transform: translateX(-260px); }
            #sidebar.mobile-open { transform: translateX(0); }
            #main-content { margin-left: 0 !important; }
            #top-header { left: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased min-h-screen text-sm">
    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen sidebar-gradient z-50 flex flex-col overflow-hidden">
        <!-- Brand -->
        <div class="flex items-center gap-3 px-5 py-4 border-b border-white/[0.08] shrink-0">
            <div class="w-9 h-9 bg-gradient-to-br from-cyan-400 to-indigo-400 rounded-lg flex items-center justify-center text-white text-lg font-extrabold shrink-0">AB</div>
            <div class="flex-1 min-w-0">
                <div class="text-white text-[15px] font-bold tracking-tight">Absensi Beacon</div>
                <div class="text-white/50 text-[11px]">Admin Dashboard</div>
            </div>
            <button onclick="toggleSidebar()" class="lg:hidden w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/10 text-white/60 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <!-- Nav -->
        <nav class="flex-1 px-2.5 py-3 overflow-y-auto sidebar-scroll">
            @php
                $adminPermission = app(\App\Support\AdminPermission::class);
                $navGroups = [
                    ['label' => 'Menu Utama', 'icon' => 'space_dashboard', 'key' => 'main', 'items' => [
                        ['route' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'match' => 'admin.dashboard'],
                        ['route' => 'admin.employees.index', 'icon' => 'group', 'label' => 'Karyawan', 'match' => 'admin.employees.*'],
                        ['route' => 'admin.departments.index', 'icon' => 'apartment', 'label' => 'Departemen', 'match' => 'admin.departments.*'],
                    ]],
                    ['label' => 'Presensi', 'icon' => 'fingerprint', 'key' => 'attendance', 'items' => [
                        ['route' => 'admin.attendance.realtime', 'icon' => 'location_on', 'label' => 'Realtime Hari Ini', 'match' => 'admin.attendance.realtime'],
                        ['route' => 'admin.attendance.history', 'icon' => 'history', 'label' => 'Riwayat Absensi', 'match' => 'admin.attendance.history'],
                        ['route' => 'admin.attendance-recap.index', 'icon' => 'summarize', 'label' => 'Rekap Presensi', 'match' => 'admin.attendance-recap.*'],
                        ['route' => 'admin.attendance-photo-archives.index', 'icon' => 'archive', 'label' => 'Arsip Foto Absensi', 'match' => 'admin.attendance-photo-archives.*'],
                    ]],
                    ['label' => 'Cuti', 'icon' => 'event_busy', 'key' => 'leave', 'items' => [
                        ['route' => 'admin.leaves.index', 'icon' => 'event_busy', 'label' => 'Pengajuan Cuti', 'match' => 'admin.leaves.*'],
                        ['route' => 'admin.leave-types.index', 'icon' => 'category', 'label' => 'Tipe Cuti', 'match' => 'admin.leave-types.*'],
                        ['route' => 'admin.leave-policies.index', 'icon' => 'tune', 'label' => 'Kebijakan Cuti', 'match' => 'admin.leave-policies.*'],
                        ['route' => 'admin.leave-balances.index', 'icon' => 'account_balance_wallet', 'label' => 'Saldo Cuti', 'match' => 'admin.leave-balances.*'],
                    ]],
                    ['label' => 'Anggaran', 'icon' => 'request_quote', 'key' => 'budget', 'items' => [
                        ['route' => 'admin.budget-requests.index', 'icon' => 'request_quote', 'label' => 'Pengajuan Anggaran', 'match' => 'admin.budget-requests.*'],
                        ['route' => 'admin.travel-reports.index', 'icon' => 'flight_takeoff', 'label' => 'LHP', 'match' => 'admin.travel-reports.*'],
                        ['route' => 'admin.lpj.index', 'icon' => 'receipt_long', 'label' => 'LPJ', 'match' => 'admin.lpj.*'],
                        ['route' => 'admin.policies.index', 'icon' => 'policy', 'label' => 'Kebijakan', 'match' => 'admin.policies.*'],
                        ['route' => 'admin.travel-zones.index', 'icon' => 'map', 'label' => 'Zona Perjalanan', 'match' => 'admin.travel-zones.*'],
                    ]],
                    ['label' => 'Jadwal Kerja', 'icon' => 'calendar_month', 'key' => 'schedule', 'items' => [
                        ['route' => 'admin.schedules.index', 'icon' => 'calendar_month', 'label' => 'Jadwal Kerja', 'match' => 'admin.schedules.*'],
                        ['route' => 'admin.holidays.index', 'icon' => 'celebration', 'label' => 'Hari Libur', 'match' => 'admin.holidays.*'],
                    ]],
                    ['label' => 'Payroll', 'icon' => 'payments', 'key' => 'payroll', 'items' => [
                        ['route' => 'admin.payroll-components.index', 'icon' => 'list_alt', 'label' => 'Komponen Gaji', 'match' => 'admin.payroll-components.*'],
                        ['route' => 'admin.employee-payrolls.index', 'icon' => 'account_balance', 'label' => 'Master Payroll', 'match' => 'admin.employee-payrolls.*'],
                        ['route' => 'admin.loan-requests.index', 'icon' => 'account_balance_wallet', 'label' => 'Pinjaman', 'match' => 'admin.loan-requests.*'],
                        ['route' => 'admin.payroll-runs.index', 'icon' => 'payments', 'label' => 'Run Payroll', 'match' => 'admin.payroll-runs.*'],
                        ['route' => 'admin.payslips.index', 'icon' => 'receipt', 'label' => 'Payslip', 'match' => 'admin.payslips.*'],
                        ['route' => 'admin.payroll-adjustments.index', 'icon' => 'tune', 'label' => 'Adjustment', 'match' => 'admin.payroll-adjustments.*'],
                    ]],
                    ['label' => 'Pajak & BPJS', 'icon' => 'receipt_long', 'key' => 'tax', 'items' => [
                        ['route' => 'admin.tax.settings', 'icon' => 'settings', 'label' => 'Tax Settings', 'match' => 'admin.tax.settings'],
                        ['route' => 'admin.tax.simulator', 'icon' => 'calculate', 'label' => 'Kalkulator Pajak', 'match' => 'admin.tax.simulator'],
                        ['route' => 'admin.tax.bukti-potong', 'icon' => 'description', 'label' => 'Bukti Potong', 'match' => 'admin.tax.bukti-potong*'],
                    ]],
                    ['label' => 'Persetujuan', 'icon' => 'task_alt', 'key' => 'approval', 'items' => [
                        ['route' => 'admin.approvals.index', 'icon' => 'task_alt', 'label' => 'Persetujuan', 'match' => 'admin.approvals.*', 'badge' => true],
                        ['route' => 'admin.monitor-approvals.index', 'icon' => 'monitoring', 'label' => 'Monitor Approval', 'match' => 'admin.monitor-approvals.*', 'superadmin_only' => true],
                        ['route' => 'admin.approval-rules.index', 'icon' => 'settings', 'label' => 'Pengaturan Approval', 'match' => 'admin.approval-rules.*'],
                    ]],
                    ['label' => 'Laporan', 'icon' => 'analytics', 'key' => 'reports', 'items' => [
                        ['route' => 'admin.reports.index', 'icon' => 'analytics', 'label' => 'Pusat Laporan', 'match' => 'admin.reports.index'],
                        ['route' => 'admin.reports.overtime', 'icon' => 'more_time', 'label' => 'Rekap Lembur', 'match' => 'admin.reports.overtime*'],
                    ]],
                    ['label' => 'Pengaturan', 'icon' => 'settings', 'key' => 'settings', 'items' => [
                        ['route' => 'admin.company.index', 'icon' => 'domain', 'label' => 'Info Perusahaan', 'match' => 'admin.company.*'],
                        ['route' => 'admin.attendance-settings.index', 'icon' => 'tune', 'label' => 'Pengaturan Presensi', 'match' => 'admin.attendance-settings.*'],
                        ['route' => 'admin.roles.index', 'icon' => 'badge', 'label' => 'Role', 'match' => 'admin.roles.*'],
                        ['route' => 'admin.role-permissions.index', 'icon' => 'admin_panel_settings', 'label' => 'Role Permission', 'match' => 'admin.role-permissions.*'],
                        ['route' => 'admin.audit-logs.index', 'icon' => 'manage_search', 'label' => 'Audit Log', 'match' => 'admin.audit-logs.*'],
                    ]],
                ];
                $pendingCount = app(\App\Support\PendingApprovalCounter::class)->countForApprover($currentAdmin);
            @endphp

            @foreach($navGroups as $group)
                @php
                    $visibleItems = array_values(array_filter($group['items'], function ($item) use ($adminPermission, $currentAdmin) {
                        if (!empty($item['superadmin_only']) && $currentAdmin?->role !== 'superadmin') {
                            return false;
                        }
                        $permission = $adminPermission->permissionForRoute($item['route']);
                        return !$permission || ($currentAdmin && $adminPermission->can($currentAdmin, $permission));
                    }));
                    $isGroupActive = false;
                    foreach($visibleItems as $item) {
                        if(request()->routeIs($item['match'])) { $isGroupActive = true; break; }
                    }
                @endphp
                @if(empty($visibleItems))
                    @continue
                @endif
                <div class="mb-1">
                    <button onclick="toggleAccordion('nav-{{ $group['key'] }}')" class="nav-group-toggle {{ $isGroupActive ? 'open' : '' }} w-full flex items-center gap-3 px-3.5 py-2 rounded-lg text-[11px] font-bold uppercase tracking-[1.5px] transition-all duration-200 cursor-pointer {{ $isGroupActive ? 'text-white/80 bg-white/[0.05]' : 'text-white/35 hover:text-white/50 hover:bg-white/[0.03]' }}" id="toggle-{{ $group['key'] }}">
                        <span class="material-symbols-outlined text-[16px]">{{ $group['icon'] }}</span>
                        <span class="flex-1 text-left">{{ $group['label'] }}</span>
                        @if($group['key'] === 'approval' && $pendingCount > 0)
                            <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">{{ $pendingCount }}</span>
                        @endif
                        <span class="acc-arrow material-symbols-outlined text-[14px]">expand_more</span>
                    </button>
                    <div class="nav-group-items {{ $isGroupActive ? 'open' : '' }}" id="nav-{{ $group['key'] }}">
                        @foreach($visibleItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 pl-7 pr-3.5 py-2 rounded-lg text-[13px] font-medium transition-all duration-200 mb-0.5 relative
                                      {{ request()->routeIs($item['match']) ? 'bg-white/12 text-white before:nav-active-bar' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                                <span class="material-symbols-outlined w-5 h-5 flex items-center justify-center shrink-0 text-[18px]">{{ $item['icon'] }}</span>
                                {{ $item['label'] }}
                                @if(!empty($item['badge']) && $pendingCount > 0)
                                    <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">{{ $pendingCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <!-- Footer -->
        <div class="px-3.5 py-3 border-t border-white/[0.08] shrink-0">
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
    <header id="top-header" class="fixed top-0 right-0 h-14 bg-white/90 backdrop-blur-sm border-b border-gray-200 flex items-center justify-between px-5 lg:px-7 z-40">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors cursor-pointer" id="sidebar-toggle-btn">
                <span class="material-symbols-outlined text-[22px]">menu</span>
            </button>
            <h1 class="text-lg font-bold text-gray-900 tracking-tight hidden sm:block">@yield('title', 'Dashboard')</h1>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[13px] text-gray-500 hidden md:inline">{{ now()->translatedFormat('l, d F Y') }}</span>
            <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 cursor-pointer">Logout</button>
            </form>
        </div>
    </header>

    <!-- Main Content -->
    <main id="main-content" class="mt-14 p-5 lg:p-7 min-h-[calc(100vh-56px)]">
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

    <div id="confirmActionModal" class="hidden fixed inset-0 z-[80] items-center justify-center px-4" style="z-index: 1000;">
        <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" data-confirm-cancel></div>
        <div class="relative w-full max-w-md rounded-xl bg-white shadow-2xl border border-gray-200 overflow-hidden">
            <div class="p-5">
                <div class="flex items-start gap-3">
                    <div id="confirmActionIconWrap" class="w-10 h-10 rounded-lg bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                        <span id="confirmActionIcon" class="material-symbols-outlined text-[22px]">warning</span>
                    </div>
                    <div class="min-w-0">
                        <h3 id="confirmActionTitle" class="text-[15px] font-bold text-gray-900">Konfirmasi Aksi</h3>
                        <p id="confirmActionMessage" class="mt-1 text-[13px] leading-5 text-gray-600">Lanjutkan aksi ini?</p>
                    </div>
                </div>
            </div>
            <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" data-confirm-cancel class="px-4 py-2 text-[12px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer">Batal</button>
                <button type="button" id="confirmActionButton" class="px-4 py-2 text-[12px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition cursor-pointer">Lanjutkan</button>
            </div>
        </div>
    </div>

    <script>
    let confirmModalCallback = null;

    function openConfirmModal(options = {}) {
        const modal = document.getElementById('confirmActionModal');
        const title = document.getElementById('confirmActionTitle');
        const message = document.getElementById('confirmActionMessage');
        const button = document.getElementById('confirmActionButton');
        const icon = document.getElementById('confirmActionIcon');
        const iconWrap = document.getElementById('confirmActionIconWrap');
        const variant = options.variant || 'danger';

        title.textContent = options.title || 'Konfirmasi Aksi';
        message.textContent = options.message || 'Lanjutkan aksi ini?';
        button.textContent = options.confirmText || 'Lanjutkan';
        button.className = 'px-4 py-2 text-[12px] font-semibold text-white rounded-lg transition cursor-pointer ' +
            (variant === 'primary' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-red-600 hover:bg-red-700');
        icon.textContent = variant === 'primary' ? 'help' : 'warning';
        iconWrap.className = 'w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ' +
            (variant === 'primary' ? 'bg-indigo-50 text-indigo-600' : 'bg-red-50 text-red-600');

        confirmModalCallback = typeof options.onConfirm === 'function' ? options.onConfirm : null;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        button.focus();
    }

    function closeConfirmModal() {
        const modal = document.getElementById('confirmActionModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        confirmModalCallback = null;
    }

    function runConfirmBeforeAction(code) {
        if (!code) return true;
        try {
            Function(code)();
            return true;
        } catch (error) {
            console.error('Gagal menjalankan aksi sebelum submit:', error);
            return false;
        }
    }

    function submitConfirmedForm(form, submitter = null) {
        form.dataset.confirmed = '1';
        const validSubmitter = submitter
            && submitter.form === form
            && submitter.matches('button, input[type="submit"], input[type="image"]')
            ? submitter
            : null;

        if (form.requestSubmit) {
            if (validSubmitter) {
                form.requestSubmit(validSubmitter);
            } else {
                form.requestSubmit();
            }
        } else {
            form.submit();
        }
        setTimeout(() => delete form.dataset.confirmed, 500);
    }

    function resolveConfirmMessage(element) {
        if (element.dataset.confirmMessageFn && typeof window[element.dataset.confirmMessageFn] === 'function') {
            return window[element.dataset.confirmMessageFn](element);
        }

        return element.dataset.confirm || 'Lanjutkan aksi ini?';
    }

    document.addEventListener('click', function(event) {
        if (event.target.closest('[data-confirm-cancel]')) {
            closeConfirmModal();
            return;
        }

        if (event.target.closest('#confirmActionButton')) {
            const callback = confirmModalCallback;
            closeConfirmModal();
            if (callback) callback();
            return;
        }

        const trigger = event.target.closest('[data-confirm]');
        if (!trigger) return;
        if (trigger.matches('form')) return;

        const form = trigger.form || trigger.closest('form');
        if (!form) return;

        event.preventDefault();
        event.stopPropagation();

        const message = resolveConfirmMessage(trigger);
        if (message === false) return;

        openConfirmModal({
            message,
            confirmText: trigger.dataset.confirmText || 'Lanjutkan',
            variant: trigger.dataset.confirmVariant || 'danger',
            onConfirm: () => {
                if (!runConfirmBeforeAction(trigger.dataset.confirmBefore)) return;
                submitConfirmedForm(form, trigger);
            },
        });
    }, true);

    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (!form.matches('[data-confirm], [data-confirm-message-fn]') || form.dataset.confirmed === '1') return;

        event.preventDefault();
        event.stopPropagation();

        const message = resolveConfirmMessage(form);
        if (message === false) return;

        openConfirmModal({
            message,
            confirmText: form.dataset.confirmText || 'Lanjutkan',
            variant: form.dataset.confirmVariant || 'danger',
            onConfirm: () => {
                if (!runConfirmBeforeAction(form.dataset.confirmBefore)) return;
                submitConfirmedForm(form);
            },
        });
    }, true);

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !document.getElementById('confirmActionModal').classList.contains('hidden')) {
            closeConfirmModal();
        }
    });

    // Sidebar toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main-content');
        const header = document.getElementById('top-header');
        const overlay = document.getElementById('sidebar-overlay');
        const isMobile = window.innerWidth < 1024;

        if (isMobile) {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
        } else {
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
            header.classList.toggle('expanded');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
        }
    }

    // Accordion toggle
    function toggleAccordion(id) {
        const items = document.getElementById(id);
        const toggle = document.getElementById('toggle-' + id.replace('nav-', ''));
        items.classList.toggle('open');
        toggle.classList.toggle('open');
        // Save state
        const states = JSON.parse(localStorage.getItem('nav-accordion') || '{}');
        states[id] = items.classList.contains('open') ? '1' : '0';
        localStorage.setItem('nav-accordion', JSON.stringify(states));
    }

    // Restore states on load
    document.addEventListener('DOMContentLoaded', function() {
        // Restore sidebar state (desktop only)
        if (window.innerWidth >= 1024 && localStorage.getItem('sidebar-collapsed') === '1') {
            document.getElementById('sidebar').classList.add('collapsed');
            document.getElementById('main-content').classList.add('expanded');
            document.getElementById('top-header').classList.add('expanded');
        }

        // Restore accordion states (only for non-active groups)
        const states = JSON.parse(localStorage.getItem('nav-accordion') || '{}');
        Object.keys(states).forEach(function(id) {
            const items = document.getElementById(id);
            const toggle = document.getElementById('toggle-' + id.replace('nav-', ''));
            if (items && toggle) {
                // Don't override active group
                if (items.classList.contains('open') && !toggle.classList.contains('open')) {
                    // Active group, keep open
                } else if (states[id] === '0') {
                    items.classList.remove('open');
                    toggle.classList.remove('open');
                } else if (states[id] === '1') {
                    items.classList.add('open');
                    toggle.classList.add('open');
                }
            }
        });

        // Close sidebar on mobile when clicking a link
        document.querySelectorAll('#sidebar nav a').forEach(function(a) {
            a.addEventListener('click', function() {
                if (window.innerWidth < 1024) {
                    document.getElementById('sidebar').classList.remove('mobile-open');
                    document.getElementById('sidebar-overlay').classList.remove('show');
                }
            });
        });

        // Responsive: handle resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
            }
        });
    });
    </script>

    @include('admin.partials.searchable-select')
    @include('admin.partials.currency-format')
@stack('scripts')
</body>
</html>
