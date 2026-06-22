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
        .employee-native-field {
            -webkit-appearance: none;
            appearance: none;
            background-color: #fff;
            color: #111827;
            color-scheme: light;
        }
        /* iOS WebKit: date input mengkerut tanpa tinggi eksplisit saat appearance:none. */
        input[type="date"].employee-native-field,
        input[type="time"].employee-native-field {
            min-height: 2.5rem;
        }
        .employee-native-field::-webkit-date-and-time-value {
            color: #111827;
            text-align: left;
            min-height: 1.25rem;
            line-height: 1.25rem;
        }
        .employee-native-field::-webkit-calendar-picker-indicator {
            opacity: .75;
        }
        select.employee-native-field {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right .625rem center;
            background-size: 1rem;
            padding-right: 2.25rem;
        }
        .employee-date-shell {
            position: relative;
            display: block;
        }
        .employee-date-shell .employee-date-placeholder {
            position: absolute;
            left: .75rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #111827;
            font-size: 13px;
            line-height: 1;
        }
        .employee-date-shell:not(.employee-date-shell-has-value):not(:focus-within) input[type="date"]::-webkit-date-and-time-value,
        .employee-date-shell:not(.employee-date-shell-has-value):not(:focus-within) input[type="date"]::-webkit-datetime-edit {
            color: transparent;
        }
        .employee-date-shell.employee-date-shell-has-value .employee-date-placeholder,
        .employee-date-shell:focus-within .employee-date-placeholder {
            display: none;
        }
    </style>
    @stack('head')
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased min-h-screen text-sm">
    <header class="sticky top-0 z-40 h-14 bg-white/95 backdrop-blur-sm border-b border-gray-200">
        <div class="max-w-5xl mx-auto h-full px-4 sm:px-5 flex items-center justify-between gap-3">
            <a href="{{ route('employee.dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0 overflow-hidden">
                    <img src="{{ asset('images/logo_be2.png') }}" alt="HRIS Beacon" class="w-full h-full object-contain">
                </div>
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
                <a href="{{ route('employee.profile.show') }}" title="Profil Saya" class="inline-flex items-center justify-center w-9 h-9 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    <span class="material-symbols-outlined text-[17px]">person</span>
                </a>
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
        <div id="employeePortalFlash" class="hidden mb-4"></div>

        @yield('content')
    </main>

    <script>
        (function () {
            function syncDateShell(shell) {
                const input = shell.querySelector('input[type="date"]');
                shell.classList.toggle('employee-date-shell-has-value', Boolean(input?.value));
            }

            document.querySelectorAll('[data-employee-date-shell]').forEach(syncDateShell);
            document.addEventListener('input', (event) => {
                const shell = event.target.closest?.('[data-employee-date-shell]');
                if (shell) syncDateShell(shell);
            });
            document.addEventListener('change', (event) => {
                const shell = event.target.closest?.('[data-employee-date-shell]');
                if (shell) syncDateShell(shell);
            });

            const flash = document.getElementById('employeePortalFlash');
            if (!flash) return;

            const rawMessage = sessionStorage.getItem('employee-attendance-alert');
            if (!rawMessage) return;

            sessionStorage.removeItem('employee-attendance-alert');

            let payload = { type: 'success', message: rawMessage };
            try {
                payload = JSON.parse(rawMessage);
            } catch (error) {
                payload = { type: 'success', message: rawMessage };
            }

            const isError = payload.type === 'error';
            flash.className = [
                'flex items-center gap-2.5 px-4 py-3.5 rounded-lg text-[13.5px] font-medium mb-4 animate-slide-down',
                isError ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200',
            ].join(' ');
            flash.replaceChildren();

            const icon = document.createElement('span');
            icon.className = 'material-symbols-outlined text-[18px]';
            icon.textContent = isError ? 'error' : 'check_circle';

            const message = document.createElement('span');
            message.textContent = payload.message || 'Presensi berhasil diproses.';

            flash.append(icon, message);

            window.setTimeout(() => {
                flash.classList.add('hidden');
            }, 5000);
        })();
    </script>
    @stack('scripts')
</body>
</html>
