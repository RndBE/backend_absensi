<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Absensi Beacon Admin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center login-bg p-5 relative overflow-hidden">
        <!-- Glow effects -->
        <div class="absolute w-[600px] h-[600px] rounded-full -top-[200px] -right-[100px] bg-[radial-gradient(circle,rgba(6,182,212,0.15),transparent)]"></div>
        <div class="absolute w-[400px] h-[400px] rounded-full -bottom-[150px] -left-[50px] bg-[radial-gradient(circle,rgba(139,92,246,0.12),transparent)]"></div>

        <!-- Login Card -->
        <div class="bg-white/95 backdrop-blur-xl rounded-2xl p-12 w-full max-w-[420px] shadow-2xl relative z-10">
            <div class="text-center mb-8">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-600 to-cyan-500 rounded-xl inline-flex items-center justify-center text-2xl text-white font-extrabold mb-3.5">AB</div>
                <h1 class="text-[22px] font-extrabold text-gray-900 tracking-tight">Absensi Beacon</h1>
                <p class="text-gray-500 text-[13.5px] mt-1">Admin Dashboard Login</p>
            </div>

            @if(session('error'))
                <div class="flex items-center gap-2.5 px-4 py-3 rounded-lg text-[13.5px] font-medium mb-5 bg-red-50 text-red-800 border border-red-200 animate-slide-down">
                    <span class="material-symbols-outlined text-[18px]">error</span> {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('admin.login') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm text-gray-800 bg-white outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400"
                           placeholder="admin@company.com" value="{{ old('email') }}" required autofocus>
                    @error('email')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Password</label>
                    <input type="password" name="password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm text-gray-800 bg-white outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-[3px] focus:ring-indigo-500/10 placeholder:text-gray-400"
                           placeholder="••••••••" required>
                    @error('password')<div class="text-red-500 text-xs mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="mt-6">
                    <button type="submit"
                            class="w-full py-3.5 bg-gradient-to-br from-indigo-600 to-indigo-400 text-white text-[15px] font-semibold rounded-lg shadow-[0_2px_8px_rgba(79,70,229,0.3)] hover:shadow-[0_4px_12px_rgba(79,70,229,0.4)] hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 cursor-pointer">
                        Masuk ke Dashboard
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
