@php
    $isPustakawan = (auth()->user()->role ?? '') === 'Pustakawan';

    $defaultSearch = $isPustakawan
        ? 'Cari judul buku, anggota...'
        : ($isAdmin ? 'Cari buku, judul, anggota, ...' : 'Cari buku, judul, pengarang...');
    $searchPlaceholder = trim($__env->yieldContent('search_placeholder')) ?: $defaultSearch;

    $brandRoute  = $isPustakawan ? route('pustakawan.dashboard') : ($isAdmin ? route('admin.dashboard') : route('dashboard'));
    $searchRoute = $isPustakawan ? route('pustakawan.peminjaman') : ($isAdmin ? route('admin.buku') : route('katalog'));

    $loginUser = auth()->user();
    $namaTampil = null;
    if ($loginUser) {
        $namaTampil = $loginUser->name
            ?? \Illuminate\Support\Facades\DB::table('siswa')->where('id_user', $loginUser->getAuthIdentifier())->value('nama_siswa')
            ?? $loginUser->username;
    }
@endphp
<header class="topbar">
    {{-- Tombol menu (mobile) --}}
    <button type="button" class="menu-toggle" data-sidebar-toggle aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>

    {{-- Logo ringkas (mobile; di desktop logo ada di sidebar) --}}
    <a href="{{ $brandRoute }}" class="topbar-brand">
        <i class="bi bi-book brand-logo"></i>
        <div class="brand-text">
            <strong>SIPUS</strong>
            <small>Sistem Informasi Perpustakaan<br>SMP Negeri 01 Pasirjambu</small>
        </div>
    </a>

    <form action="{{ $searchRoute }}" method="GET" class="topbar-search">
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="{{ $searchPlaceholder }}">
        <button type="submit" aria-label="Cari"><i class="bi bi-search"></i></button>
    </form>

    <div class="topbar-right">
        {{-- Admin: lonceng hanya di mobile. Siswa: selalu tampil. --}}
        <a href="{{ $isAdmin ? '#' : route('notifikasi') }}" class="topbar-bell {{ $isAdmin ? 'bell-mobile' : '' }}" title="Notifikasi">
            <i class="bi bi-bell"></i>
            {{-- Titik merah muncul bila ada notifikasi belum dibaca.
                 Kirim $notifBaru (boolean) dari view composer, mis. auth()->user()->notifikasi()->belumDibaca()->exists() --}}
            @if ($notifBaru ?? ! $isAdmin)
                <span class="dot"></span>
            @endif
        </a>

        <div class="topbar-user {{ $isAdmin ? 'hide-mobile' : '' }}">
            <i class="bi bi-person-circle avatar"></i>
            <div class="user-meta">
                <span class="user-name">{{ $namaTampil ?: ($isAdmin ? 'Pengguna' : 'Siswa') }}</span>
                @if ($isAdmin)
                    <small>{{ auth()->user()->role === 'Admin' ? 'Admin' : 'Pustakawan' }}</small>
                @endif
            </div>
            <i class="bi bi-chevron-down"></i>
        </div>
    </div>
</header>
