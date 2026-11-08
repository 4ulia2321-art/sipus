<aside class="sidebar">
    <div>
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <i class="bi bi-book brand-logo"></i>
            <div class="brand-text">
                <strong>SIPUS</strong>
                <small>Sistem Informasi Perpustakaan<br>SMP Negeri 01 Pasirjambu</small>
            </div>
        </a>

        <nav class="sidebar-menu">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi {{ request()->routeIs('dashboard') ? 'bi-house-door-fill' : 'bi-house-door' }}"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('katalog') }}" class="{{ request()->routeIs('katalog', 'buku.*') ? 'active' : '' }}">
                <i class="bi bi-book"></i> <span>Katalog Buku</span>
            </a>
            <a href="{{ route('pengajuan') }}" class="{{ request()->routeIs('pengajuan*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-plus"></i> <span>Pengajuan Peminjaman</span>
            </a>
            <a href="{{ route('riwayat') }}" class="{{ request()->routeIs('riwayat') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i> <span>Riwayat Peminjaman</span>
            </a>
            <a href="{{ route('notifikasi') }}" class="{{ request()->routeIs('notifikasi*') ? 'active' : '' }}">
                <i class="bi bi-bell"></i> <span>Notifikasi</span>
            </a>
            <a href="{{ route('profil') }}" class="{{ request()->routeIs('profil') ? 'active' : '' }}">
                <i class="bi bi-person"></i> <span>Profil</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-footer">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> <span>Keluar</span>
            </button>
        </form>
    </div>
</aside>
