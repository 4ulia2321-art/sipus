<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIPUS | Sistem Informasi Perpustakaan Digital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN Fallback for instant standalone styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef6ff',
                            100: '#d9ebfe',
                            200: '#bcdcfd',
                            300: '#8ec5fc',
                            400: '#58a5f8',
                            500: '#3a7ad4',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#173663',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-[#ebf3fc] relative flex items-center justify-center p-4 sm:p-6 lg:p-8 overflow-x-hidden selection:bg-brand-500 selection:text-white">

    <!-- Decorative background elements -->
    <div class="fixed top-0 left-0 w-96 h-96 -translate-x-32 -translate-y-32 pointer-events-none opacity-60 z-0">
        <svg viewBox="0 0 400 400" fill="none" class="w-full h-full text-blue-200" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,0 C150,0 350,100 300,280 C260,390 100,380 0,350 Z" fill="currentColor" fill-opacity="0.35"/>
        </svg>
    </div>

    <div class="fixed bottom-0 right-0 w-[480px] h-[480px] translate-x-36 translate-y-36 pointer-events-none opacity-60 z-0">
        <svg viewBox="0 0 500 500" fill="none" class="w-full h-full text-blue-200" xmlns="http://www.w3.org/2000/svg">
            <path d="M500,500 C300,500 120,380 180,220 C240,60 420,100 500,0 Z" fill="currentColor" fill-opacity="0.35"/>
        </svg>
    </div>

    <!-- Main Container Card -->
    <div class="relative z-10 w-full max-w-[1020px] bg-white/70 backdrop-blur-xl rounded-[28px] sm:rounded-[36px] shadow-[0_20px_50px_rgba(28,68,124,0.08)] border border-white/90 p-3 sm:p-5 md:p-6 lg:p-7">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-stretch">

            <!-- LEFT COLUMN: Illustration & School Identity -->
            <div class="lg:col-span-6 bg-gradient-to-b from-[#eaf2fc] via-[#e2edfa] to-[#d8e6f7] rounded-[24px] sm:rounded-[28px] p-6 sm:p-8 flex flex-col justify-between relative overflow-hidden border border-blue-100/60 shadow-inner">

                <!-- Background soft glow shapes in left card -->
                <div class="absolute -top-16 -right-16 w-48 h-48 bg-blue-100/50 rounded-full blur-2xl pointer-events-none"></div>
                <div class="absolute -bottom-16 -left-16 w-52 h-52 bg-white/40 rounded-full blur-2xl pointer-events-none"></div>

                <!-- Top Header: SIPUS & School Name -->
                <div class="relative z-10 flex items-center gap-3.5">
                    <!-- Book Logo Icon -->
                    <svg class="w-9 h-9 text-[#3a7ad4] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                    <div>
                        <h1 class="text-xl font-extrabold text-[#17325c] tracking-tight leading-tight">SIPUS</h1>
                        <p class="text-[12px] sm:text-[13px] text-[#556d8f] font-medium leading-snug">Sistem Informasi Perpustakaan</p>
                        <p class="text-[11px] sm:text-[12px] text-[#556d8f] font-normal leading-snug">SMP Negeri 01 Pasirjambu</p>
                    </div>
                </div>

                <!-- Center Illustration -->
                <div class="relative z-10 my-4 sm:my-6 flex flex-col items-center justify-center">
                    <div class="relative max-w-[340px] w-full flex items-center justify-center">
                        <img
                            src="{{ asset('images/illustration-library.png') }}"
                            alt="Ilustrasi Membaca Buku Perpustakaan"
                            class="w-full h-auto object-contain max-h-[300px] drop-shadow-sm transition-transform duration-500 hover:scale-[1.02]"
                            loading="eager"
                        >
                    </div>
                </div>

                <!-- Bottom Branding & Quote -->
                <div class="relative z-10 text-center flex flex-col items-center pt-2">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-[#17325c] tracking-wider">SIPUS</h2>
                    <p class="text-xs sm:text-sm font-semibold text-[#294c7b] mt-0.5 tracking-wide">Sistem Informasi Perpustakaan Digital</p>

                    <!-- Decorative small line -->
                    <div class="w-10 h-1 bg-[#3a7ad4] rounded-full my-3"></div>

                    <p class="text-[12px] sm:text-[13px] text-[#526a8c] italic font-normal max-w-xs leading-relaxed">
                        &ldquo;Membaca hari ini, untuk masa depan yang lebih baik&rdquo;
                    </p>
                </div>
            </div>

            <!-- RIGHT COLUMN: Login Form -->
            <div class="lg:col-span-6 bg-white rounded-[24px] sm:rounded-[28px] p-6 sm:p-8 md:p-10 flex flex-col justify-center border border-slate-100 shadow-sm">

                <!-- Login Title -->
                <div class="mb-6">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-[#1a2f4c] tracking-tight">Login</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1.5 font-normal">Masuk untuk melanjutkan ke akun Anda</p>
                </div>

                <!-- Alerts (Validation / Error / Success) -->
                @if (session('error'))
                    <div class="mb-4 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs sm:text-sm flex items-start gap-2.5">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <!-- Login Form -->
                <form action="{{ route('login') }}" method="POST" class="space-y-4 sm:space-y-5" id="loginForm">
                    @csrf

                    <!-- Field: NIS / Username -->
                    <div>
                        <label for="usernameInput" id="labelUsername" class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1.5">
                            NIS / Username
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <!-- User Icon -->
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <input
                                type="text"
                                name="username"
                                id="usernameInput"
                                value="{{ old('username') }}"
                                placeholder="Masukkan NIS atau username"
                                required
                                autofocus
                                class="w-full pl-11 pr-4 py-2.5 sm:py-3 rounded-xl border border-slate-200 text-sm text-slate-800 placeholder-slate-400 bg-slate-50/50 hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all duration-200"
                            >
                        </div>
                    </div>

                    <!-- Field: Password -->
                    <div>
                        <label for="passwordInput" class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1.5">
                            Password
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <!-- Lock Icon -->
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <input
                                type="password"
                                name="password"
                                id="passwordInput"
                                placeholder="Masukkan password"
                                required
                                class="w-full pl-11 pr-11 py-2.5 sm:py-3 rounded-xl border border-slate-200 text-sm text-slate-800 placeholder-slate-400 bg-slate-50/50 hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all duration-200"
                            >
                            <!-- Password Toggle Eye Button -->
                            <button
                                type="button"
                                id="togglePasswordBtn"
                                aria-label="Lihat password"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none transition-colors"
                            >
                                <!-- Eye Open Icon -->
                                <svg id="eyeOpenIcon" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <!-- Eye Slash Icon (hidden initially) -->
                                <svg id="eyeSlashIcon" class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-1">
                        <button
                            type="submit"
                            class="w-full bg-[#3a7ad4] hover:bg-[#2d6ac0] active:bg-[#2457a1] text-white font-semibold py-2.5 sm:py-3 px-4 rounded-xl shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 active:scale-[0.99] transition-all duration-200 text-sm sm:text-base flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>Masuk</span>
                        </button>
                    </div>

                    <!-- Forgot Password Link -->
                    <div class="text-center pt-1">
                        <a href="{{ route('password.request') }}" class="text-xs sm:text-sm font-medium text-[#3a7ad4] hover:text-[#2457a1] hover:underline transition-colors">
                            Lupa password?
                        </a>
                    </div>
                </form>

            </div>

        </div>

    </div>

    <!-- Interactive Scripts -->
    <script>
        // Password Visibility Toggle
        const passwordInput = document.getElementById('passwordInput');
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const eyeOpenIcon = document.getElementById('eyeOpenIcon');
        const eyeSlashIcon = document.getElementById('eyeSlashIcon');

        togglePasswordBtn.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            if (isPassword) {
                passwordInput.setAttribute('type', 'text');
                eyeOpenIcon.classList.add('hidden');
                eyeSlashIcon.classList.remove('hidden');
                togglePasswordBtn.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                passwordInput.setAttribute('type', 'password');
                eyeOpenIcon.classList.remove('hidden');
                eyeSlashIcon.classList.add('hidden');
                togglePasswordBtn.setAttribute('aria-label', 'Lihat password');
            }
        });

    </script>
</body>
</html>

