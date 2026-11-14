@php
    // Peran ditentukan oleh halaman (@section('role', 'admin')) atau, jika tidak ada, dari akun yang login.
    $role     = trim($__env->yieldContent('role')) ?: (auth()->user()->role ?? 'siswa');
    $isAdmin  = in_array(strtolower($role), ['admin', 'pustakawan'], true);
    $authRole = auth()->user()->role ?? '';

    // Menu sidebar dipilih sesuai role: Admin, Pustakawan, atau Siswa.
    $sidebar = $isAdmin
        ? ($authRole === 'Pustakawan' ? 'partials.sidebar-pustakawan' : 'partials.sidebar-admin')
        : 'partials.sidebar';

    // Titik merah pada lonceng: ada notifikasi belum dibaca.
    $notifBaru = false;
    if (auth()->check()) {
        if ($isAdmin) {
            $notifBaru = \Illuminate\Support\Facades\DB::table('peminjaman')->where('status', 'Menunggu')->exists();
        } else {
            $idSiswa = \Illuminate\Support\Facades\DB::table('siswa')->where('id_user', auth()->id())->value('id_siswa');
            $notifBaru = $idSiswa && (
                \Illuminate\Support\Facades\DB::table('peminjaman')->where('id_siswa', $idSiswa)->where('status', 'Menunggu')->exists()
                || \App\Support\NotifikasiSiswa::untukSiswa((int) $idSiswa)->isNotEmpty()
            );
        }
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIPUS') - Sistem Informasi Perpustakaan</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/sipus.css') }}?v={{ file_exists(public_path('css/sipus.css')) ? filemtime(public_path('css/sipus.css')) : '1' }}">
    <link rel="stylesheet" href="{{ asset('css/dynamic.css') }}?v={{ file_exists(public_path('css/dynamic.css')) ? filemtime(public_path('css/dynamic.css')) : '1' }}">
    @stack('styles')
</head>
<body>
    <div class="app-wrapper">
        {{-- Sidebar (logo SIPUS + menu), berbeda untuk admin, pustakawan, dan siswa --}}
        @include($sidebar)

        <div class="app-content">
            {{-- Topbar: pencarian, notifikasi, akun --}}
            @include('partials.navbar')

            {{-- Konten halaman --}}
            <main class="app-content-body">
                @yield('content')
            </main>

            {{-- Footer --}}
            @include('partials.footer')
        </div>
    </div>

    {{-- Navigasi bawah (hanya tampil di mobile) --}}
    @include('partials.bottom-nav')

    {{-- Latar gelap saat sidebar dibuka di mobile --}}
    <div class="sidebar-overlay"></div>

    <script src="{{ asset('js/sipus.js') }}"></script>
    <script src="{{ asset('js/dynamic.js') }}"></script>
    @stack('scripts')
</body>
</html>
